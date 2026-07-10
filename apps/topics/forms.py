import json

from django import forms
from django.conf import settings
from django.core.exceptions import ValidationError

from .content import validate_prosemirror_document


DEFAULT_DOCUMENT = {
    "type": "doc",
    "content": [
        {
            "type": "paragraph",
            "content": [],
        }
    ],
}


class TopicForm(forms.Form):
    slug = forms.SlugField(label="Slug", max_length=120, required=False)
    title = forms.CharField(label="Titel", max_length=180)
    content_json = forms.CharField(
        label="Inhalt JSON",
        max_length=settings.WIKI_TOPIC_MAX_JSON_SIZE,
        widget=forms.Textarea,
    )
    change_note = forms.CharField(
        label="Aenderungsnotiz",
        max_length=255,
        required=False,
    )

    def __init__(self, *args, include_slug: bool = True, **kwargs):
        super().__init__(*args, **kwargs)
        if not include_slug:
            self.fields.pop("slug")
        else:
            self.fields["slug"].required = True

    def clean_slug(self):
        slug = self.cleaned_data.get("slug", "")
        return slug.lower()

    def clean_content_json(self):
        raw = self.cleaned_data["content_json"]
        if len(raw) > settings.WIKI_TOPIC_MAX_JSON_SIZE:
            raise ValidationError("Topic-Inhalt ist zu gross.")
        try:
            document = json.loads(raw)
        except json.JSONDecodeError as exc:
            raise ValidationError("Inhalt ist kein gueltiges JSON.") from exc
        validate_prosemirror_document(document)
        return document


def document_to_json(document: dict) -> str:
    return json.dumps(document, ensure_ascii=False, indent=2)

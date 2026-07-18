from django import forms


class AttachmentUploadForm(forms.Form):
    file = forms.FileField(label="Datei")
    change_note = forms.CharField(
        label="Aenderungsnotiz",
        max_length=255,
        required=False,
    )

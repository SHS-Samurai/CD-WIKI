from django import forms


class CommentForm(forms.Form):
    body = forms.CharField(
        label="Kommentar",
        max_length=5000,
        widget=forms.Textarea(attrs={"rows": 5}),
    )

    def clean_body(self):
        body = self.cleaned_data["body"].strip()
        if not body:
            raise forms.ValidationError("Kommentar darf nicht leer sein.")
        return body

from django import forms
from django.conf import settings
from django.contrib.auth import get_user_model
from django.contrib.auth.forms import UserCreationForm
from django.utils import timezone


class RegistrationForm(UserCreationForm):
    email = forms.EmailField(label="E-Mail")
    website = forms.CharField(
        required=False,
        widget=forms.HiddenInput,
    )
    started_at = forms.IntegerField(widget=forms.HiddenInput)

    class Meta:
        model = get_user_model()
        fields = ("username", "email", "password1", "password2")
        labels = {
            "username": "Benutzername",
        }

    def __init__(self, *args, **kwargs):
        super().__init__(*args, **kwargs)
        self.fields["started_at"].initial = int(timezone.now().timestamp())
        self.fields["password1"].label = "Passwort"
        self.fields["password2"].label = "Passwort wiederholen"

    def clean_email(self):
        email = self.cleaned_data["email"].strip().lower()
        user_model = get_user_model()
        if user_model.objects.filter(email__iexact=email).exists():
            raise forms.ValidationError("Diese E-Mail-Adresse ist bereits registriert.")
        return email

    def clean(self):
        cleaned = super().clean()
        if cleaned.get("website"):
            raise forms.ValidationError("Registrierung konnte nicht verarbeitet werden.")

        started_at = cleaned.get("started_at")
        if started_at:
            elapsed = timezone.now().timestamp() - started_at
            if elapsed < settings.WIKI_REGISTRATION_MIN_SECONDS:
                raise forms.ValidationError("Das Formular wurde zu schnell abgesendet.")
        return cleaned

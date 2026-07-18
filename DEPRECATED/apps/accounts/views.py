from urllib.parse import urlencode

from django.contrib import messages
from django.contrib.auth.views import LoginView, LogoutView
from django.shortcuts import redirect, render
from django.urls import reverse

from apps.audit.models import AuditAction
from apps.audit.services import write_audit_log

from .forms import RegistrationForm
from .models import RateLimitScope, RegistrationMode
from .rate_limits import (
    clear_rate_limit,
    rate_limit_status,
    record_rate_limit_hit,
    request_rate_limit_identifier,
)
from .services import RegistrationDisabled, confirm_email_token, register_user, registration_settings


class RateLimitedLoginView(LoginView):
    template_name = "registration/login.html"

    def post(self, request, *args, **kwargs):
        identifier = request_rate_limit_identifier(request)
        state = rate_limit_status(RateLimitScope.LOGIN, identifier)
        if state.blocked:
            form = self.get_form()
            form.add_error(None, _rate_limit_message(state.retry_after))
            return self._blocked_response(form, state.retry_after)
        return super().post(request, *args, **kwargs)

    def form_valid(self, form):
        identifier = request_rate_limit_identifier(self.request)
        clear_rate_limit(RateLimitScope.LOGIN, identifier)
        write_audit_log(
            action=AuditAction.LOGIN_SUCCESS,
            user=form.get_user(),
            request=self.request,
        )
        return super().form_valid(form)

    def form_invalid(self, form):
        identifier = request_rate_limit_identifier(self.request)
        state = record_rate_limit_hit(RateLimitScope.LOGIN, identifier)
        attempted_username = self.request.POST.get("username", "")[:150]
        write_audit_log(
            action=AuditAction.LOGIN_FAILED,
            request=self.request,
            username=attempted_username,
            details={"rate_limited": state.blocked},
        )
        if state.blocked:
            write_audit_log(
                action=AuditAction.RATE_LIMIT_BLOCKED,
                request=self.request,
                username=attempted_username,
                details={"scope": RateLimitScope.LOGIN},
            )
            form.add_error(None, _rate_limit_message(state.retry_after))
            return self._blocked_response(form, state.retry_after)
        return super().form_invalid(form)

    def _blocked_response(self, form, retry_after):
        response = self.render_to_response(self.get_context_data(form=form), status=429)
        response["Retry-After"] = str(retry_after)
        return response


class AuditedLogoutView(LogoutView):
    def post(self, request, *args, **kwargs):
        if request.user.is_authenticated:
            write_audit_log(action=AuditAction.LOGOUT, request=request)
        return super().post(request, *args, **kwargs)


def admin_login_redirect(request):
    next_url = request.GET.get("next") or request.POST.get("next") or reverse("admin:index")
    login_url = f"{reverse('login')}?{urlencode({'next': next_url})}"
    return redirect(login_url)


def register(request):
    settings_row = registration_settings()
    if settings_row.mode == RegistrationMode.DISABLED:
        return render(request, "registration/register_disabled.html", status=403)

    if request.method == "POST":
        identifier = request_rate_limit_identifier(request)
        state = rate_limit_status(RateLimitScope.REGISTRATION, identifier)
        if state.blocked:
            form = RegistrationForm(request.POST)
            form.add_error(None, _rate_limit_message(state.retry_after))
            response = render(
                request,
                "registration/register.html",
                {"form": form, "registration_mode": settings_row.mode},
                status=429,
            )
            response["Retry-After"] = str(state.retry_after)
            return response

        state = record_rate_limit_hit(RateLimitScope.REGISTRATION, identifier)
        if state.blocked:
            write_audit_log(
                action=AuditAction.RATE_LIMIT_BLOCKED,
                request=request,
                username=request.POST.get("username", "")[:150],
                details={"scope": RateLimitScope.REGISTRATION},
            )
        form = RegistrationForm(request.POST)
        if form.is_valid():
            try:
                user, mode, _ = register_user(form=form, request=request)
            except RegistrationDisabled:
                return render(request, "registration/register_disabled.html", status=403)

            if mode == RegistrationMode.AUTOMATIC:
                messages.success(request, "Registrierung abgeschlossen. Du kannst dich jetzt anmelden.")
            elif mode == RegistrationMode.EMAIL_CONFIRMATION:
                messages.success(request, "Registrierung gespeichert. Bitte bestaetige deine E-Mail-Adresse.")
            else:
                messages.success(request, "Registrierung gespeichert. Ein Administrator muss den Benutzer freischalten.")
            return redirect("login")
    else:
        form = RegistrationForm()

    return render(
        request,
        "registration/register.html",
        {
            "form": form,
            "registration_mode": settings_row.mode,
        },
    )


def confirm_registration(request, token):
    user = confirm_email_token(token, request=request)
    if user is None:
        return render(request, "registration/confirmation_invalid.html", status=400)
    messages.success(request, "E-Mail-Adresse bestaetigt. Du kannst dich jetzt anmelden.")
    return redirect("login")


def _rate_limit_message(retry_after: int) -> str:
    minutes = max(1, (retry_after + 59) // 60)
    return f"Zu viele Versuche. Bitte in {minutes} Minute(n) erneut versuchen."

"""Django settings for the wiki project."""

from pathlib import Path
import os

from django.core.exceptions import ImproperlyConfigured
from dotenv import load_dotenv


BASE_DIR = Path(__file__).resolve().parent.parent
load_dotenv(BASE_DIR / ".env")


def env(name: str, default: str | None = None) -> str | None:
    return os.environ.get(name, default)


def env_bool(name: str, default: bool = False) -> bool:
    value = env(name)
    if value is None:
        return default
    return value.strip().lower() in {"1", "true", "yes", "on"}


def env_int(name: str, default: int) -> int:
    value = env(name)
    if value is None or value == "":
        return default
    return int(value)


def env_list(name: str, default: list[str] | None = None) -> list[str]:
    value = env(name)
    if value is None or value.strip() == "":
        return default or []
    return [item.strip() for item in value.split(",") if item.strip()]


def env_path(name: str, default: Path) -> Path:
    value = env(name)
    if not value:
        return default
    path = Path(value)
    if path.is_absolute():
        return path
    return BASE_DIR / path


ENVIRONMENT = (env("DJANGO_ENVIRONMENT", "development") or "development").strip().lower()
if ENVIRONMENT not in {"development", "test", "production"}:
    raise ImproperlyConfigured("DJANGO_ENVIRONMENT muss development, test oder production sein.")
IS_PRODUCTION = ENVIRONMENT == "production"
DEVELOPMENT_SECRET_KEY = "django-insecure-local-development-change-me"
SECRET_KEY = env("DJANGO_SECRET_KEY", DEVELOPMENT_SECRET_KEY)
DEBUG = env_bool("DJANGO_DEBUG", not IS_PRODUCTION)
ALLOWED_HOSTS = env_list("DJANGO_ALLOWED_HOSTS", ["127.0.0.1", "localhost"])
CSRF_TRUSTED_ORIGINS = env_list("DJANGO_CSRF_TRUSTED_ORIGINS")

if IS_PRODUCTION:
    if DEBUG:
        raise ImproperlyConfigured("DJANGO_DEBUG muss in Produktion deaktiviert sein.")
    if SECRET_KEY == DEVELOPMENT_SECRET_KEY or len(SECRET_KEY or "") < 50:
        raise ImproperlyConfigured("DJANGO_SECRET_KEY muss in Produktion sicher gesetzt sein.")
    if not ALLOWED_HOSTS or "*" in ALLOWED_HOSTS:
        raise ImproperlyConfigured("DJANGO_ALLOWED_HOSTS muss in Produktion explizit gesetzt sein.")


INSTALLED_APPS = [
    "apps.accounts.apps.AccountsConfig",
    "apps.webs.apps.WebsConfig",
    "apps.topics.apps.TopicsConfig",
    "apps.attachments.apps.AttachmentsConfig",
    "apps.comments.apps.CommentsConfig",
    "apps.audit.apps.AuditConfig",
    "apps.search.apps.SearchConfig",
    "apps.plugins.apps.PluginsConfig",
    "apps.theme.apps.ThemeConfig",
    "django.contrib.admin",
    "django.contrib.auth",
    "django.contrib.contenttypes",
    "django.contrib.sessions",
    "django.contrib.messages",
    "django.contrib.staticfiles",
]

MIDDLEWARE = [
    "django.middleware.security.SecurityMiddleware",
    "apps.security_headers.SecurityHeadersMiddleware",
    "django.contrib.sessions.middleware.SessionMiddleware",
    "django.middleware.common.CommonMiddleware",
    "django.middleware.csrf.CsrfViewMiddleware",
    "django.contrib.auth.middleware.AuthenticationMiddleware",
    "django.contrib.messages.middleware.MessageMiddleware",
    "django.middleware.clickjacking.XFrameOptionsMiddleware",
]

ROOT_URLCONF = "config.urls"

TEMPLATES = [
    {
        "BACKEND": "django.template.backends.django.DjangoTemplates",
        "DIRS": [BASE_DIR / "templates"],
        "APP_DIRS": True,
        "OPTIONS": {
            "context_processors": [
                "django.template.context_processors.debug",
                "django.template.context_processors.request",
                "django.contrib.auth.context_processors.auth",
                "django.contrib.messages.context_processors.messages",
                "apps.theme.context_processors.active_theme",
            ],
        },
    },
]

WSGI_APPLICATION = "config.wsgi.application"


DATABASES = {
    "default": {
        "ENGINE": "django.db.backends.mysql",
        "NAME": env("DB_NAME", "cd-wiki"),
        "USER": env("DB_USER", "wiki"),
        "PASSWORD": env("DB_PASSWORD", ""),
        "HOST": env("DB_HOST", "127.0.0.1"),
        "PORT": env("DB_PORT", "3306"),
        "CONN_MAX_AGE": env_int("DB_CONN_MAX_AGE", 60),
        "OPTIONS": {
            "charset": "utf8mb4",
            "init_command": "SET sql_mode='STRICT_TRANS_TABLES'",
        },
        "TEST": {
            "NAME": env("DB_TEST_NAME", "test_wiki"),
        },
    }
}

AUTH_USER_MODEL = "accounts.User"

AUTH_PASSWORD_VALIDATORS = [
    {
        "NAME": "django.contrib.auth.password_validation.UserAttributeSimilarityValidator",
    },
    {
        "NAME": "django.contrib.auth.password_validation.MinimumLengthValidator",
    },
    {
        "NAME": "django.contrib.auth.password_validation.CommonPasswordValidator",
    },
    {
        "NAME": "django.contrib.auth.password_validation.NumericPasswordValidator",
    },
]


LANGUAGE_CODE = "de-de"
TIME_ZONE = "Europe/Berlin"
USE_I18N = True
USE_TZ = True


STATIC_URL = "static/"
STATIC_ROOT = BASE_DIR / "staticfiles"
STATICFILES_DIRS = [BASE_DIR / "static"]

WIKI_STORAGE_ROOT = env_path("WIKI_STORAGE_ROOT", BASE_DIR / "storage")
FILE_UPLOAD_MAX_MEMORY_SIZE = env_int("DJANGO_FILE_UPLOAD_MAX_MEMORY_SIZE", 5 * 1024 * 1024)
DATA_UPLOAD_MAX_MEMORY_SIZE = env_int("DJANGO_DATA_UPLOAD_MAX_MEMORY_SIZE", 20 * 1024 * 1024)
WIKI_MAX_ATTACHMENT_SIZE = env_int("WIKI_MAX_ATTACHMENT_SIZE", 25 * 1024 * 1024)
WIKI_MAX_ARCHIVE_MEMBERS = env_int("WIKI_MAX_ARCHIVE_MEMBERS", 2000)
WIKI_MAX_ARCHIVE_UNCOMPRESSED_SIZE = env_int(
    "WIKI_MAX_ARCHIVE_UNCOMPRESSED_SIZE",
    100 * 1024 * 1024,
)
WIKI_MAX_PDF_PAGES = env_int("WIKI_MAX_PDF_PAGES", 500)
WIKI_MAX_SPREADSHEET_CELLS = env_int("WIKI_MAX_SPREADSHEET_CELLS", 200_000)
WIKI_ALLOWED_ATTACHMENT_EXTENSIONS = {
    ".pdf",
    ".docx",
    ".txt",
    ".md",
    ".xlsx",
    ".html",
}
WIKI_BLOCKED_ATTACHMENT_EXTENSIONS = {
    ".php",
    ".py",
    ".js",
    ".sh",
    ".exe",
    ".bat",
    ".cmd",
    ".ps1",
    ".jar",
    ".war",
}

MEILISEARCH = {
    "URL": env("MEILISEARCH_URL", "http://127.0.0.1:7700"),
    "MASTER_KEY": env("MEILISEARCH_MASTER_KEY", ""),
}
if IS_PRODUCTION and not MEILISEARCH["MASTER_KEY"]:
    raise ImproperlyConfigured("MEILISEARCH_MASTER_KEY muss in Produktion gesetzt sein.")
WIKI_SEARCH_INDEX_NAME = env("WIKI_SEARCH_INDEX_NAME", "wiki_topics")
WIKI_SEARCH_RESULT_LIMIT = env_int("WIKI_SEARCH_RESULT_LIMIT", 20)
WIKI_SEARCH_BACKEND_LIMIT = env_int("WIKI_SEARCH_BACKEND_LIMIT", 100)
WIKI_SEARCH_ATTACHMENT_TEXT_LIMIT = env_int("WIKI_SEARCH_ATTACHMENT_TEXT_LIMIT", 200_000)
WIKI_TOPIC_MAX_JSON_SIZE = env_int("WIKI_TOPIC_MAX_JSON_SIZE", 2_000_000)
WIKI_TOPIC_MAX_NODES = env_int("WIKI_TOPIC_MAX_NODES", 10_000)
WIKI_TOPIC_MAX_DEPTH = env_int("WIKI_TOPIC_MAX_DEPTH", 64)
WIKI_TOPIC_MAX_TEXT_LENGTH = env_int("WIKI_TOPIC_MAX_TEXT_LENGTH", 1_000_000)

WIKI_REGISTRATION_MODE = env("WIKI_REGISTRATION_MODE", "disabled")
WIKI_REGISTRATION_MIN_SECONDS = env_int("WIKI_REGISTRATION_MIN_SECONDS", 3)
WIKI_EMAIL_CONFIRMATION_HOURS = env_int("WIKI_EMAIL_CONFIRMATION_HOURS", 48)
WIKI_LOGIN_RATE_LIMIT = env_int("WIKI_LOGIN_RATE_LIMIT", 5)
WIKI_LOGIN_RATE_WINDOW_SECONDS = env_int("WIKI_LOGIN_RATE_WINDOW_SECONDS", 900)
WIKI_LOGIN_RATE_BLOCK_SECONDS = env_int("WIKI_LOGIN_RATE_BLOCK_SECONDS", 900)
WIKI_REGISTRATION_RATE_LIMIT = env_int("WIKI_REGISTRATION_RATE_LIMIT", 3)
WIKI_REGISTRATION_RATE_WINDOW_SECONDS = env_int(
    "WIKI_REGISTRATION_RATE_WINDOW_SECONDS",
    3600,
)
WIKI_REGISTRATION_RATE_BLOCK_SECONDS = env_int(
    "WIKI_REGISTRATION_RATE_BLOCK_SECONDS",
    3600,
)
WIKI_RATE_LIMIT_RETENTION_HOURS = env_int("WIKI_RATE_LIMIT_RETENTION_HOURS", 168)
WIKI_TRUSTED_PROXY_IPS = env_list("WIKI_TRUSTED_PROXY_IPS", ["127.0.0.1", "::1"])
WIKI_CONTENT_SECURITY_POLICY = env(
    "WIKI_CONTENT_SECURITY_POLICY",
    "default-src 'self'; base-uri 'self'; connect-src 'self'; font-src 'self'; "
    "form-action 'self'; frame-ancestors 'none'; img-src 'self' data:; "
    "object-src 'none'; script-src 'self'; style-src 'self' 'unsafe-inline'",
)

DEFAULT_AUTO_FIELD = "django.db.models.BigAutoField"

EMAIL_BACKEND = env(
    "DJANGO_EMAIL_BACKEND",
    "django.core.mail.backends.console.EmailBackend",
)
if IS_PRODUCTION and EMAIL_BACKEND == "django.core.mail.backends.console.EmailBackend":
    raise ImproperlyConfigured("Ein produktives E-Mail-Backend muss konfiguriert sein.")

LOGIN_URL = "login"
LOGIN_REDIRECT_URL = "home"
LOGOUT_REDIRECT_URL = "home"

TRUST_X_FORWARDED_PROTO = env_bool("DJANGO_TRUST_X_FORWARDED_PROTO", IS_PRODUCTION)
SECURE_PROXY_SSL_HEADER = (
    ("HTTP_X_FORWARDED_PROTO", "https") if TRUST_X_FORWARDED_PROTO else None
)
SESSION_COOKIE_SECURE = env_bool("DJANGO_SESSION_COOKIE_SECURE", IS_PRODUCTION)
CSRF_COOKIE_SECURE = env_bool("DJANGO_CSRF_COOKIE_SECURE", IS_PRODUCTION)
SECURE_SSL_REDIRECT = env_bool("DJANGO_SECURE_SSL_REDIRECT", IS_PRODUCTION)
SECURE_HSTS_SECONDS = env_int("DJANGO_SECURE_HSTS_SECONDS", 31536000 if IS_PRODUCTION else 0)
SECURE_HSTS_INCLUDE_SUBDOMAINS = env_bool(
    "DJANGO_SECURE_HSTS_INCLUDE_SUBDOMAINS",
    IS_PRODUCTION,
)
SECURE_HSTS_PRELOAD = env_bool("DJANGO_SECURE_HSTS_PRELOAD", False)
SECURE_CONTENT_TYPE_NOSNIFF = True
USE_X_FORWARDED_HOST = env_bool("DJANGO_USE_X_FORWARDED_HOST", False)
X_FRAME_OPTIONS = "DENY"

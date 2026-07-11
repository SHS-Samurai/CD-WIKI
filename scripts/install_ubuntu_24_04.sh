#!/usr/bin/env bash

set -Eeuo pipefail
umask 027

readonly APP_NAME="cd-wiki"
readonly APP_USER="cdwiki"
readonly APP_GROUP="cdwiki"
readonly APP_DIR="/var/www/cd-wiki"
readonly VENV_DIR="${APP_DIR}/.venv"
readonly CONFIG_DIR="/etc/cd-wiki"
readonly APP_ENV="${CONFIG_DIR}/wiki.env"
readonly STORAGE_DIR="/var/lib/cd-wiki/storage"
readonly STATIC_DIR="/var/lib/cd-wiki/static"
readonly BACKUP_DIR="/var/backups/cd-wiki"
readonly MEILI_USER="meilisearch"
readonly MEILI_GROUP="meilisearch"
readonly MEILI_ENV="/etc/meilisearch.env"
readonly MEILI_DATA_DIR="/var/lib/meilisearch"
readonly APACHE_SITE="/etc/apache2/sites-available/wiki.only-space.de.conf"
readonly INSTALL_MARKER="${CONFIG_DIR}/installed"
readonly DB_NAME="cd-wiki"
readonly DB_USER="cdwiki"
readonly SOURCE_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd -P)"

TEMP_MEILI_BINARY=""
TEMP_MEILI_METADATA=""
DOMAIN=""
LETSENCRYPT_EMAIL=""
SMTP_HOST=""
SMTP_PORT=""
SMTP_USER=""
SMTP_PASSWORD=""
SMTP_USE_TLS="true"
SMTP_USE_SSL="false"
DEFAULT_FROM_EMAIL=""
ADMIN_USERNAME=""
ADMIN_EMAIL=""
ADMIN_PASSWORD=""
MEILISEARCH_VERSION=""
MEILISEARCH_SHA256=""
SOURCE_REVISION="manueller-upload"

log() {
    printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*"
}

die() {
    printf 'FEHLER: %s\n' "$*" >&2
    exit 1
}

on_error() {
    local exit_code=$?
    printf 'FEHLER in Zeile %s (Exit-Code %s). Die Installation wurde nicht als abgeschlossen markiert.\n' \
        "${BASH_LINENO[0]}" "$exit_code" >&2
    exit "$exit_code"
}
trap on_error ERR

cleanup() {
    if [[ -n $TEMP_MEILI_BINARY && -f $TEMP_MEILI_BINARY ]]; then
        rm -f -- "$TEMP_MEILI_BINARY"
    fi
    if [[ -n $TEMP_MEILI_METADATA && -f $TEMP_MEILI_METADATA ]]; then
        rm -f -- "$TEMP_MEILI_METADATA"
    fi
}
trap cleanup EXIT

usage() {
    cat <<'EOF'
Aufruf:
  sudo bash scripts/install_ubuntu_24_04.sh
  sudo bash scripts/install_ubuntu_24_04.sh --check

Die Erstinstallation fragt alle erforderlichen Werte interaktiv ab.
EOF
}

require_root() {
    [[ ${EUID} -eq 0 ]] || die "Das Skript muss mit sudo oder als root ausgefuehrt werden."
}

require_ubuntu_2404() {
    # shellcheck source=/dev/null
    source /etc/os-release
    [[ ${ID:-} == "ubuntu" && ${VERSION_ID:-} == "24.04" ]] || \
        die "Unterstuetzt wird ausschliesslich Ubuntu 24.04 LTS."
}

require_value() {
    local name=$1
    [[ -n ${!name:-} ]] || die "Pflichtwert ${name} fehlt."
    [[ ${!name} != *$'\n'* && ${!name} != *$'\r'* ]] || die "${name} darf keinen Zeilenumbruch enthalten."
}

prompt_value() {
    local variable=$1 label=$2 default=${3:-} value
    if [[ -n $default ]]; then
        read -r -p "${label} [${default}]: " value
        value=${value:-$default}
    else
        while [[ -z ${value:-} ]]; do
            read -r -p "${label}: " value
        done
    fi
    printf -v "$variable" '%s' "$value"
}

prompt_secret() {
    local variable=$1 label=$2 minimum_length=${3:-1} first second
    while true; do
        read -r -s -p "${label}: " first
        printf '\n'
        read -r -s -p "${label} wiederholen: " second
        printf '\n'
        [[ -n $first ]] || { printf 'Der Wert darf nicht leer sein.\n' >&2; continue; }
        (( ${#first} >= minimum_length )) || {
            printf 'Der Wert muss mindestens %s Zeichen lang sein.\n' "$minimum_length" >&2
            continue
        }
        [[ $first == "$second" ]] || { printf 'Die Eingaben stimmen nicht ueberein.\n' >&2; continue; }
        printf -v "$variable" '%s' "$first"
        return
    done
}

collect_installation_values() {
    local smtp_mode
    [[ -t 0 ]] || die "Die Erstinstallation benoetigt ein interaktives Terminal."

    printf '\nCD-Wiki Erstinstallation\n'
    printf 'Alle Angaben werden nur fuer diese Installation verwendet. Passwoerter bleiben unsichtbar.\n\n'
    prompt_value DOMAIN "Domain" "wiki.only-space.de"
    prompt_value ADMIN_USERNAME "Administrator-Benutzername" "admin"
    prompt_value ADMIN_EMAIL "Administrator-E-Mail"
    prompt_secret ADMIN_PASSWORD "Administrator-Passwort" 16
    prompt_value LETSENCRYPT_EMAIL "E-Mail fuer Let's Encrypt" "$ADMIN_EMAIL"
    prompt_value SMTP_HOST "SMTP-Server"
    prompt_value SMTP_USER "SMTP-Benutzer"
    prompt_secret SMTP_PASSWORD "SMTP-Passwort"
    prompt_value smtp_mode "SMTP-Sicherheit (starttls oder ssl)" "starttls"
    case "${smtp_mode,,}" in
        starttls)
            SMTP_USE_TLS=true
            SMTP_USE_SSL=false
            prompt_value SMTP_PORT "SMTP-Port" "587"
            ;;
        ssl)
            SMTP_USE_TLS=false
            SMTP_USE_SSL=true
            prompt_value SMTP_PORT "SMTP-Port" "465"
            ;;
        *) die "SMTP-Sicherheit muss starttls oder ssl sein." ;;
    esac
    prompt_value DEFAULT_FROM_EMAIL "Absenderadresse" "$SMTP_USER"
}

confirm_installation() {
    local confirmation
    printf '\nZusammenfassung:\n'
    printf '  Domain:           %s\n' "$DOMAIN"
    printf '  Administrator:    %s <%s>\n' "$ADMIN_USERNAME" "$ADMIN_EMAIL"
    printf '  SMTP:             %s:%s\n' "$SMTP_HOST" "$SMTP_PORT"
    printf '  Datenbank:        %s\n' "$DB_NAME"
    printf '  Anwendungspfad:   %s\n\n' "$APP_DIR"
    read -r -p "Installation jetzt vollstaendig starten? [ja/NEIN]: " confirmation
    [[ ${confirmation,,} == "ja" ]] || die "Installation auf Wunsch abgebrochen."
}

validate_config_values() {
    local required=(
        DOMAIN LETSENCRYPT_EMAIL SMTP_HOST SMTP_PORT SMTP_USER SMTP_PASSWORD
        DEFAULT_FROM_EMAIL ADMIN_USERNAME ADMIN_EMAIL ADMIN_PASSWORD
    )
    local name
    for name in "${required[@]}"; do
        require_value "$name"
    done

    [[ $DOMAIN =~ ^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?$ ]] || die "DOMAIN ist ungueltig."
    [[ $DOMAIN == "${DOMAIN,,}" ]] || die "DOMAIN muss in Kleinbuchstaben angegeben werden."
    [[ $DOMAIN == *.* ]] || die "DOMAIN muss ein vollstaendiger DNS-Name sein."
    local email_pattern='^[A-Za-z0-9_.+%-]+@[A-Za-z0-9.-]+$'
    [[ $LETSENCRYPT_EMAIL =~ $email_pattern && $ADMIN_EMAIL =~ $email_pattern && $DEFAULT_FROM_EMAIL =~ $email_pattern ]] || \
        die "E-Mail-Adressen sind ungueltig."
    [[ $SMTP_HOST =~ ^[A-Za-z0-9.-]+$ ]] || die "SMTP_HOST ist ungueltig."
    [[ $SMTP_USER =~ ^[A-Za-z0-9_.@+%:/=-]+$ ]] || die "SMTP_USER enthaelt ungueltige Zeichen."
    [[ $SMTP_PORT =~ ^[0-9]+$ ]] || die "SMTP_PORT muss numerisch sein."
    (( SMTP_PORT >= 1 && SMTP_PORT <= 65535 )) || die "SMTP_PORT liegt ausserhalb des gueltigen Bereichs."
    [[ ${SMTP_USE_TLS:-true} =~ ^(true|false)$ ]] || die "SMTP_USE_TLS muss true oder false sein."
    [[ ${SMTP_USE_SSL:-false} =~ ^(true|false)$ ]] || die "SMTP_USE_SSL muss true oder false sein."
    [[ ! ( ${SMTP_USE_TLS:-true} == true && ${SMTP_USE_SSL:-false} == true ) ]] || \
        die "SMTP_USE_TLS und SMTP_USE_SSL duerfen nicht gleichzeitig true sein."
    [[ $ADMIN_USERNAME =~ ^[A-Za-z0-9_.@+-]+$ ]] || die "ADMIN_USERNAME enthaelt ungueltige Zeichen."
    (( ${#ADMIN_PASSWORD} >= 16 )) || die "ADMIN_PASSWORD muss mindestens 16 Zeichen lang sein."
}

quarantine_uploaded_runtime_files() {
    local relative_path source_path destination_path
    local quarantine_dir=""
    local runtime_paths=(
        ".venv"
        ".env"
        "staticfiles"
        "frontend/editor/node_modules"
    )

    for relative_path in "${runtime_paths[@]}"; do
        source_path="${APP_DIR}/${relative_path}"
        [[ -e "$source_path" || -L "$source_path" ]] || continue
        if [[ -z $quarantine_dir ]]; then
            quarantine_dir="/var/backups/cd-wiki-upload-$(date -u '+%Y%m%dT%H%M%SZ')"
            install -d -o root -g root -m 0700 "$quarantine_dir"
            log "Verschiebe mitkopierte Laufzeitdateien nach ${quarantine_dir}."
        fi
        destination_path="${quarantine_dir}/${relative_path}"
        install -d -o root -g root -m 0700 "$(dirname -- "$destination_path")"
        mv -- "$source_path" "$destination_path"
        log "Gesichert: ${relative_path}"
    done
}

ensure_fresh_target() {
    local reserved_path
    [[ ! -e "$INSTALL_MARKER" ]] || die "Die Installation ist bereits abgeschlossen. Verwende --check."
    [[ "$SOURCE_DIR" == "$APP_DIR" ]] || \
        die "Das Projekt muss vollstaendig unter ${APP_DIR} liegen. Aktuell: ${SOURCE_DIR}"
    [[ ! -e "$CONFIG_DIR" ]] || \
        die "${CONFIG_DIR} existiert bereits. Vor erneutem Start Ursache pruefen und kontrolliert zurueckrollen."
    quarantine_uploaded_runtime_files
    [[ ! -e /etc/systemd/system/cd-wiki.service && ! -e /etc/systemd/system/meilisearch.service ]] || \
        die "Eine der vorgesehenen systemd-Units existiert bereits."
    for reserved_path in \
        "$APACHE_SITE" /etc/mysql/mysql.conf.d/cd-wiki.cnf "$MEILI_ENV" \
        /usr/local/bin/meilisearch "$MEILI_DATA_DIR"; do
        [[ ! -e "$reserved_path" ]] || die "Reservierter Zielpfad existiert bereits: ${reserved_path}"
    done
    ! getent passwd "$APP_USER" >/dev/null || die "Systembenutzer ${APP_USER} existiert bereits."
    ! getent group "$APP_GROUP" >/dev/null || die "Systemgruppe ${APP_GROUP} existiert bereits."
    ! getent passwd "$MEILI_USER" >/dev/null || die "Systembenutzer ${MEILI_USER} existiert bereits."
    ! getent group "$MEILI_GROUP" >/dev/null || die "Systemgruppe ${MEILI_GROUP} existiert bereits."
    [[ -f "${SOURCE_DIR}/manage.py" && -f "${SOURCE_DIR}/static/editor/wiki-editor.js" ]] || \
        die "Projektquelle oder gebautes Editor-Bundle fehlt."
    if command -v git >/dev/null && git -C "$SOURCE_DIR" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
        [[ -z $(git -C "$SOURCE_DIR" status --porcelain) ]] || \
            die "Das Git-Arbeitsverzeichnis ist nicht sauber."
        SOURCE_REVISION=$(git -C "$SOURCE_DIR" rev-parse HEAD)
        log "Projektstand: Git-Commit ${SOURCE_REVISION}"
    else
        log "Hinweis: Kein Git-Metadatenverzeichnis vorhanden; installiere die hochgeladenen Projektdateien."
    fi
    if ss -ltnH | awk '{print $4}' | grep -Eq '(^|:)8000$|(^|:)7700$'; then
        die "Port 8000 oder 7700 ist bereits belegt."
    fi
}

write_env_line() {
    local file=$1 name=$2 value=$3
    [[ $value != *$'\n'* && $value != *$'\r'* ]] || die "Umgebungswert ${name} enthaelt einen Zeilenumbruch."
    printf '%s=%s\n' "$name" "$value" >> "$file"
}

run_manage() {
    runuser -u "$APP_USER" -- "$VENV_DIR/bin/python" "$APP_DIR/manage.py" "$@"
}

check_system_django() {
    local version
    if version=$(python3 -c 'import django; print(django.get_version())' 2>/dev/null); then
        log "Django ${version} ist systemweit vorhanden; verwendet wird trotzdem die isolierte Projektumgebung."
    else
        log "Django ist systemweit nicht installiert und wird in der isolierten Projektumgebung eingerichtet."
    fi
}

test_smtp_connection() {
    log "Pruefe die verschluesselte SMTP-Anmeldung."
    SMTP_PASSWORD="$SMTP_PASSWORD" python3 - \
        "$SMTP_HOST" "$SMTP_PORT" "$SMTP_USER" "$SMTP_USE_SSL" <<'PY'
import os
import smtplib
import ssl
import sys

host, port, username, use_ssl = sys.argv[1:]
context = ssl.create_default_context()
if use_ssl == "true":
    with smtplib.SMTP_SSL(host, int(port), timeout=20, context=context) as smtp:
        smtp.ehlo()
        smtp.login(username, os.environ["SMTP_PASSWORD"])
else:
    with smtplib.SMTP(host, int(port), timeout=20) as smtp:
        smtp.ehlo()
        smtp.starttls(context=context)
        smtp.ehlo()
        smtp.login(username, os.environ["SMTP_PASSWORD"])
PY
    log "SMTP-Anmeldung erfolgreich."
}

install_packages() {
    log "Installiere Ubuntu-Pakete."
    export DEBIAN_FRONTEND=noninteractive
    apt-get update
    apt-get install -y --no-install-recommends \
        apache2 ca-certificates certbot curl default-libmysqlclient-dev \
        build-essential git mysql-server pkg-config python3 python3-dev python3-pip \
        python3-venv python3-certbot-apache
    systemctl enable --now mysql apache2

    cat > /etc/mysql/mysql.conf.d/cd-wiki.cnf <<'EOF'
[mysqld]
bind-address=127.0.0.1
mysqlx-bind-address=127.0.0.1
local-infile=0
EOF
    systemctl restart mysql
    check_system_django
}

obtain_certificate() {
    log "Pruefe DNS und fordere das TLS-Zertifikat an."
    getent ahosts "$DOMAIN" >/dev/null || die "DOMAIN ist per DNS nicht aufloesbar: ${DOMAIN}"
    install -d -o root -g www-data -m 0750 /var/www/cd-wiki-acme/.well-known/acme-challenge

    cat > "$APACHE_SITE" <<EOF
<VirtualHost *:80>
    ServerName ${DOMAIN}
    DocumentRoot /var/www/cd-wiki-acme

    <Directory /var/www/cd-wiki-acme>
        Options -Indexes
        AllowOverride None
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/cd-wiki-error.log
    CustomLog \${APACHE_LOG_DIR}/cd-wiki-access.log combined
</VirtualHost>
EOF
    a2ensite "$(basename "$APACHE_SITE")"
    apache2ctl configtest
    systemctl reload apache2

    if [[ ! -f "/etc/letsencrypt/live/${DOMAIN}/fullchain.pem" ]]; then
        certbot certonly --webroot -w /var/www/cd-wiki-acme \
            --non-interactive --agree-tos --no-eff-email \
            --email "$LETSENCRYPT_EMAIL" -d "$DOMAIN"
    fi
}

create_accounts_and_paths() {
    log "Lege getrennte Systembenutzer und geschuetzte Pfade an."
    addgroup --system "$APP_GROUP"
    adduser --system --ingroup "$APP_GROUP" --home /nonexistent --no-create-home --shell /usr/sbin/nologin "$APP_USER"
    addgroup --system "$MEILI_GROUP"
    adduser --system --ingroup "$MEILI_GROUP" --home /nonexistent --no-create-home --shell /usr/sbin/nologin "$MEILI_USER"

    install -d -o root -g root -m 0755 /var/lib/cd-wiki
    install -d -o root -g "$APP_GROUP" -m 0750 "$APP_DIR" "$CONFIG_DIR"
    install -d -o "$APP_USER" -g "$APP_GROUP" -m 0750 "$STORAGE_DIR"
    install -d -o "$APP_USER" -g www-data -m 2750 "$STATIC_DIR"
    install -d -o root -g root -m 0700 "$BACKUP_DIR"
    install -d -o "$MEILI_USER" -g "$MEILI_GROUP" -m 0750 \
        "$MEILI_DATA_DIR/data" "$MEILI_DATA_DIR/dumps" "$MEILI_DATA_DIR/snapshots"

    chown -R root:"$APP_GROUP" "$APP_DIR"
    find "$APP_DIR" -type d -exec chmod 0750 {} +
    find "$APP_DIR" -type f -perm /111 -exec chmod 0750 {} +
    find "$APP_DIR" -type f ! -perm /111 -exec chmod 0640 {} +
}

install_meilisearch() {
    local architecture asset download_url release_info
    architecture=$(dpkg --print-architecture)
    case "$architecture" in
        amd64) asset="meilisearch-linux-amd64" ;;
        arm64) asset="meilisearch-linux-aarch64" ;;
        *) die "Meilisearch wird von diesem Skript auf ${architecture} nicht unterstuetzt." ;;
    esac

    TEMP_MEILI_METADATA=$(mktemp)
    log "Ermittle die aktuelle stabile Meilisearch-Version."
    curl --fail --location --silent --show-error --retry 3 --retry-all-errors \
        --proto '=https' --tlsv1.2 \
        -H 'Accept: application/vnd.github+json' \
        -H 'X-GitHub-Api-Version: 2022-11-28' \
        https://api.github.com/repos/meilisearch/meilisearch/releases/latest \
        -o "$TEMP_MEILI_METADATA"
    release_info=$(python3 - "$TEMP_MEILI_METADATA" "$asset" <<'PY'
import json
import re
import sys

metadata_path, asset_name = sys.argv[1:]
with open(metadata_path, encoding="utf-8") as source:
    release = json.load(source)

version = release.get("tag_name", "")
if not re.fullmatch(r"v\d+\.\d+\.\d+", version):
    raise SystemExit("Ungueltige Meilisearch-Versionsangabe in der GitHub-Antwort.")

for asset in release.get("assets", []):
    if asset.get("name") != asset_name:
        continue
    url = asset.get("browser_download_url", "")
    digest = asset.get("digest", "") or ""
    if not url.startswith("https://github.com/meilisearch/meilisearch/releases/download/"):
        raise SystemExit("Ungueltige Meilisearch-Downloadadresse.")
    if not re.fullmatch(r"sha256:[0-9a-fA-F]{64}", digest):
        raise SystemExit("GitHub liefert fuer das Meilisearch-Asset keinen gueltigen SHA-256-Digest.")
    print(f"{version}\t{url}\t{digest.removeprefix('sha256:')}")
    break
else:
    raise SystemExit(f"Meilisearch-Asset nicht gefunden: {asset_name}")
PY
    )
    IFS=$'\t' read -r MEILISEARCH_VERSION download_url MEILISEARCH_SHA256 <<< "$release_info"
    rm -f "$TEMP_MEILI_METADATA"
    TEMP_MEILI_METADATA=""

    TEMP_MEILI_BINARY=$(mktemp)
    log "Lade Meilisearch ${MEILISEARCH_VERSION} und pruefe SHA-256."
    curl --fail --location --silent --show-error --retry 3 --retry-all-errors \
        --proto '=https' --tlsv1.2 "$download_url" \
        -o "$TEMP_MEILI_BINARY"
    printf '%s  %s\n' "${MEILISEARCH_SHA256,,}" "$TEMP_MEILI_BINARY" | sha256sum --check --status || \
        die "Die Meilisearch-Pruefsumme stimmt nicht."
    install -o root -g root -m 0755 "$TEMP_MEILI_BINARY" /usr/local/bin/meilisearch
    rm -f "$TEMP_MEILI_BINARY"
    TEMP_MEILI_BINARY=""
}

configure_database_and_environment() {
    local db_password django_secret meili_master smtp_password_b64
    db_password=$(openssl rand -hex 32)
    django_secret=$(openssl rand -hex 48)
    meili_master=$(openssl rand -hex 32)
    smtp_password_b64=$(printf '%s' "$SMTP_PASSWORD" | base64 -w 0)

    if mysql --protocol=socket --batch --skip-column-names -e \
        "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='${DB_NAME}'" | grep -q .; then
        die "Die Datenbank ${DB_NAME} existiert bereits; es wird nichts ueberschrieben."
    fi
    if mysql --protocol=socket --batch --skip-column-names -e \
        "SELECT User FROM mysql.user WHERE User='${DB_USER}' AND Host='127.0.0.1'" | grep -q .; then
        die "Der MySQL-Benutzer ${DB_USER}@127.0.0.1 existiert bereits."
    fi

    log "Erstelle MySQL-Datenbank und minimal berechtigten Anwendungsbenutzer."
    mysql --protocol=socket <<SQL
CREATE DATABASE \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${db_password}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
SQL

    : > "$APP_ENV"
    write_env_line "$APP_ENV" DJANGO_ENVIRONMENT production
    write_env_line "$APP_ENV" DJANGO_DEBUG False
    write_env_line "$APP_ENV" DJANGO_SECRET_KEY "$django_secret"
    write_env_line "$APP_ENV" DJANGO_ALLOWED_HOSTS "$DOMAIN"
    write_env_line "$APP_ENV" DJANGO_CSRF_TRUSTED_ORIGINS "https://${DOMAIN}"
    write_env_line "$APP_ENV" DJANGO_TRUST_X_FORWARDED_PROTO True
    write_env_line "$APP_ENV" DJANGO_SESSION_COOKIE_SECURE True
    write_env_line "$APP_ENV" DJANGO_CSRF_COOKIE_SECURE True
    write_env_line "$APP_ENV" DJANGO_SECURE_SSL_REDIRECT True
    write_env_line "$APP_ENV" DJANGO_SECURE_HSTS_SECONDS 86400
    write_env_line "$APP_ENV" DJANGO_SECURE_HSTS_INCLUDE_SUBDOMAINS False
    write_env_line "$APP_ENV" DJANGO_SECURE_HSTS_PRELOAD False
    write_env_line "$APP_ENV" DB_NAME "$DB_NAME"
    write_env_line "$APP_ENV" DB_USER "$DB_USER"
    write_env_line "$APP_ENV" DB_PASSWORD "$db_password"
    write_env_line "$APP_ENV" DB_HOST 127.0.0.1
    write_env_line "$APP_ENV" DB_PORT 3306
    write_env_line "$APP_ENV" DB_CONN_MAX_AGE 60
    write_env_line "$APP_ENV" MEILISEARCH_URL http://127.0.0.1:7700
    write_env_line "$APP_ENV" MEILISEARCH_MASTER_KEY "$meili_master"
    write_env_line "$APP_ENV" WIKI_STORAGE_ROOT "$STORAGE_DIR"
    write_env_line "$APP_ENV" DJANGO_STATIC_ROOT "$STATIC_DIR"
    write_env_line "$APP_ENV" WIKI_REGISTRATION_MODE disabled
    write_env_line "$APP_ENV" WIKI_TRUSTED_PROXY_IPS 127.0.0.1,::1
    write_env_line "$APP_ENV" DJANGO_EMAIL_BACKEND django.core.mail.backends.smtp.EmailBackend
    write_env_line "$APP_ENV" DJANGO_EMAIL_HOST "$SMTP_HOST"
    write_env_line "$APP_ENV" DJANGO_EMAIL_PORT "$SMTP_PORT"
    write_env_line "$APP_ENV" DJANGO_EMAIL_HOST_USER "$SMTP_USER"
    write_env_line "$APP_ENV" DJANGO_EMAIL_HOST_PASSWORD_B64 "$smtp_password_b64"
    write_env_line "$APP_ENV" DJANGO_EMAIL_USE_TLS "${SMTP_USE_TLS:-true}"
    write_env_line "$APP_ENV" DJANGO_EMAIL_USE_SSL "${SMTP_USE_SSL:-false}"
    write_env_line "$APP_ENV" DJANGO_DEFAULT_FROM_EMAIL "$DEFAULT_FROM_EMAIL"
    chown root:"$APP_GROUP" "$APP_ENV"
    chmod 0640 "$APP_ENV"
    ln -s "$APP_ENV" "${APP_DIR}/.env"

    : > "$MEILI_ENV"
    write_env_line "$MEILI_ENV" MEILI_ENV production
    write_env_line "$MEILI_ENV" MEILI_HTTP_ADDR 127.0.0.1:7700
    write_env_line "$MEILI_ENV" MEILI_DB_PATH "$MEILI_DATA_DIR/data"
    write_env_line "$MEILI_ENV" MEILI_DUMP_DIR "$MEILI_DATA_DIR/dumps"
    write_env_line "$MEILI_ENV" MEILI_SNAPSHOT_DIR "$MEILI_DATA_DIR/snapshots"
    write_env_line "$MEILI_ENV" MEILI_MASTER_KEY "$meili_master"
    write_env_line "$MEILI_ENV" MEILI_NO_ANALYTICS true
    chown root:"$MEILI_GROUP" "$MEILI_ENV"
    chmod 0640 "$MEILI_ENV"
}

install_python_application() {
    local django_version
    log "Installiere Python-Abhaengigkeiten und bereite Django vor."
    python3 -m venv "$VENV_DIR"
    "$VENV_DIR/bin/python" -m pip install --upgrade pip
    "$VENV_DIR/bin/python" -m pip install -r "$APP_DIR/requirements.txt"
    django_version=$("$VENV_DIR/bin/python" -c 'import django; print(django.get_version())')
    [[ $django_version == 5.2.* ]] || \
        die "In der Projektumgebung wurde Django ${django_version} statt 5.2.x installiert."
    log "Django ${django_version} ist in ${VENV_DIR} einsatzbereit."
    chown -R root:"$APP_GROUP" "$VENV_DIR"
    find "$VENV_DIR" -type d -exec chmod 0750 {} +
    find "$VENV_DIR" -type f -perm /111 -exec chmod 0750 {} +
    find "$VENV_DIR" -type f ! -perm /111 -exec chmod 0640 {} +

    mysqldump --protocol=socket --single-transaction --no-tablespaces \
        --routines --triggers --events --hex-blob "$DB_NAME" \
        | gzip -9 > "${BACKUP_DIR}/pre-migrate-$(date -u '+%Y%m%dT%H%M%SZ').sql.gz"
    run_manage check
    run_manage migrate --noinput
    run_manage collectstatic --noinput
    DJANGO_SUPERUSER_PASSWORD="$ADMIN_PASSWORD" runuser -u "$APP_USER" -- \
        env DJANGO_SUPERUSER_PASSWORD="$ADMIN_PASSWORD" \
        "$VENV_DIR/bin/python" "$APP_DIR/manage.py" createsuperuser --noinput \
        --username "$ADMIN_USERNAME" --email "$ADMIN_EMAIL"
}

install_services() {
    log "Installiere gehaertete systemd-Dienste."
    cat > /etc/systemd/system/meilisearch.service <<EOF
[Unit]
Description=Meilisearch fuer CD-Wiki
After=network.target

[Service]
Type=simple
User=${MEILI_USER}
Group=${MEILI_GROUP}
EnvironmentFile=${MEILI_ENV}
ExecStart=/usr/local/bin/meilisearch
Restart=on-failure
RestartSec=5s
UMask=0027
NoNewPrivileges=true
PrivateDevices=true
PrivateTmp=true
ProtectControlGroups=true
ProtectHome=true
ProtectKernelModules=true
ProtectKernelTunables=true
ProtectSystem=strict
ReadWritePaths=${MEILI_DATA_DIR}
RestrictAddressFamilies=AF_INET AF_INET6 AF_UNIX

[Install]
WantedBy=multi-user.target
EOF

    cat > /etc/systemd/system/cd-wiki.service <<EOF
[Unit]
Description=CD-Wiki Gunicorn
After=network.target mysql.service meilisearch.service
Requires=mysql.service meilisearch.service

[Service]
Type=simple
User=${APP_USER}
Group=${APP_GROUP}
WorkingDirectory=${APP_DIR}
EnvironmentFile=${APP_ENV}
Environment=PYTHONDONTWRITEBYTECODE=1
ExecStart=${VENV_DIR}/bin/gunicorn --bind 127.0.0.1:8000 --workers 3 --timeout 60 --access-logfile - --error-logfile - config.wsgi:application
Restart=on-failure
RestartSec=5s
UMask=0027
NoNewPrivileges=true
PrivateDevices=true
PrivateTmp=true
ProtectControlGroups=true
ProtectHome=true
ProtectKernelModules=true
ProtectKernelTunables=true
ProtectSystem=strict
ReadWritePaths=${STORAGE_DIR}
RestrictAddressFamilies=AF_INET AF_INET6 AF_UNIX

[Install]
WantedBy=multi-user.target
EOF

    cat > /etc/systemd/system/cd-wiki-maintenance.service <<EOF
[Unit]
Description=CD-Wiki Wartungsaufgaben
After=mysql.service

[Service]
Type=oneshot
User=${APP_USER}
Group=${APP_GROUP}
WorkingDirectory=${APP_DIR}
EnvironmentFile=${APP_ENV}
Environment=PYTHONDONTWRITEBYTECODE=1
ExecStart=${VENV_DIR}/bin/python ${APP_DIR}/manage.py prune_rate_limits
NoNewPrivileges=true
PrivateTmp=true
ProtectHome=true
ProtectSystem=strict
ReadWritePaths=${STORAGE_DIR}
EOF

    cat > /etc/systemd/system/cd-wiki-maintenance.timer <<'EOF'
[Unit]
Description=Taegliche CD-Wiki Wartung

[Timer]
OnCalendar=daily
RandomizedDelaySec=30m
Persistent=true

[Install]
WantedBy=timers.target
EOF

    systemctl daemon-reload
    systemctl enable --now meilisearch.service
    systemctl enable --now cd-wiki.service cd-wiki-maintenance.timer
}

configure_apache() {
    log "Konfiguriere Apache als HTTPS-Reverse-Proxy."
    a2enmod headers proxy proxy_http rewrite ssl
    cat > "$APACHE_SITE" <<EOF
<VirtualHost *:80>
    ServerName ${DOMAIN}
    Alias /.well-known/acme-challenge/ /var/www/cd-wiki-acme/.well-known/acme-challenge/

    <Directory /var/www/cd-wiki-acme/.well-known/acme-challenge>
        Options -Indexes
        AllowOverride None
        Require all granted
    </Directory>

    RewriteEngine On
    RewriteCond %{REQUEST_URI} !^/\.well-known/acme-challenge/
    RewriteRule ^ https://${DOMAIN}%{REQUEST_URI} [R=301,L,NE]

    ErrorLog \${APACHE_LOG_DIR}/cd-wiki-error.log
    CustomLog \${APACHE_LOG_DIR}/cd-wiki-access.log combined
</VirtualHost>

<VirtualHost *:443>
    ServerName ${DOMAIN}
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/${DOMAIN}/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/${DOMAIN}/privkey.pem
    SSLProtocol all -SSLv3 -TLSv1 -TLSv1.1

    ProxyRequests Off
    ProxyPreserveHost On
    RequestHeader set X-Forwarded-Proto "https"

    Alias /static/ ${STATIC_DIR}/
    <Directory ${STATIC_DIR}>
        Options -Indexes
        AllowOverride None
        Require all granted
    </Directory>

    ProxyPass /static/ !
    ProxyPass / http://127.0.0.1:8000/ retry=0 timeout=60
    ProxyPassReverse / http://127.0.0.1:8000/
    LimitRequestBody 27262976

    ErrorLog \${APACHE_LOG_DIR}/cd-wiki-error.log
    CustomLog \${APACHE_LOG_DIR}/cd-wiki-access.log combined
</VirtualHost>
EOF
    install -d -o root -g root -m 0755 /etc/letsencrypt/renewal-hooks/deploy
    cat > /etc/letsencrypt/renewal-hooks/deploy/reload-apache <<'EOF'
#!/usr/bin/env sh
set -eu
apache2ctl configtest
systemctl reload apache2
EOF
    chmod 0755 /etc/letsencrypt/renewal-hooks/deploy/reload-apache
    apache2ctl configtest
    systemctl reload apache2
}

post_install_checks() {
    log "Fuehre Produktions- und Erreichbarkeitspruefungen aus."
    run_manage check --deploy
    run_manage reindex_search
    systemctl --quiet is-active mysql meilisearch cd-wiki apache2
    curl --fail --silent --show-error http://127.0.0.1:7700/health >/dev/null
    curl --fail --silent --show-error -H "Host: ${DOMAIN}" -H "X-Forwarded-Proto: https" \
        http://127.0.0.1:8000/ >/dev/null
    curl --fail --silent --show-error --resolve "${DOMAIN}:443:127.0.0.1" \
        "https://${DOMAIN}/" >/dev/null
    certbot renew --dry-run
}

check_installation() {
    [[ -f "$INSTALL_MARKER" ]] || die "Keine abgeschlossene Installation gefunden."
    [[ -r "$APP_ENV" ]] || die "Produktionskonfiguration ist nicht lesbar: ${APP_ENV}"
    DOMAIN=$(awk -F= '$1 == "DJANGO_ALLOWED_HOSTS" { print $2; exit }' "$APP_ENV")
    [[ $DOMAIN =~ ^[A-Za-z0-9]([A-Za-z0-9.-]*[A-Za-z0-9])?$ ]] || \
        die "DJANGO_ALLOWED_HOSTS enthaelt keinen einzelnen gueltigen Domainnamen."
    post_install_checks
    log "Alle Installationspruefungen waren erfolgreich."
}

main() {
    require_root
    require_ubuntu_2404

    if [[ $# -eq 1 && $1 == "--check" ]]; then
        check_installation
        exit 0
    fi

    [[ $# -eq 0 ]] || { usage; exit 2; }
    ensure_fresh_target
    collect_installation_values
    validate_config_values
    confirm_installation
    install_packages
    test_smtp_connection
    obtain_certificate
    create_accounts_and_paths
    install_meilisearch
    configure_database_and_environment
    install_python_application
    install_services
    configure_apache
    post_install_checks

    printf 'Installiert am %s aus %s; Revision %s; Meilisearch %s\n' \
        "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" "$SOURCE_DIR" "$SOURCE_REVISION" \
        "$MEILISEARCH_VERSION" > "$INSTALL_MARKER"
    chmod 0640 "$INSTALL_MARKER"
    unset SMTP_PASSWORD ADMIN_PASSWORD
    log "Installation abgeschlossen: https://${DOMAIN}/"
}

if [[ ${BASH_SOURCE[0]} == "$0" ]]; then
    main "$@"
fi

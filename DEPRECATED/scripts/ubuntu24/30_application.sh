#!/usr/bin/env bash

set -Eeuo pipefail
umask 027

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
# shellcheck source=common.sh
source "${SCRIPT_DIR}/common.sh"

stage_start
require_stage 20
[[ ! -f "${STATE_DIR}/stage-30.done" ]] || die "Stufe 30 wurde bereits abgeschlossen."
[[ ! -e "$VENV_DIR" ]] || die "Virtuelle Python-Umgebung existiert bereits."

runtime_backup="${BACKUP_DIR}/uploaded-runtime-$(date -u '+%Y%m%dT%H%M%SZ')"
for relative_path in .env staticfiles frontend/editor/node_modules; do
    source_path="${APP_DIR}/${relative_path}"
    [[ -e "$source_path" || -L "$source_path" ]] || continue
    destination_path="${runtime_backup}/${relative_path}"
    install -d -o root -g root -m 0700 "$(dirname -- "$destination_path")"
    mv -- "$source_path" "$destination_path"
    log "Mitkopierte Laufzeitdatei gesichert: ${relative_path}"
done

chown -R root:"$APP_GROUP" "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 0750 {} +
find "$APP_DIR" -type f -perm /111 -exec chmod 0750 {} +
find "$APP_DIR" -type f ! -perm /111 -exec chmod 0640 {} +

python3 -m venv "$VENV_DIR"
MAKEFLAGS=-j1 "$VENV_DIR/bin/python" -m pip install --timeout 30 --retries 3 \
    -r "$APP_DIR/requirements.txt"
"$VENV_DIR/bin/python" -m pip check
django_version=$("$VENV_DIR/bin/python" -c 'import django; print(django.get_version())')
[[ $django_version == 5.2.* ]] || die "Django ${django_version} statt 5.2.x installiert."

chown -R root:"$APP_GROUP" "$VENV_DIR"
find "$VENV_DIR" -type d -exec chmod 0750 {} +
find "$VENV_DIR" -type f -perm /111 -exec chmod 0750 {} +
find "$VENV_DIR" -type f ! -perm /111 -exec chmod 0640 {} +
ln -s "$APP_ENV" "${APP_DIR}/.env"

run_as_app_timeout 120s check
run_as_app_timeout 120s check --deploy
run_as_app_timeout 120s makemigrations --check --dry-run
run_as_app_timeout 120s migrate --plan
run_as_app_timeout 300s migrate --noinput
run_as_app_timeout 180s collectstatic --noinput

read -r -p "Administrator-Benutzername [admin]: " admin_username
admin_username=${admin_username:-admin}
read -r -p "Administrator-E-Mail: " admin_email
[[ $admin_username =~ ^[A-Za-z0-9_.@+-]+$ ]] || die "Ungueltiger Benutzername."
[[ $admin_email =~ ^[A-Za-z0-9_.+%-]+@[A-Za-z0-9.-]+$ ]] || die "Ungueltige E-Mail-Adresse."
while true; do
    read -r -s -p "Administrator-Passwort (mindestens 16 Zeichen): " admin_password
    printf '\n'
    read -r -s -p "Administrator-Passwort wiederholen: " admin_password_repeat
    printf '\n'
    (( ${#admin_password} >= 16 )) || { printf 'Passwort ist zu kurz.\n' >&2; continue; }
    [[ $admin_password == "$admin_password_repeat" ]] || \
        { printf 'Passwoerter stimmen nicht ueberein.\n' >&2; continue; }
    break
done

runuser -u "$APP_USER" -- env DJANGO_SUPERUSER_PASSWORD="$admin_password" \
    "$VENV_DIR/bin/python" "$APP_DIR/manage.py" createsuperuser --noinput \
    --username "$admin_username" --email "$admin_email"
unset admin_password admin_password_repeat

stage_finish 30
log "Naechste Stufe: bash scripts/install_cd_wiki.sh search"

#!/usr/bin/env bash

set -Eeuo pipefail
umask 027

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
# shellcheck source=common.sh
source "${SCRIPT_DIR}/common.sh"

stage_start
require_stage 10
[[ ! -f "${STATE_DIR}/stage-20.done" ]] || die "Stufe 20 wurde bereits abgeschlossen."
[[ ! -e "$CONFIG_DIR" && ! -e "$APP_ENV" ]] || die "CD-Wiki-Konfiguration existiert bereits."

systemctl --quiet is-active mysql.service || die "MySQL ist nicht aktiv."
mysql_listener=$(ss -ltnp | grep -E '(:3306|:33060)' || true)
[[ -n $mysql_listener ]] || die "Kein lokaler MySQL-Listener gefunden."
printf '%s\n' "$mysql_listener" | grep -Eq '0\.0\.0\.0:|\[::\]:' && \
    die "MySQL lauscht oeffentlich. Aus Sicherheitsgruenden wird nicht weiter installiert."

mysql --protocol=socket --batch --skip-column-names -e \
    "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='${DB_NAME}'" | \
    grep -q . && die "Datenbank ${DB_NAME} existiert bereits."
mysql --protocol=socket --batch --skip-column-names -e \
    "SELECT User FROM mysql.user WHERE User='${DB_USER}' AND Host='127.0.0.1'" | \
    grep -q . && die "MySQL-Benutzer ${DB_USER}@127.0.0.1 existiert bereits."

getent passwd "$APP_USER" >/dev/null && die "Systembenutzer ${APP_USER} existiert bereits."
getent group "$APP_GROUP" >/dev/null && die "Systemgruppe ${APP_GROUP} existiert bereits."
addgroup --system "$APP_GROUP"
adduser --system --ingroup "$APP_GROUP" --home /nonexistent --no-create-home \
    --shell /usr/sbin/nologin "$APP_USER"

install -d -o root -g "$APP_GROUP" -m 0750 "$CONFIG_DIR"
install -d -o root -g root -m 0755 /var/lib/cd-wiki
install -d -o "$APP_USER" -g "$APP_GROUP" -m 0750 "$STORAGE_DIR"
install -d -o "$APP_USER" -g www-data -m 2750 "$STATIC_DIR"
install -d -o root -g root -m 0700 "$BACKUP_DIR"

db_password=$(openssl rand -hex 32)
django_secret=$(openssl rand -hex 48)
meili_master=$(openssl rand -hex 32)

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
write_env_line "$APP_ENV" DJANGO_TRUST_X_FORWARDED_PROTO False
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
write_env_line "$APP_ENV" DJANGO_EMAIL_BACKEND django.core.mail.backends.dummy.EmailBackend
write_env_line "$APP_ENV" DJANGO_DEFAULT_FROM_EMAIL "no-reply@${DOMAIN}"
chown root:"$APP_GROUP" "$APP_ENV"
chmod 0640 "$APP_ENV"

mysqldump --protocol=socket --single-transaction --no-tablespaces \
    --routines --triggers --events --hex-blob "$DB_NAME" | gzip -9 > \
    "${BACKUP_DIR}/before-first-migration-$(date -u '+%Y%m%dT%H%M%SZ').sql.gz"

stage_finish 20
log "Naechste Stufe: bash scripts/install_cd_wiki.sh application"

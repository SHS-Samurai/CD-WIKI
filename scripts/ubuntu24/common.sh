#!/usr/bin/env bash

set -Eeuo pipefail
umask 027

readonly APP_DIR="/var/www/cd-wiki"
readonly VENV_DIR="${APP_DIR}/.venv"
readonly APP_USER="cdwiki"
readonly APP_GROUP="cdwiki"
readonly DB_NAME="cd-wiki"
readonly DB_USER="cdwiki"
readonly CONFIG_DIR="/etc/cd-wiki"
readonly APP_ENV="${CONFIG_DIR}/wiki.env"
readonly STORAGE_DIR="/var/lib/cd-wiki/storage"
readonly STATIC_DIR="/var/lib/cd-wiki/static"
readonly BACKUP_DIR="/var/backups/cd-wiki"
readonly STATE_DIR="/var/lib/cd-wiki-installer"
readonly STATE_FILE="${STATE_DIR}/state.env"
readonly SSH_BASELINE="${STATE_DIR}/ssh.sha256"
readonly MEILI_USER="meilisearch"
readonly MEILI_GROUP="meilisearch"
readonly MEILI_ENV="/etc/meilisearch.env"
readonly MEILI_DATA_DIR="/var/lib/meilisearch"
readonly APACHE_SITE="/etc/apache2/sites-available/wiki.only-space.de.conf"
readonly APACHE_WSGI_CONF="/etc/apache2/conf-available/cd-wiki-wsgi.conf"

log() {
    printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*"
}

die() {
    printf 'FEHLER: %s\n' "$*" >&2
    exit 1
}

require_root() {
    [[ ${EUID} -eq 0 ]] || die "Diese Stufe muss als root ausgefuehrt werden."
}

require_ubuntu_2404() {
    # shellcheck source=/dev/null
    source /etc/os-release
    [[ ${ID:-} == "ubuntu" && ${VERSION_ID:-} == "24.04" ]] || \
        die "Unterstuetzt wird ausschliesslich Ubuntu 24.04 LTS."
}

require_state() {
    [[ -f "$STATE_FILE" ]] || die "Zuerst 00_preflight.sh ausfuehren."
    [[ $(stat -c '%u' "$STATE_FILE") == 0 ]] || die "Unsicherer Besitzer von ${STATE_FILE}."
    [[ $(stat -c '%a' "$STATE_FILE") == 600 ]] || die "Unsichere Rechte von ${STATE_FILE}."
    # shellcheck source=/dev/null
    source "$STATE_FILE"
}

require_stage() {
    local stage=$1
    [[ -f "${STATE_DIR}/stage-${stage}.done" ]] || die "Vorherige Stufe ${stage} fehlt."
}

mark_stage() {
    local stage=$1
    printf '%s\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')" > "${STATE_DIR}/stage-${stage}.done"
    chmod 0600 "${STATE_DIR}/stage-${stage}.done"
}

assert_ssh_access() {
    systemctl --quiet is-active ssh.service || die "ssh.service ist nicht aktiv. Keine weitere Aenderung."
    ss -ltnp | grep -q 'sshd' || die "Kein SSH-Listener gefunden. Keine weitere Aenderung."
    if [[ -f "$SSH_BASELINE" ]]; then
        sha256sum --check --quiet "$SSH_BASELINE" || \
            die "Eine Datei unter /etc/ssh wurde seit dem Preflight veraendert."
    fi
}

write_ssh_baseline() {
    : > "$SSH_BASELINE"
    while IFS= read -r -d '' file; do
        sha256sum "$file" >> "$SSH_BASELINE"
    done < <(find /etc/ssh -type f -print0 | sort -z)
    chmod 0600 "$SSH_BASELINE"
}

run_as_app() {
    runuser -u "$APP_USER" -- "$VENV_DIR/bin/python" "$APP_DIR/manage.py" "$@"
}

run_as_app_timeout() {
    local duration=$1
    shift
    timeout "$duration" runuser -u "$APP_USER" -- \
        "$VENV_DIR/bin/python" "$APP_DIR/manage.py" "$@"
}

write_env_line() {
    local file=$1 name=$2 value=$3
    [[ $value != *$'\n'* && $value != *$'\r'* ]] || die "Ungueltiger Wert fuer ${name}."
    printf '%s=%s\n' "$name" "$value" >> "$file"
}

stage_start() {
    require_root
    require_ubuntu_2404
    require_state
    assert_ssh_access
}

stage_finish() {
    local stage=$1
    assert_ssh_access
    mark_stage "$stage"
    log "Stufe ${stage} abgeschlossen. SSH-Konfiguration und SSH-Dienst sind unveraendert."
}

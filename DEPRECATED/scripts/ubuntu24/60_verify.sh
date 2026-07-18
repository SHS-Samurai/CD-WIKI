#!/usr/bin/env bash

set -Eeuo pipefail
umask 027

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
# shellcheck source=common.sh
source "${SCRIPT_DIR}/common.sh"

stage_start
require_stage 50

log "Pruefe Apache, Dienste, lokale Listener und Django."
apache2ctl configtest
systemctl --quiet is-active ssh.service
systemctl --quiet is-active mysql.service
systemctl --quiet is-active meilisearch.service
systemctl --quiet is-active apache2.service
systemctl --quiet is-active certbot.timer
systemctl --quiet is-active cd-wiki-maintenance.timer

mysql_listener=$(ss -ltnH | awk '$4 ~ /:3306$|:33060$/ {print $4}')
[[ -n $mysql_listener ]] || die "MySQL-Listener fehlt."
printf '%s\n' "$mysql_listener" | grep -Eq '^0\.0\.0\.0:|^\[::\]:' && \
    die "MySQL lauscht oeffentlich."
meili_listener=$(ss -ltnH | awk '$4 ~ /:7700$/ {print $4}')
[[ $meili_listener == "127.0.0.1:7700" ]] || die "Meilisearch ist nicht exklusiv an IPv4-Loopback gebunden."
ss -ltnH | awk '{print $4}' | grep -Eq '(^|:)8000$' && die "Unerwarteter Anwendungsport 8000 ist offen."

run_as_app_timeout 120s check --deploy
run_as_app_timeout 300s reindex_search
curl --fail --silent --show-error --connect-timeout 5 --max-time 20 \
    http://127.0.0.1:7700/health >/dev/null
curl --fail --silent --show-error --connect-timeout 10 --max-time 30 \
    --resolve "${DOMAIN}:443:127.0.0.1" "https://${DOMAIN}/" >/dev/null

assert_ssh_access
mark_stage 60
cat > "${CONFIG_DIR}/installed" <<EOF
Installiert: $(date -u '+%Y-%m-%dT%H:%M:%SZ')
Quelle: ${SOURCE_REVISION}
Domain: ${DOMAIN}
Meilisearch: ${MEILISEARCH_VERSION:-unbekannt}
EOF
chown root:"$APP_GROUP" "${CONFIG_DIR}/installed"
chmod 0640 "${CONFIG_DIR}/installed"

log "Installation technisch abgeschlossen: https://${DOMAIN}/"
log "Jetzt Login, Rechte, privates Web, Revisionen, Attachments, Kommentare und Suche im Browser pruefen."

#!/usr/bin/env bash

set -Eeuo pipefail
umask 027

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
# shellcheck source=common.sh
source "${SCRIPT_DIR}/common.sh"

stage_start
require_stage 30
[[ ! -f "${STATE_DIR}/stage-40.done" ]] || die "Stufe 40 wurde bereits abgeschlossen."

for reserved_path in /usr/local/bin/meilisearch "$MEILI_ENV" "$MEILI_DATA_DIR" \
    /etc/systemd/system/meilisearch.service \
    /etc/systemd/system/cd-wiki-maintenance.service \
    /etc/systemd/system/cd-wiki-maintenance.timer; do
    [[ ! -e "$reserved_path" ]] || die "Reservierter Zielpfad existiert: ${reserved_path}"
done
getent passwd "$MEILI_USER" >/dev/null && die "Systembenutzer ${MEILI_USER} existiert bereits."
getent group "$MEILI_GROUP" >/dev/null && die "Systemgruppe ${MEILI_GROUP} existiert bereits."
ss -ltnH | awk '{print $4}' | grep -Eq '(^|:)7700$' && die "Port 7700 ist bereits belegt."

meili_master=$(awk -F= '$1 == "MEILISEARCH_MASTER_KEY" {print $2; exit}' "$APP_ENV")
[[ $meili_master =~ ^[0-9a-f]{64}$ ]] || die "Ungueltiger Meilisearch-Schluessel in ${APP_ENV}."

architecture=$(dpkg --print-architecture)
case "$architecture" in
    amd64) asset="meilisearch-linux-amd64" ;;
    arm64) asset="meilisearch-linux-aarch64" ;;
    *) die "Meilisearch wird auf ${architecture} nicht unterstuetzt." ;;
esac

metadata=$(mktemp)
binary=$(mktemp)
cleanup() {
    rm -f -- "$metadata" "$binary"
}
trap cleanup EXIT

log "Ermittle das aktuelle stabile Meilisearch-Release."
curl --fail --location --silent --show-error --retry 3 --retry-all-errors \
    --connect-timeout 15 --max-time 60 --proto '=https' --tlsv1.2 \
    -H 'Accept: application/vnd.github+json' \
    -H 'X-GitHub-Api-Version: 2022-11-28' \
    https://api.github.com/repos/meilisearch/meilisearch/releases/latest -o "$metadata"

release_info=$(python3 - "$metadata" "$asset" <<'PY'
import json
import re
import sys

metadata_path, asset_name = sys.argv[1:]
with open(metadata_path, encoding="utf-8") as source:
    release = json.load(source)

version = release.get("tag_name", "")
if not re.fullmatch(r"v\d+\.\d+\.\d+", version):
    raise SystemExit("Ungueltige Versionsangabe in der GitHub-Antwort.")

for item in release.get("assets", []):
    if item.get("name") != asset_name:
        continue
    url = item.get("browser_download_url", "")
    digest = item.get("digest", "") or ""
    if not url.startswith("https://github.com/meilisearch/meilisearch/releases/download/"):
        raise SystemExit("Ungueltige Downloadadresse.")
    if not re.fullmatch(r"sha256:[0-9a-fA-F]{64}", digest):
        raise SystemExit("Kein gueltiger SHA-256-Digest fuer das Release-Asset.")
    print(f"{version}\t{url}\t{digest.removeprefix('sha256:')}")
    break
else:
    raise SystemExit(f"Release-Asset nicht gefunden: {asset_name}")
PY
)
IFS=$'\t' read -r meili_version download_url expected_sha256 <<< "$release_info"

log "Lade Meilisearch ${meili_version} und pruefe den SHA-256-Digest."
curl --fail --location --silent --show-error --retry 3 --retry-all-errors \
    --connect-timeout 15 --max-time 180 --proto '=https' --tlsv1.2 \
    "$download_url" -o "$binary"
printf '%s  %s\n' "${expected_sha256,,}" "$binary" | sha256sum --check --status || \
    die "Die Meilisearch-Pruefsumme stimmt nicht."

addgroup --system "$MEILI_GROUP"
adduser --system --ingroup "$MEILI_GROUP" --home /nonexistent --no-create-home \
    --shell /usr/sbin/nologin "$MEILI_USER"
install -d -o "$MEILI_USER" -g "$MEILI_GROUP" -m 0750 \
    "$MEILI_DATA_DIR/data" "$MEILI_DATA_DIR/dumps" "$MEILI_DATA_DIR/snapshots"
install -o root -g root -m 0755 "$binary" /usr/local/bin/meilisearch

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
TimeoutStartSec=60s
TimeoutStopSec=30s
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
MemoryAccounting=true
MemoryHigh=30%
MemoryMax=40%
CPUQuota=100%
TasksMax=64
Nice=10

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

timeout 60s systemctl daemon-reload
timeout 60s systemctl enable --now meilisearch.service
timeout 60s systemctl enable --now cd-wiki-maintenance.timer
curl --fail --silent --show-error --connect-timeout 5 --max-time 20 \
    http://127.0.0.1:7700/health >/dev/null

printf 'MEILISEARCH_VERSION=%s\n' "$meili_version" >> "$STATE_FILE"
stage_finish 40
log "Naechste Stufe: bash scripts/install_cd_wiki.sh web"

#!/usr/bin/env bash

set -Eeuo pipefail
umask 027

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
# shellcheck source=common.sh
source "${SCRIPT_DIR}/common.sh"

require_dispatcher
require_root
require_ubuntu_2404

[[ "$SCRIPT_DIR" == "${APP_DIR}/scripts/ubuntu24" ]] || \
    die "Das Projekt muss unter ${APP_DIR} liegen."
[[ -f "${APP_DIR}/manage.py" && -f "${APP_DIR}/static/editor/wiki-editor.js" ]] || \
    die "Projektdateien oder Editor-Bundle fehlen."
[[ ! -e "$STATE_DIR" ]] || die "Preflight wurde bereits angelegt: ${STATE_DIR}"

assert_ssh_access

memory_kib=$(awk '/MemTotal:/ {print $2}' /proc/meminfo)
swap_kib=$(awk '/SwapTotal:/ {print $2}' /proc/meminfo)
cpu_count=$(nproc)
disk_kib=$(df --output=avail / | tail -n 1 | tr -d ' ')

(( memory_kib >= 2097152 )) || die "Mindestens 2 GiB physischer RAM sind erforderlich."
(( memory_kib + swap_kib >= 4194304 )) || \
    die "RAM plus Swap muessen zusammen mindestens 4 GiB ergeben."
(( cpu_count >= 2 )) || die "Mindestens zwei CPU-Kerne sind erforderlich."
(( disk_kib >= 8388608 )) || die "Mindestens 8 GiB freier Speicher auf / sind erforderlich."

read -r -p "Wiki-Domain [wiki.only-space.de]: " DOMAIN
DOMAIN=${DOMAIN:-wiki.only-space.de}
[[ $DOMAIN =~ ^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$ && $DOMAIN == *.* ]] || \
    die "Ungueltige Domain."
getent ahosts "$DOMAIN" >/dev/null || die "Domain ist per DNS nicht aufloesbar: ${DOMAIN}"

install -d -o root -g root -m 0700 "$STATE_DIR"
cat > "$STATE_FILE" <<EOF
DOMAIN=${DOMAIN}
SOURCE_REVISION=$(if command -v git >/dev/null 2>&1; then git -C "$APP_DIR" rev-parse HEAD 2>/dev/null || printf 'manueller-upload'; else printf 'manueller-upload'; fi)
EOF
chmod 0600 "$STATE_FILE"
write_ssh_baseline
write_installer_baseline

{
    printf 'Preflight: %s\n' "$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
    printf 'RAM KiB: %s\nSwap KiB: %s\nCPUs: %s\nDisk frei KiB: %s\n' \
        "$memory_kib" "$swap_kib" "$cpu_count" "$disk_kib"
    systemctl status ssh.service --no-pager
    ss -ltnp
} > "${STATE_DIR}/preflight-report.txt"
chmod 0600 "${STATE_DIR}/preflight-report.txt"
mark_stage 00

log "Preflight erfolgreich. Es wurden keine Pakete, Dienste, Firewall- oder SSH-Einstellungen geaendert."
log "Naechste Stufe: bash scripts/install_cd_wiki.sh packages"

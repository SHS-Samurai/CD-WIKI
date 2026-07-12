#!/usr/bin/env bash

set -Eeuo pipefail
umask 027

readonly APP_DIR="/var/www/cd-wiki"
readonly SCRIPT_DIR="${APP_DIR}/scripts/ubuntu24"
readonly STATE_DIR="/var/lib/cd-wiki-installer"

die() {
    printf 'FEHLER: %s\n' "$*" >&2
    exit 1
}

usage() {
    cat <<'EOF'
CD-Wiki Installation - immer nur eine Stufe ausfuehren

Aufruf:
  bash scripts/install_cd_wiki.sh status
  bash scripts/install_cd_wiki.sh preflight
  bash scripts/install_cd_wiki.sh packages
  bash scripts/install_cd_wiki.sh database
  bash scripts/install_cd_wiki.sh application
  bash scripts/install_cd_wiki.sh search
  bash scripts/install_cd_wiki.sh web
  bash scripts/install_cd_wiki.sh verify

Es gibt absichtlich keinen all-Modus.
EOF
}

show_status() {
    local item stage label
    printf 'CD-Wiki Installationsstatus\n'
    for item in \
        '00:Preflight' '10:Pakete' '20:Datenbank' '30:Anwendung' \
        '40:Meilisearch' '50:Apache und TLS' '60:Abschlusspruefung'; do
        stage=${item%%:*}
        label=${item#*:}
        if [[ -f "${STATE_DIR}/stage-${stage}.done" ]]; then
            printf '  [OK]   %s\n' "$label"
        else
            printf '  [OFFEN] %s\n' "$label"
        fi
    done
}

confirm_stage() {
    local label=$1 expected=$2 answer
    printf '\nStufe: %s\n' "$label"
    printf 'SSH, Firewall, nftables, fail2ban und Netzwerkkonfiguration werden nicht geaendert.\n'
    read -r -p "Zum Start exakt ${expected} eingeben: " answer
    [[ $answer == "$expected" ]] || die "Stufe nicht bestaetigt. Keine Aenderung ausgefuehrt."
}

run_stage() {
    local script=$1
    export CD_WIKI_INSTALLER_DISPATCHED=1
    exec bash "${SCRIPT_DIR}/${script}"
}

main() {
    [[ "$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd -P)" == "$APP_DIR" ]] || \
        die "Das Projekt muss vollstaendig unter ${APP_DIR} liegen."
    [[ $# -eq 1 ]] || { usage; exit 2; }

    case "$1" in
        status)
            show_status
            ;;
        preflight)
            [[ ${EUID} -eq 0 ]] || die "Als root ausfuehren."
            python3 "${SCRIPT_DIR}/verify_access_safety.py"
            run_stage 00_preflight.sh
            ;;
        packages)
            [[ ${EUID} -eq 0 ]] || die "Als root ausfuehren."
            python3 "${SCRIPT_DIR}/verify_access_safety.py"
            confirm_stage "Ubuntu-Pakete" "PAKETE"
            run_stage 10_packages.sh
            ;;
        database)
            [[ ${EUID} -eq 0 ]] || die "Als root ausfuehren."
            python3 "${SCRIPT_DIR}/verify_access_safety.py"
            confirm_stage "Datenbank cd-wiki und Geheimnisse" "DATENBANK cd-wiki"
            run_stage 20_database.sh
            ;;
        application)
            [[ ${EUID} -eq 0 ]] || die "Als root ausfuehren."
            python3 "${SCRIPT_DIR}/verify_access_safety.py"
            confirm_stage "Django-Umgebung und Migrationen" "ANWENDUNG"
            run_stage 30_application.sh
            ;;
        search)
            [[ ${EUID} -eq 0 ]] || die "Als root ausfuehren."
            python3 "${SCRIPT_DIR}/verify_access_safety.py"
            confirm_stage "Meilisearch und Wartungstimer" "SUCHE"
            run_stage 40_meilisearch.sh
            ;;
        web)
            [[ ${EUID} -eq 0 ]] || die "Als root ausfuehren."
            python3 "${SCRIPT_DIR}/verify_access_safety.py"
            confirm_stage "Apache-Site und TLS-Zertifikat" "APACHE"
            run_stage 50_apache.sh
            ;;
        verify)
            [[ ${EUID} -eq 0 ]] || die "Als root ausfuehren."
            python3 "${SCRIPT_DIR}/verify_access_safety.py"
            run_stage 60_verify.sh
            ;;
        *)
            usage
            exit 2
            ;;
    esac
}

main "$@"

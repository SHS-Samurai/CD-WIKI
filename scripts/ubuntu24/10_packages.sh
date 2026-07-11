#!/usr/bin/env bash

set -Eeuo pipefail
umask 027

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
# shellcheck source=common.sh
source "${SCRIPT_DIR}/common.sh"

stage_start
require_stage 00
[[ ! -f "${STATE_DIR}/stage-10.done" ]] || die "Stufe 10 wurde bereits abgeschlossen."

log "Installiere ausschliesslich die benoetigten Pakete; kein apt upgrade."
export DEBIAN_FRONTEND=noninteractive
export NEEDRESTART_MODE=l
apt-get update
apt-get install -y --no-install-recommends \
    apache2 build-essential ca-certificates certbot curl default-libmysqlclient-dev \
    git libapache2-mod-wsgi-py3 mysql-server openssl pkg-config python3 python3-dev \
    python3-pip python3-venv

systemctl --quiet is-active mysql.service || systemctl start mysql.service
systemctl --quiet is-active apache2.service || systemctl start apache2.service
apache2ctl configtest

stage_finish 10
log "Naechste Stufe: bash scripts/ubuntu24/20_database.sh"

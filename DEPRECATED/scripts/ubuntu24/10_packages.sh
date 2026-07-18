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
apt-get -o Acquire::Retries=3 -o Acquire::http::Timeout=30 \
    -o Acquire::https::Timeout=30 update
apt-get -o Acquire::Retries=3 -o Acquire::http::Timeout=30 \
    -o Acquire::https::Timeout=30 install -y --no-install-recommends --no-upgrade \
    apache2 build-essential ca-certificates certbot curl default-libmysqlclient-dev \
    git libapache2-mod-wsgi-py3 mysql-server openssl pkg-config python3 python3-dev \
    python3-pip python3-venv

systemctl --quiet is-active mysql.service || timeout 60s systemctl start mysql.service
systemctl --quiet is-active apache2.service || timeout 60s systemctl start apache2.service
apache2ctl configtest

stage_finish 10
log "Naechste Stufe: bash scripts/install_cd_wiki.sh database"

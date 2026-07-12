#!/usr/bin/env bash

set -Eeuo pipefail
umask 027

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
# shellcheck source=common.sh
source "${SCRIPT_DIR}/common.sh"

stage_start
require_stage 40
[[ ! -f "${STATE_DIR}/stage-50.done" ]] || die "Stufe 50 wurde bereits abgeschlossen."
[[ ! -e "$APACHE_SITE" ]] || die "Apache-Site existiert bereits: ${APACHE_SITE}"
[[ ! -e "$APACHE_WSGI_CONF" ]] || die "Apache-Konfiguration existiert bereits: ${APACHE_WSGI_CONF}"

systemctl --quiet is-active apache2.service || die "Apache ist nicht aktiv."
apache2ctl configtest

read -r -p "E-Mail fuer Let's Encrypt: " letsencrypt_email
[[ $letsencrypt_email =~ ^[A-Za-z0-9_.+%-]+@[A-Za-z0-9.-]+$ ]] || \
    die "Ungueltige E-Mail-Adresse."

install -d -o root -g www-data -m 0750 /var/www/cd-wiki-acme/.well-known/acme-challenge
a2enmod rewrite ssl wsgi

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
assert_ssh_access
timeout 60s systemctl reload apache2.service
assert_ssh_access

timeout 300s certbot certonly --webroot -w /var/www/cd-wiki-acme \
    --non-interactive --agree-tos --no-eff-email \
    --email "$letsencrypt_email" -d "$DOMAIN"
[[ -f "/etc/letsencrypt/live/${DOMAIN}/fullchain.pem" ]] || die "TLS-Zertifikat fehlt."
[[ -f "/etc/letsencrypt/live/${DOMAIN}/privkey.pem" ]] || die "TLS-Schluessel fehlt."

cat > "$APACHE_WSGI_CONF" <<'EOF'
WSGIRestrictEmbedded On
EOF
a2enconf cd-wiki-wsgi

cp --preserve=mode,ownership,timestamps "$APACHE_SITE" "${STATE_DIR}/apache-http-only.conf"
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

    Alias /static/ ${STATIC_DIR}/
    <Directory ${STATIC_DIR}>
        Options -Indexes
        AllowOverride None
        Require all granted
    </Directory>

    WSGIDaemonProcess cd-wiki user=${APP_USER} group=${APP_GROUP} processes=1 threads=3 umask=0027 display-name=%{GROUP} home=${APP_DIR} request-timeout=120 graceful-timeout=15 python-home=${VENV_DIR} python-path=${APP_DIR}
    WSGIProcessGroup cd-wiki
    WSGIApplicationGroup %{GLOBAL}
    WSGIScriptAlias / ${APP_DIR}/config/wsgi.py

    <Directory ${APP_DIR}/config>
        Options -Indexes
        AllowOverride None
        <Files wsgi.py>
            Require all granted
        </Files>
    </Directory>

    LimitRequestBody 27262976
    ErrorLog \${APACHE_LOG_DIR}/cd-wiki-error.log
    CustomLog \${APACHE_LOG_DIR}/cd-wiki-access.log combined
</VirtualHost>
EOF

if ! apache2ctl configtest; then
    cp --preserve=mode,ownership,timestamps "${STATE_DIR}/apache-http-only.conf" "$APACHE_SITE"
    apache2ctl configtest
    timeout 60s systemctl reload apache2.service
    die "HTTPS-Konfiguration ungueltig; HTTP-Konfiguration wurde wiederhergestellt."
fi

install -d -o root -g root -m 0755 /etc/letsencrypt/renewal-hooks/deploy
cat > /etc/letsencrypt/renewal-hooks/deploy/cd-wiki-reload-apache <<'EOF'
#!/usr/bin/env sh
set -eu
apache2ctl configtest
systemctl reload apache2.service
EOF
chmod 0755 /etc/letsencrypt/renewal-hooks/deploy/cd-wiki-reload-apache

assert_ssh_access
timeout 60s systemctl reload apache2.service
timeout 60s systemctl enable --now certbot.timer
assert_ssh_access
curl --fail --silent --show-error --connect-timeout 10 --max-time 30 \
    --resolve "${DOMAIN}:443:127.0.0.1" "https://${DOMAIN}/" >/dev/null

stage_finish 50
log "Naechste Stufe: bash scripts/install_cd_wiki.sh verify"

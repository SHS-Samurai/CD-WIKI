# Deployment

Zielsystem: Ubuntu-VPS mit Apache und mod_wsgi, MySQL und lokalem
Meilisearch-Dienst. Gunicorn und uWSGI werden nicht eingesetzt.

Die reproduzierbare Erstinstallation fuer einen frischen Ubuntu-24.04-VPS ist
in `docs/SERVER_INSTALLATION.md` beschrieben. Sie besteht aus getrennten,
kontrollierten Stufen unter `scripts/ubuntu24/`. Keine dieser Stufen aendert
SSH, Firewall, Netzwerk- oder Resolverkonfiguration.

## Dienste

- Apache auf Port 80/443
- Django im getrennten Apache-mod_wsgi-Daemon unter dem Benutzer `cdwiki`
- MySQL lokal, Standardport `3306`
- Meilisearch lokal, Standardport `7700`

Es gibt keinen Anwendungsport und keinen HTTP-Reverse-Proxy. Apache bindet die
Django-WSGI-Anwendung direkt ein. `DJANGO_TRUST_X_FORWARDED_PROTO` bleibt daher
deaktiviert. MySQL und Meilisearch sind ausschliesslich auf Loopback gebunden.

Produktiv mindestens setzen:

```text
DJANGO_ENVIRONMENT=production
DJANGO_DEBUG=False
DJANGO_ALLOWED_HOSTS=wiki.only-space.de
DJANGO_CSRF_TRUSTED_ORIGINS=https://wiki.only-space.de
DJANGO_TRUST_X_FORWARDED_PROTO=False
DJANGO_SECURE_HSTS_PRELOAD=False
DJANGO_SECRET_KEY=<langer zufaelliger Wert>
MEILISEARCH_MASTER_KEY=<separater zufaelliger Wert>
DJANGO_EMAIL_BACKEND=django.core.mail.backends.smtp.EmailBackend
DJANGO_EMAIL_HOST=<SMTP-Server>
DJANGO_EMAIL_PORT=587
DJANGO_EMAIL_HOST_USER=<SMTP-Benutzer>
DJANGO_EMAIL_HOST_PASSWORD_B64=<Base64-kodiertes SMTP-Passwort>
DJANGO_EMAIL_USE_TLS=True
DJANGO_EMAIL_USE_SSL=False
DJANGO_DEFAULT_FROM_EMAIL=<Absenderadresse>
```

Vor dem Start `python manage.py check --deploy` gegen diese Umgebung ausfuehren.
HSTS-Preload und `includeSubDomains` erst aktivieren, nachdem HTTPS und die
automatische Zertifikatserneuerung stabil getestet wurden. Alle betroffenen
Unterdomains muessen dann HTTPS unterstuetzen.

## Theme und statische Dateien

Die Migrationen enthalten die globale Tabelle `theme_themesettings`. Vor dem
ersten Start nach einem Update daher zuerst die Datenbank sichern und dann
`python manage.py migrate` ausfuehren. Ist noch keine Theme-Konfiguration
gespeichert, liefert das Wiki weiterhin die statischen Standardwerte.

Der Theme-Endpunkt `/theme/active.css` ist oeffentlich lesbar, enthaelt aber
ausschliesslich validierte CSS-Variablen und keine vertraulichen Daten. Die
letzte Aenderungszeit ist Teil der Stylesheet-URL, damit Theme-Aenderungen
sofort sichtbar werden. Apache uebergibt diesen Django-Pfad wie die uebrigen
Anwendungspfade direkt an mod_wsgi.

## Suche

Nach Deployment, Migrationen oder groesseren Datenuebernahmen den Suchindex neu
aufbauen:

```bash
python manage.py reindex_search
```

Meilisearch darf nicht direkt oeffentlich erreichbar sein; die Websuche laeuft
ueber Django und filtert Ergebnisse nach Web-Rechten.

## Pfade

- Projektverzeichnis: `/var/www/cd-wiki`, nicht direkt durch Apache ausliefern
- Virtualenv: `/var/www/cd-wiki/.venv`
- Storage: ausserhalb direkt oeffentlicher Webpfade
- Logs: projektbezogen und nicht oeffentlich

## Statische Dateien

Bei Entwicklungs- oder Release-Builds vor `collectstatic` den Tiptap-Editor
bauen. Node.js wird nicht als Dienst betrieben. Der Serverinstaller verwendet
das bereits versionierte Bundle `static/editor/wiki-editor.js` und installiert
auf dem VPS kein Node.js.

```bash
cd frontend/editor
npm ci
npm run build
cd ../..
python manage.py collectstatic --noinput
```

`static/css/wiki.css` bindet die Teilbereiche fuer Tokens, Grundgestaltung,
Layout, Komponenten und Editor ein. Die erzeugten statischen Dateien duerfen
nicht durch ein zweites CSS-Framework oder serverseitige Rewrite-Regeln
ueberschrieben werden.

## Wartung

Abgelaufene Rate-Limit-Zaehler taeglich entfernen:

```bash
python manage.py prune_rate_limits
```

## Rollback

- Vor Migrationen Datenbank sichern.
- Vor Deployment aktuellen Stand taggen oder Commit notieren.
- Bei Fehlern nicht weiter installieren, Diagnose sichern und den dokumentierten
  Provider-Snapshot als vollstaendigen Rueckrollpunkt verwenden.

Konkrete Serveraenderungen werden erst nach separater Planung ausgefuehrt.

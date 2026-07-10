# Deployment

Zielsystem: Ubuntu-VPS mit Apache Reverse Proxy, Gunicorn oder uWSGI, MySQL und
lokalem Meilisearch-Dienst.

## Dienste

- Apache auf Port 80/443
- Gunicorn oder uWSGI lokal, z. B. auf `127.0.0.1:8000`
- MySQL lokal, Standardport `3306`
- Meilisearch lokal, Standardport `7700`

Gunicorn oder uWSGI nur an eine lokale Adresse binden. Die direkte
Proxy-Adresse muss in `WIKI_TRUSTED_PROXY_IPS` stehen; bei lokalem Apache sind
das normalerweise `127.0.0.1` und `::1`.

Produktiv mindestens setzen:

```text
DJANGO_ENVIRONMENT=production
DJANGO_DEBUG=False
DJANGO_ALLOWED_HOSTS=wiki.only-space.de
DJANGO_CSRF_TRUSTED_ORIGINS=https://wiki.only-space.de
DJANGO_TRUST_X_FORWARDED_PROTO=True
DJANGO_SECURE_HSTS_PRELOAD=True
DJANGO_SECRET_KEY=<langer zufaelliger Wert>
MEILISEARCH_MASTER_KEY=<separater zufaelliger Wert>
DJANGO_EMAIL_BACKEND=django.core.mail.backends.smtp.EmailBackend
```

Vor dem Start `python manage.py check --deploy` gegen diese Umgebung ausfuehren.
Der HSTS-Preload-Header gilt zusammen mit `includeSubDomains`; alle verwendeten
Unterdomains von `wiki.only-space.de` muessen daher HTTPS unterstuetzen.

## Theme und statische Dateien

Die Migrationen enthalten die globale Tabelle `theme_themesettings`. Vor dem
ersten Start nach einem Update daher zuerst die Datenbank sichern und dann
`python manage.py migrate` ausfuehren. Ist noch keine Theme-Konfiguration
gespeichert, liefert das Wiki weiterhin die statischen Standardwerte.

Der Theme-Endpunkt `/theme/active.css` ist oeffentlich lesbar, enthaelt aber
ausschliesslich validierte CSS-Variablen und keine vertraulichen Daten. Die
letzte Aenderungszeit ist Teil der Stylesheet-URL, damit Theme-Aenderungen
sofort sichtbar werden. Apache muss diesen Django-Pfad deshalb wie die uebrigen
Anwendungspfade an Gunicorn oder uWSGI weiterleiten.

## Suche

Nach Deployment, Migrationen oder groesseren Datenuebernahmen den Suchindex neu
aufbauen:

```bash
python manage.py reindex_search
```

Meilisearch darf nicht direkt oeffentlich erreichbar sein; die Websuche laeuft
ueber Django und filtert Ergebnisse nach Web-Rechten.

## Pfade

- Projektverzeichnis: nach Serverkonzept festlegen
- Virtualenv: im Projekt oder unter `/opt`
- Storage: ausserhalb direkt oeffentlicher Webpfade
- Logs: projektbezogen und nicht oeffentlich

## Statische Dateien

Vor `collectstatic` den Tiptap-Editor bauen. Node.js wird nicht als Dienst
betrieben.

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
- Bei Fehlern Code auf vorherigen Commit zuruecksetzen und Dienst neu starten.

Konkrete Serveraenderungen werden erst nach separater Planung ausgefuehrt.

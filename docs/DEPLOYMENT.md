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

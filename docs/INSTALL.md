# Installation

## Voraussetzungen

- Python 3.13
- Node.js fuer den Editor-Build
- MySQL
- Meilisearch
- Git

## MySQL

Beispiel fuer eine lokale Entwicklungsdatenbank:

```sql
CREATE DATABASE `cd-wiki` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'wiki'@'localhost' IDENTIFIED BY 'change-me';
CREATE USER 'wiki'@'127.0.0.1' IDENTIFIED BY 'change-me';
GRANT ALL PRIVILEGES ON `cd-wiki`.* TO 'wiki'@'localhost';
GRANT ALL PRIVILEGES ON `cd-wiki`.* TO 'wiki'@'127.0.0.1';
GRANT ALL PRIVILEGES ON `test_wiki`.* TO 'wiki'@'localhost';
GRANT ALL PRIVILEGES ON `test_wiki`.* TO 'wiki'@'127.0.0.1';
FLUSH PRIVILEGES;
```

Die Werte muessen in `.env` eingetragen werden.

Lokal bleibt `DJANGO_ENVIRONMENT=development`. Auf dem Zielserver muss der Wert
`production` sein; die Anwendung verweigert dort unsichere Fallback-Werte.

## Python

```powershell
python -m venv .venv
.\.venv\Scripts\Activate.ps1
python -m pip install --upgrade pip
python -m pip install -r requirements.txt
```

## Editor

Node.js wird nur zum Erzeugen des statischen Tiptap-Bundles verwendet.

```powershell
Set-Location frontend\editor
npm.cmd install
npm.cmd run build
Set-Location ..\..
```

## Django

```powershell
python manage.py check
python manage.py migrate
python manage.py createsuperuser
python manage.py runserver
```

## Registrierung

Der Startmodus kommt aus `WIKI_REGISTRATION_MODE`. Nach der ersten Nutzung kann
der Modus im Adminbereich unter Registrierung geaendert werden.

Moegliche Werte:

- `disabled`
- `admin_approval`
- `email_confirmation`
- `automatic`

`WIKI_REGISTRATION_MIN_SECONDS` steuert die Mindestzeit fuer das Formular.
`WIKI_EMAIL_CONFIRMATION_HOURS` steuert die Gueltigkeit von Bestaetigungslinks.

## Rate-Limits

Login und Registrierung speichern Zaehler in MySQL. Grenzwerte und Zeitfenster
werden ueber die Variablen `WIKI_LOGIN_RATE_*` und
`WIKI_REGISTRATION_RATE_*` gesetzt. `WIKI_TRUSTED_PROXY_IPS` enthaelt nur die
direkten Reverse Proxies, deren `X-Forwarded-For`-Header vertraut werden darf.

Alte Zaehler koennen regelmaessig entfernt werden:

```powershell
python manage.py prune_rate_limits
```

## Suche

Meilisearch muss lokal erreichbar sein, bevor der Index neu aufgebaut wird.

```powershell
python manage.py reindex_search
```

Die Verbindung wird ueber `MEILISEARCH_URL`, `MEILISEARCH_MASTER_KEY` und
`WIKI_SEARCH_INDEX_NAME` in `.env` gesteuert.

# CD-Wiki

CD-Wiki ist ein professionelles, webbasiertes Wiki auf Basis von Laravel 13, Blade, Tailwind CSS und Tiptap. Der Schwerpunkt liegt auf fein abgestuften Web-Rechten, sicherer Bearbeitung und einem einfachen Betrieb auf einem Standard-LAMP-Server.

## Funktionen

- getrennte Webs als Namensräume und Berechtigungsgrenzen
- Rechte für Öffentlichkeit, angemeldete Benutzer, Gruppen und einzelne Benutzer
- Aktionen: Lesen, Erstellen, Bearbeiten, Kommentieren, Hochladen, Verwalten und Löschen
- Tiptap-WYSIWYG-Editor mit Markdown-Speicherung und Wiki-Links
- Artikel- und Anhangsversionierung, Vergleiche und Wiederherstellung
- MySQL-Volltextsuche in Artikeln und extrahierten Anhangtexten
- Kategorien, Kommentare, Papierkorb und unveränderliches Auditlog
- administrativ bearbeitbares Layout und Theme
- geschützter Erstinstallationsassistent sowie geprüfte Backup-Werkzeuge

## Verzeichnisstruktur

| Pfad | Inhalt |
|---|---|
| [`wiki/`](wiki/) | aktive Laravel-Anwendung |
| [`DEPRECATED/`](DEPRECATED/) | unveränderte Django-Referenz des Altsystems |
| [`ANALYSE.md`](ANALYSE.md) | Analyse des früheren Systems |
| [`DEPLOY.md`](DEPLOY.md) | Produktions-, Backup- und Freigabeanleitung |
| [`MIGRATION.md`](MIGRATION.md) | Status vorhandener Bestandsdaten |

## Ersteinrichtung

1. Abhängigkeiten im Verzeichnis `wiki/` mit Composer und npm installieren.
2. `.env.example` nach `.env` kopieren und `php artisan key:generate` ausführen.
3. Den Webserver-DocumentRoot auf `wiki/public` setzen.
4. Bei entferntem Zugriff einmal `php artisan wiki:installation-token` ausführen.
5. Das Wiki im Browser öffnen und den Installationsassistenten abschließen.

Der Assistent verwendet ausschließlich den lokalen MySQL-Server unter `127.0.0.1:3306`, erstellt die angegebene Datenbank und alle Tabellen und legt das erste Administratorkonto an. Der eingegebene MySQL-Benutzer benötigt die dafür erforderlichen Rechte. Zugangsdaten werden nicht im Repository gespeichert.

Ausführliche Hinweise stehen in [DEPLOY.md](DEPLOY.md) und [wiki/README.md](wiki/README.md).

## Qualitätssicherung

```bash
cd wiki
php artisan test
php vendor/bin/pint --test
npm run build
composer audit --locked
npm audit
```

MySQL-spezifische Integrations- und Parallelitätstests werden ausschließlich gegen eine separate Datenbank mit dem Suffix `_test` ausgeführt; siehe [DEPLOY.md](DEPLOY.md).

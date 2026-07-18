# CD-Wiki – Laravel-Anwendung

Die aktive Wiki-Anwendung basiert auf Laravel 13, Laravel Breeze mit Blade, Tailwind CSS, Tiptap und `league/commonmark`.

## Voraussetzungen

- PHP 8.3 oder neuer mit PDO-MySQL, Mbstring, Fileinfo, DOM/XML, OpenSSL, Zlib und Phar
- lokaler MySQL- oder MariaDB-Server unter `127.0.0.1:3306`
- Composer
- Node.js und npm für den Frontend-Build
- Apache mit DocumentRoot auf `public/`

## Vorbereitung

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
cp .env.example .env
php artisan key:generate
```

Bei einer entfernten Ersteinrichtung wird einmalig ein Zugriffstoken benötigt:

```bash
php artisan wiki:installation-token
```

Danach das Wiki im Browser öffnen. Der Assistent fragt Datenbankname, MySQL-Benutzer und MySQL-Passwort sowie Name, E-Mail und Passwort des ersten Wiki-Administrators ab. Host `127.0.0.1` und Port `3306` sind fest voreingestellt. Die Routine erstellt die Datenbank, führt alle Migrationen aus, speichert die Verbindung in `.env` und sperrt den Installer nach erfolgreichem Abschluss.

Der MySQL-Benutzer benötigt Rechte zum Erstellen der Datenbank und zum Verwalten ihrer Tabellen. Bestehende fremde Datenbanken oder Datenbanken mit vorhandenen Benutzern werden nicht überschrieben.

## Entwicklung und Prüfung

```bash
php artisan test
php vendor/bin/pint --test
npm run build
php artisan wiki:doctor
```

Produktions-, MySQL-, Backup-, Restore- und Lasttest-Anweisungen stehen in [`../DEPLOY.md`](../DEPLOY.md). Der frühere Django-Code liegt ausschließlich als unveränderte Referenz unter [`../DEPRECATED/`](../DEPRECATED/).

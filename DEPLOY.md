# CD-Wiki – Produktion, Prüfung und Wiederherstellung

## Servervoraussetzungen

- PHP 8.3 oder neuer: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd`, `mbstring`, `openssl`, `pdo_mysql`, `xml`, `zlib`, `Phar`
- MySQL 8.0+ oder eine kompatible, unterstützte MariaDB-Version
- Apache mit `mod_rewrite`; DocumentRoot zeigt ausschließlich auf `wiki/public`
- `mysqldump` für konsistente Sicherungen

## Ersteinrichtung

Bei einer lokalen Installation über `127.0.0.1` oder `::1` kann die Ersteinrichtung direkt geöffnet werden. Vor einer entfernten Ersteinrichtung muss einmalig ein Zugriffstoken erzeugt werden:

```bash
cd /pfad/cd-wiki/wiki
php artisan wiki:installation-token
```

Das Token wird in der Installationsmaske eingegeben und nach erfolgreichem Abschluss automatisch aus `.env` entfernt. Die Routine verbindet sich ausschließlich mit `127.0.0.1:3306` und legt die angegebene Datenbank selbst an. Der eingegebene MySQL-Benutzer benötigt dafür das `CREATE`-Recht sowie Rechte zum Anlegen und Ändern der Wiki-Tabellen. Existiert die Datenbank bereits, muss sie leer sein oder ausschließlich Tabellen eines zuvor fehlgeschlagenen CD-Wiki-Installationsversuchs ohne Benutzer enthalten.

## Bereitstellung

```bash
cd /pfad/cd-wiki/wiki
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize
php artisan wiki:doctor
```

In `.env`: `APP_ENV=production`, `APP_DEBUG=false`, eine HTTPS-`APP_URL`, `LOG_LEVEL=warning`, `SESSION_ENCRYPT=true` und `SESSION_SECURE_COOKIE=true`. `storage/` und `bootstrap/cache/` dürfen nur für den Webserver-Benutzer schreibbar sein. Registrierung ist nach der Installation standardmäßig geschlossen und kann unter **Verwaltung → Einstellungen** auf Freigabe oder offen gestellt werden.

## Apache-Beispiel

```apache
<VirtualHost *:443>
    ServerName wiki.example.org
    DocumentRoot /var/www/cd-wiki/wiki/public
    <Directory /var/www/cd-wiki/wiki/public>
        AllowOverride All
        Require all granted
        Options -Indexes
    </Directory>
</VirtualHost>
```

TLS-Zertifikat und HTTPS-Weiterleitung werden vom Serveradministrator eingerichtet. Anwendungsschlüssel, `.env`, `storage/app/private` und Backups dürfen nie öffentlich ausgeliefert werden.

## Backup und Restore

Täglich außerhalb des DocumentRoot sichern und danach prüfen:

```bash
php artisan wiki:backup --path=/sicherungen/cd-wiki/$(date +%F-%H%M)
php artisan wiki:backup:verify /sicherungen/cd-wiki/2026-07-17-0200
```

Während Datenbankdump und Dateiarchiv erstellt werden, blockiert das Backup neue schreibende Wiki-Anfragen und wartet auf bereits laufende Schreibvorgänge. Die Prüfung kontrolliert Prüfsummen, SQL-Format und TAR-Lesbarkeit.

Wiederherstellung immer zuerst in einer leeren Testdatenbank proben: Wartungsmodus aktivieren, `database.sql` mit dem MySQL-Client importieren, `files.tar` nach `storage/app/` entpacken, `php artisan optimize:clear`, `php artisan wiki:doctor` und Stichproben aus privaten Webs, Anhängen, Revisionen und Auditlog ausführen. Ein Restore überschreibt Daten und wird deshalb bewusst nicht automatisch vom Wiki ausgeführt.

## Freigabetests

Der normale Testlauf verwendet SQLite und ist nicht ausreichend für die Produktionsfreigabe. Für eine eigens angelegte, leere Datenbank mit Suffix `_test` unter Windows:

```powershell
$env:ALLOW_WIKI_TEST_DB_RESET='YES'
.\scripts\test-mysql.ps1 -Database cd_wiki_test -Username test_user -Password '...'
```

Der Test löscht ausschließlich Tabellen in dieser `_test`-Datenbank. Lasttest gegen eine Staging-Installation: `php scripts/load-test.php https://staging.example.org/ 1000 20`; danach Logs, P95-Latenz, Fehlerquote und Datenbanklast prüfen.

## Betrieb

- `php artisan wiki:doctor` nach jedem Deployment und täglich per Monitoring ausführen.
- Login-, Freigabe-, Benutzer-, Rechte-, Inhalts-, Kommentar-, Datei-, Theme- und Papierkorb-Aktionen im unveränderlichen Auditlog kontrollieren.
- Backups verschlüsselt, getrennt vom Server und mit getesteter Aufbewahrungsfrist speichern.
- Sicherheitsupdates für PHP, MySQL, Composer- und npm-Abhängigkeiten zeitnah in Staging testen.

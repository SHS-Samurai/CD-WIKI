# Serverinstallation auf Ubuntu 24.04

Das Skript `scripts/install_ubuntu_24_04.sh` installiert das Wiki auf einem
frischen Ubuntu-24.04-LTS-VPS. Es ist fuer die Erstinstallation gedacht, nicht
fuer Updates oder fuer die Uebernahme einer vorhandenen Installation.

## Was installiert wird

Betroffene Dienste:

- Apache auf den oeffentlichen Ports `80` und `443`
- Django als getrennter Apache-mod_wsgi-Daemon ohne eigenen Netzwerkport
- MySQL auf dem lokalen Standardport `3306`
- Meilisearch als eigener Dienst auf `127.0.0.1:7700`
- `cd-wiki-maintenance.timer` fuer die taegliche Rate-Limit-Bereinigung

Betroffene Pfade:

- Anwendung und Git-Repository: `/var/www/cd-wiki`
- Python-Umgebung: `/var/www/cd-wiki/.venv`
- Geheimnisse: `/etc/cd-wiki/wiki.env`
- Wiki-Storage: `/var/lib/cd-wiki/storage`
- statische Dateien: `/var/lib/cd-wiki/static`
- Meilisearch-Daten: `/var/lib/meilisearch`
- Logs: systemd-Journal und `/var/log/apache2/cd-wiki-*.log`
- lokale Sicherungen vor Migrationen: `/var/backups/cd-wiki`

Das Skript richtet keine Firewall ein. So kann eine abweichende SSH-Konfiguration
nicht versehentlich ausgesperrt werden. MySQL und Meilisearch werden nur lokal
gebunden und duerfen nicht durch Provider-Firewalls freigegeben werden.
Der Meilisearch-Dienst wird per systemd auf maximal 50 Prozent des physischen
Speichers begrenzt. Der Apache-mod_wsgi-Daemon startet mit einem Prozess, fuenf
Threads und einem Zeitlimit fuer blockierte Anfragen.
Fuer MySQL werden `bind-address` und `mysqlx-bind-address` explizit auf
`127.0.0.1` gesetzt; `local-infile` wird deaktiviert.

## Voraussetzungen

Vor dem Start muessen folgende Punkte erfuellt sein:

1. Der VPS verwendet ein aktuelles Ubuntu 24.04 LTS.
2. `wiki.only-space.de` zeigt per A- und gegebenenfalls AAAA-Record auf den VPS.
3. Port 80 und 443 sind von aussen erreichbar.
4. Fuer optionalen Mailversand ist ein ausgehender SMTP-Zugang bekannt.
5. Beim Provider existiert ein aktueller VPS-Snapshot als Rueckrollpunkt.
6. Das vollstaendige Projekt liegt unter `/var/www/cd-wiki`.

Der Installer setzt voraus, dass auf dem frischen System noch keine Installation
unter den Laufzeitpfaden, keine gleichnamige Datenbank und keine gleichnamigen
systemd-Dienste existieren. Das Projekt selbst muss bereits vollstaendig unter
`/var/www/cd-wiki` liegen. Ein Git-Repository, lokale Aenderungen und ein
manueller Upload ohne `.git` werden unterstuetzt. Vorhandene Git-Informationen
werden zur Nachvollziehbarkeit protokolliert, blockieren die Installation aber
nicht. Der Installer ueberschreibt keine bestehende Installation.
Gleichnamige Systembenutzer sowie vorhandene Meilisearch-, Apache- oder
MySQL-Konfigurationen werden ebenfalls nicht ersetzt.

## 1. Projekt laden

```bash
sudo apt-get update
sudo apt-get install -y git
sudo git clone https://github.com/SHS-Samurai/CD-WIKI.git /var/www/cd-wiki
sudo chown -R "$USER":"$USER" /var/www/cd-wiki
cd /var/www/cd-wiki
git status --short
git rev-parse HEAD
```

`git status --short` zeigt gegebenenfalls lokale oder hochgeladene Aenderungen.
Diese werden vom Installer nicht verworfen.

Wenn die Dateien bereits in `/var/www/cd-wiki` liegen, nicht erneut klonen.
Der Installer kann vollstaendig hochgeladene Projektdateien auch ohne das
Verzeichnis `.git` installieren. Git wird fuer spaetere, nachvollziehbare Updates
dennoch empfohlen.

Mitkopierte lokale Laufzeit- oder Build-Verzeichnisse wie `.venv`, `.env`,
`staticfiles` und `frontend/editor/node_modules` werden vor der Installation
nicht geloescht. Der Installer verschiebt sie automatisch in ein zeitgestempeltes,
nur fuer root lesbares Verzeichnis unter `/var/backups` und erstellt die fuer
Ubuntu benoetigten Dateien anschliessend neu.

Obwohl die Anwendung unter `/var/www` liegt, wird dieses Verzeichnis nicht als
Apache-`DocumentRoot` freigegeben. Apache liefert nur die getrennt erzeugten
statischen Dateien aus und fuehrt Django in einem getrennten mod_wsgi-Daemon
unter dem unprivilegierten Benutzer `cdwiki` aus.

## 2. Installation starten

```bash
cd /var/www/cd-wiki
sudo bash scripts/install_ubuntu_24_04.sh
```

Das ist der einzige Installationsbefehl. Das Skript fragt nacheinander Domain,
Administrator und Let's-Encrypt-E-Mail ab. SMTP-Mailversand ist optional und
standardmaessig deaktiviert. Passwoerter werden im
Terminal nicht angezeigt und muessen zur Bestaetigung zweimal eingegeben werden.
Bei aktivierter SMTP-Einrichtung wird `starttls` auf Port 587 oder `ssl` auf
Port 465 angeboten. Vor den Wiki- und Datenbankarbeiten prueft das Skript dann
die verschluesselte SMTP-Anmeldung, versendet dabei aber keine Nachricht. Vor
Beginn muss die angezeigte Zusammenfassung mit `ja` bestaetigt werden.

Ohne SMTP bleibt die Registrierung standardmaessig deaktiviert und das Wiki ist
voll nutzbar. E-Mail-Bestaetigung darf erst aktiviert werden, nachdem in
`/etc/cd-wiki/wiki.env` ein funktionsfaehiges SMTP-Backend konfiguriert wurde.

Meilisearch wird ohne weitere Eingabe installiert: Der Installer liest die
aktuelle stabile Version aus der oeffentlichen GitHub-Release-API, waehlt das
Binary passend zu `amd64` oder `arm64` und verifiziert vor der Installation den
von GitHub gelieferten SHA-256-Digest.

Das Skript fuehrt in dieser Reihenfolge aus:

1. Betriebssystem, Projektdateien, vorhandene Installation und Ports pruefen.
2. Ubuntu-Pakete installieren, vorhandenes Django erkennen und MySQL sowie Apache starten.
3. bei aktivem Mailversand die verschluesselte SMTP-Anmeldung pruefen.
4. Per Certbot und ACME-Webroot ein TLS-Zertifikat beziehen.
5. getrennte Systembenutzer und geschuetzte Verzeichnisse erstellen.
6. aktuelle stabile Meilisearch-Version ermitteln, herunterladen und SHA-256 pruefen.
7. Datenbank, zufaellige Anwendungsschluessel und geschuetzte Env-Dateien anlegen.
8. isolierte Python-Umgebung anlegen, Django 5.2.x pruefen, Migrationen,
   statische Dateien und ersten Administrator anlegen.
9. gehaerteten Meilisearch-Dienst, Wartungstimer und Apache mod_wsgi aktivieren.
10. Django-Deployment-Check, Suche, Dienste und HTTPS mit Zeitlimits testen.

Das Editor-Bundle liegt versioniert in `static/editor/wiki-editor.js`. Auf dem
Produktivserver wird daher weder Node.js installiert noch ein Frontend-Build
ausgefuehrt.

Eine eventuell systemweit installierte Django-Version wird nur gemeldet und
nicht verwendet. Fuer den Wiki-Dienst gilt ausschliesslich die durch
`requirements.txt` festgelegte Version in `/var/www/cd-wiki/.venv`.

Es wird keine temporaere Konfigurationsdatei benoetigt. Dauerhafte, automatisch
erzeugte Geheimnisse speichert das Skript mit restriktiven Rechten unter
`/etc/cd-wiki/wiki.env`. Das eingegebene Administrator-Passwort wird nur zur
Kontoerstellung verwendet und nicht dort gespeichert.

## 3. Installation pruefen

Der komplette technische Test kann erneut ausgefuehrt werden:

```bash
sudo bash scripts/install_ubuntu_24_04.sh --check
```

Der Check liest nur die bereits geschuetzte Serverkonfiguration. Einzelne
Betriebspruefungen:

```bash
sudo systemctl status meilisearch mysql apache2
sudo journalctl -u apache2 -u meilisearch --since today
sudo -u cdwiki /var/www/cd-wiki/.venv/bin/python /var/www/cd-wiki/manage.py check --deploy
curl -I https://wiki.only-space.de/
```

Danach im Browser anmelden und mindestens Login, Logout, Rechte, privates Web,
Topic-Erstellung, Revision, Attachment-Download, Kommentar und Suche testen.
Das bei der Installation gesetzte Admin-Passwort anschliessend aendern.

## HSTS

Die Erstinstallation beginnt absichtlich mit einem Tag HSTS und ohne
`includeSubDomains` oder Preload. Erst nachdem HTTPS und Zertifikatserneuerung
mehrere Tage sicher funktionieren und alle betroffenen Unterdomains HTTPS
unterstuetzen, koennen in `/etc/cd-wiki/wiki.env` folgende Werte gesetzt werden:

```text
DJANGO_SECURE_HSTS_SECONDS=31536000
DJANGO_SECURE_HSTS_INCLUDE_SUBDOMAINS=True
DJANGO_SECURE_HSTS_PRELOAD=True
```

Danach `sudo systemctl restart cd-wiki` ausfuehren. Ein voreiliger Preload-Eintrag
laesst sich nicht kurzfristig rueckgaengig machen.

## Sicherung

Vor der Freigabe muss eine externe, verschluesselte Sicherung eingerichtet und
eine Wiederherstellung getestet werden. Zu sichern sind gemeinsam:

- MySQL-Datenbank `cd-wiki`
- `/var/lib/cd-wiki/storage`
- `/etc/cd-wiki/wiki.env`

Der Meilisearch-Index ist abgeleitet und kann mit `reindex_search` neu aufgebaut
werden. Lokale Dateien unter `/var/backups/cd-wiki` ersetzen keine externe
Sicherung.

## Rueckrollmoeglichkeit

Bei einem Fehler vor der Fertigmeldung bleibt die Markierungsdatei
`/etc/cd-wiki/installed` aus. Nicht blind erneut starten: zuerst Journal und
letzte Skriptausgabe sichern. Der sicherste Rollback einer Erstinstallation ist
das Wiederherstellen des vorher erstellten VPS-Snapshots.

Ohne Snapshot die neuen Dienste stoppen und deaktivieren, die installierten
Daten unter `/var/lib/cd-wiki` sowie `/etc/cd-wiki` erhalten und erst nach einer
Sicherung kontrolliert bereinigen. Datenbank oder Storage niemals als ersten
Fehlerbehebungsschritt loeschen.

## Updates

Das Erstinstallationsskript ist kein Update-Werkzeug. Updates benoetigen eine
separate Sicherung, einen dokumentierten Commit, Migrationen, `collectstatic`,
`reindex_search`, Dienstneustart und einen eigenen Rueckrollplan.

# Serverinstallation auf Ubuntu 24.04

Das Skript `scripts/install_ubuntu_24_04.sh` installiert das Wiki auf einem
frischen Ubuntu-24.04-LTS-VPS. Es ist fuer die Erstinstallation gedacht, nicht
fuer Updates oder fuer die Uebernahme einer vorhandenen Installation.

## Was installiert wird

Betroffene Dienste:

- Apache auf den oeffentlichen Ports `80` und `443`
- Gunicorn als `cd-wiki.service` auf `127.0.0.1:8000`
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
nicht versehentlich ausgesperrt werden. MySQL, Meilisearch und Gunicorn werden
nur lokal gebunden und duerfen nicht durch Provider-Firewalls freigegeben werden.
Fuer MySQL werden `bind-address` und `mysqlx-bind-address` explizit auf
`127.0.0.1` gesetzt; `local-infile` wird deaktiviert.

## Voraussetzungen

Vor dem Start muessen folgende Punkte erfuellt sein:

1. Der VPS verwendet ein aktuelles Ubuntu 24.04 LTS.
2. `wiki.only-space.de` zeigt per A- und gegebenenfalls AAAA-Record auf den VPS.
3. Port 80 und 443 sind von aussen erreichbar.
4. Der ausgehende SMTP-Zugang ist bekannt und vom VPS erreichbar.
5. Beim Provider existiert ein aktueller VPS-Snapshot als Rueckrollpunkt.
6. Das Repository ist sauber und auf den gewuenschten Commit ausgecheckt.

Der Installer setzt voraus, dass auf dem frischen System noch keine Installation
unter den Laufzeitpfaden, keine gleichnamige Datenbank und keine gleichnamigen
systemd-Dienste existieren. Das Projekt selbst muss bereits vollstaendig und als
sauberes Git-Repository unter `/var/www/cd-wiki` liegen. Der Installer bricht
bei Abweichungen ab und ueberschreibt keine bestehende Installation.
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

`git status --short` darf nichts ausgeben. Den vollstaendigen Wert von
`git rev-parse HEAD` spaeter als `EXPECTED_GIT_COMMIT` eintragen.

Wenn die Dateien bereits in `/var/www/cd-wiki` liegen, nicht erneut klonen.
Stattdessen dort `git status --short` und `git rev-parse HEAD` ausfuehren. Ohne
das Verzeichnis `.git` ist keine verifizierte Installation moeglich; in diesem
Fall das Repository frisch in den vorgesehenen Pfad klonen.

Obwohl die Anwendung unter `/var/www` liegt, wird dieses Verzeichnis nicht als
Apache-`DocumentRoot` freigegeben. Apache liefert nur die getrennt erzeugten
statischen Dateien aus und leitet Anwendungsaufrufe an Gunicorn weiter.

## 2. Meilisearch-Version pruefen

Auf der offiziellen Meilisearch-Release-Seite eine aktuelle stabile Version
waehlen. Zum Server passend wird das Asset `meilisearch-linux-amd64` oder
`meilisearch-linux-aarch64` verwendet. Versionsnummer und vom Release
veroeffentlichten SHA-256-Digest in die Installationskonfiguration uebernehmen.
Das Skript installiert kein Binary, dessen Pruefsumme abweicht.

- Releases: <https://github.com/meilisearch/meilisearch/releases>
- Installationshinweise: <https://www.meilisearch.com/docs/resources/self_hosting/getting_started/install_locally>

## 3. Installationskonfiguration anlegen

```bash
cd /var/www/cd-wiki
sudo cp scripts/install.env.example /root/cd-wiki-install.env
sudo chown root:root /root/cd-wiki-install.env
sudo chmod 600 /root/cd-wiki-install.env
sudoedit /root/cd-wiki-install.env
```

Alle Platzhalter muessen ersetzt werden. Das Admin-Passwort muss mindestens 16
Zeichen lang sein. Fuer den produktiven Betrieb ist ein einzigartiges, vom
Passwortmanager erzeugtes Passwort erforderlich. `SMTP_USE_TLS` ist fuer Port
587 normalerweise `true`; bei implizitem TLS auf Port 465 wird stattdessen
`SMTP_USE_SSL=true` gesetzt. Beide Werte duerfen nicht gleichzeitig aktiv sein.

Der Installer parst nur die vorgegebenen `NAME="Wert"`-Zeilen. Befehle,
Variablenersetzungen, unbekannte Namen oder unquoted Werte werden nicht
ausgefuehrt, sondern fuehren zum Abbruch. Ein Wert darf kein doppeltes
Anfuehrungszeichen und keinen Zeilenumbruch enthalten.

## 4. Installation starten

```bash
cd /var/www/cd-wiki
sudo bash scripts/install_ubuntu_24_04.sh /root/cd-wiki-install.env
```

Das Skript fuehrt in dieser Reihenfolge aus:

1. Betriebssystem, Konfigurationsrechte, Commit, Arbeitsbaum und Ports pruefen.
2. Ubuntu-Pakete installieren, vorhandenes Django erkennen und MySQL sowie Apache starten.
3. Per Certbot und ACME-Webroot ein TLS-Zertifikat beziehen.
4. getrennte Systembenutzer und geschuetzte Verzeichnisse erstellen.
5. Meilisearch herunterladen und die angegebene SHA-256-Pruefsumme pruefen.
6. Datenbank, zufaellige Anwendungsschluessel und geschuetzte Env-Dateien anlegen.
7. isolierte Python-Umgebung anlegen, Django 5.2.x pruefen, Migrationen,
   statische Dateien und ersten Administrator anlegen.
8. gehaertete systemd-Dienste und Apache-Reverse-Proxy aktivieren.
9. Django-Deployment-Check, Suche, Dienste, HTTPS und Zertifikatserneuerung testen.

Das Editor-Bundle liegt versioniert in `static/editor/wiki-editor.js`. Auf dem
Produktivserver wird daher weder Node.js installiert noch ein Frontend-Build
ausgefuehrt.

Eine eventuell systemweit installierte Django-Version wird nur gemeldet und
nicht verwendet. Fuer den Wiki-Dienst gilt ausschliesslich die durch
`requirements.txt` festgelegte Version in `/var/www/cd-wiki/.venv`.

Nach erfolgreichem Abschluss die temporaere Konfiguration entfernen, weil sie
SMTP- und Admin-Passwort enthaelt:

```bash
sudo rm /root/cd-wiki-install.env
```

## 5. Installation pruefen

Der komplette technische Test kann erneut ausgefuehrt werden:

```bash
sudo bash scripts/install_ubuntu_24_04.sh --check
```

Der Check liest nur die bereits geschuetzte Serverkonfiguration. Einzelne
Betriebspruefungen:

```bash
sudo systemctl status cd-wiki meilisearch mysql apache2
sudo journalctl -u cd-wiki -u meilisearch --since today
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

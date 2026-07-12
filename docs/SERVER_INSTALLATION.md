# Serverinstallation auf Ubuntu 24.04

Diese Anleitung installiert CD-Wiki schrittweise unter `/var/www/cd-wiki`.
Sie ist ausschliesslich fuer eine neue Ubuntu-24.04-LTS-Installation bestimmt.
Nach jeder Stufe wird angehalten. Erst nach Kontrolle wird die naechste Stufe
ausgefuehrt.

## Unantastbare Zugriffsregeln

Das Einstiegsskript `scripts/install_cd_wiki.sh` und seine internen Stufen:

- aendern keine Datei unter `/etc/ssh`;
- starten, stoppen, deaktivieren oder maskieren SSH nicht;
- aendern weder UFW noch nftables, iptables oder eine Provider-Firewall;
- aendern keine Netzwerkschnittstelle, Route, DNS- oder Resolverkonfiguration;
- fuehren weder Neustart noch Herunterfahren noch `apt upgrade` aus;
- deaktivieren keine vorhandene Apache-Site;
- laden Apache nur nach einem erfolgreichen `apache2ctl configtest` neu;
- schreiben keine globale MySQL-Konfiguration und loeschen keine Datenbank.

Der Preflight speichert SHA-256-Pruefsummen aller Dateien unter `/etc/ssh`.
Jede weitere Stufe prueft diese Dateien, `ssh.service` und den SSH-Listener vor
und nach ihren Arbeiten. Bei einer Abweichung bricht sie ab. Das verhindert
keinen externen Fehler des Providers, stellt aber sicher, dass der Installer
selbst den Serverzugang nicht konfiguriert.

## Voraussetzungen

- Ubuntu 24.04 LTS auf einem frischen VPS
- mindestens 2 CPU-Kerne und 2 GiB RAM
- zusammen mindestens 4 GiB RAM plus Swap
- mindestens 8 GiB freier Speicher auf `/`
- DNS von `wiki.only-space.de` zeigt auf den VPS
- Ports 80 und 443 sind beim Provider erreichbar
- ein aktueller Provider-Snapshot ist vorhanden
- das vollstaendige Projekt liegt unter `/var/www/cd-wiki`
- eine zweite SSH-Sitzung kann parallel geoeffnet werden

Die Skripte richten bewusst keine Firewall ein. Den SSH-Port und die
Provider-Firewall verwaltest du getrennt von dieser Installation.

## Einziger Installationseinstieg

Alle Installationsstufen werden ausschliesslich ueber dasselbe Skript gestartet:

```bash
cd /var/www/cd-wiki
sudo bash scripts/install_cd_wiki.sh status
```

Es gibt absichtlich keinen `all`-Modus. Jede schreibende Stufe nennt ihren
Umfang, verlangt ein eigenes Bestaetigungswort und beendet sich danach. Die
internen Dateien unter `scripts/ubuntu24/` verweigern einen direkten Start.
Nach dem Preflight werden Pruefsummen aller Installerdateien gespeichert; eine
spaetere Aenderung sperrt die folgenden Stufen.

## Vor dem ersten Schritt

In der ersten SSH-Sitzung:

```bash
cd /var/www/cd-wiki
test -f manage.py
test -f static/editor/wiki-editor.js
python3 scripts/ubuntu24/verify_access_safety.py
sudo bash scripts/install_cd_wiki.sh status
```

Die letzte Ausgabe muss `Zugriffsschutz-Pruefung erfolgreich.` lauten. Danach
eine zweite SSH-Sitzung oeffnen und bis zum Abschluss geoeffnet lassen. In der
zweiten Sitzung nur kontrollieren:

```bash
systemctl is-active ssh
ss -ltnp | grep sshd
```

Beide Befehle muessen den aktiven SSH-Dienst beziehungsweise einen Listener
anzeigen.

## Stufe 00: Nur Preflight

```bash
cd /var/www/cd-wiki
sudo bash scripts/install_cd_wiki.sh preflight
```

Diese Stufe installiert nichts. Sie prueft Betriebssystem, Ressourcen, DNS,
Projektpfad und SSH. Der Bericht liegt danach nur fuer root lesbar unter
`/var/lib/cd-wiki-installer/preflight-report.txt`.

Jetzt in der zweiten Sitzung erneut `systemctl is-active ssh` ausfuehren.

## Stufe 10: Ubuntu-Pakete

```bash
cd /var/www/cd-wiki
sudo bash scripts/install_cd_wiki.sh packages
```

Installiert werden Apache, mod_wsgi, MySQL, Certbot, Python und Build-Pakete.
`NEEDRESTART_MODE=l` verhindert automatische Dienstneustarts durch
`needrestart`. Es wird nur `apt-get update` und `apt-get install`, niemals ein
Paket-Upgrade, ausgefuehrt. MySQL und Apache werden nur gestartet, falls sie
noch nicht aktiv sind.

Danach den SSH-Zugang wieder in der zweiten Sitzung pruefen.

## Stufe 20: Datenbank und Geheimnisse

```bash
cd /var/www/cd-wiki
sudo bash scripts/install_cd_wiki.sh database
```

Diese Stufe erstellt ausschliesslich:

- die Datenbank `cd-wiki`;
- den MySQL-Benutzer `cdwiki@127.0.0.1` mit Rechten auf diese Datenbank;
- den Systembenutzer `cdwiki` ohne Login-Shell;
- `/etc/cd-wiki/wiki.env` mit zufaelligen Schluesseln;
- Storage-, Static- und Sicherungsverzeichnisse fuer CD-Wiki.

Sie aendert keine MySQL-Konfigurationsdatei. Wenn MySQL oeffentlich lauscht,
bricht die Stufe ab und nimmt keine Korrektur an der Serverkonfiguration vor.
SMTP bleibt deaktiviert; fuer die Installation ist kein Postfach erforderlich.

## Stufe 30: Django-Anwendung

```bash
cd /var/www/cd-wiki
sudo bash scripts/install_cd_wiki.sh application
```

Die Stufe erstellt die isolierte Python-Umgebung, installiert die festgelegten
Abhaengigkeiten, fuehrt Django-Check, Migrationen und `collectstatic` mit
Zeitlimits aus und fragt anschliessend den ersten Administrator ab. Eine
systemweit vorhandene Django-Version wird nicht verwendet.

Mitkopierte `.env`, `.venv`, `staticfiles` oder Editor-`node_modules` werden
nicht geloescht, sondern unter `/var/backups/cd-wiki` gesichert.

## Stufe 40: Meilisearch

```bash
cd /var/www/cd-wiki
sudo bash scripts/install_cd_wiki.sh search
```

Meilisearch wird passend zu `amd64` oder `arm64` von der offiziellen
GitHub-Release-Adresse geladen. Der von GitHub gelieferte SHA-256-Digest wird
vor der Installation geprueft. Der Dienst lauscht nur auf `127.0.0.1:7700` und
ist durch systemd auf 40 Prozent Arbeitsspeicher, eine CPU und 64 Tasks begrenzt.
Zusammen mit ihm wird der taegliche Wartungstimer fuer Rate-Limits eingerichtet.

## Stufe 50: Apache und TLS

Vor dieser Stufe muessen DNS sowie die Provider-Freigaben fuer Port 80 und 443
funktionieren. Dann:

```bash
cd /var/www/cd-wiki
sudo bash scripts/install_cd_wiki.sh web
```

Die Stufe fragt nur nach einer E-Mail-Adresse fuer Let's Encrypt. Sie aktiviert
eine eigene HTTP-Site, prueft die Apache-Konfiguration und laedt Apache neu.
Certbot verwendet danach den ACME-Webroot; es beendet Apache nicht. Erst nach
erfolgreichem Zertifikat erstellt das Skript die HTTPS-Site mit Django/mod_wsgi.

Vor jedem Apache-Reload laufen `apache2ctl configtest` und die SSH-Pruefung.
Falls die neue HTTPS-Konfiguration ungueltig ist, wird die zuvor funktionierende
HTTP-Konfiguration wiederhergestellt. Bestehende Apache-Sites werden nicht
deaktiviert oder ueberschrieben.

## Stufe 60: Abschlusspruefung

```bash
cd /var/www/cd-wiki
sudo bash scripts/install_cd_wiki.sh verify
```

Geprueft werden SSH, Apache-Syntax, MySQL, Meilisearch, Certbot-Timer,
Wartungstimer, lokale Listener, Django-Deployment-Einstellungen, Suchindex und
HTTPS. Erst wenn alles erfolgreich ist, wird `/etc/cd-wiki/installed` angelegt.
Die Pruefstufe kann spaeter erneut ausgefuehrt werden.

Danach im Browser mindestens testen:

1. Login und Logout
2. privates Web mit berechtigtem und unberechtigtem Benutzer
3. Topic erstellen und bearbeiten
4. Revision anzeigen und wiederherstellen
5. Attachment hochladen und geschuetzt herunterladen
6. Kommentar erstellen
7. Suche ausfuehren
8. Auditlog kontrollieren

## Bei einem Fehler

Nicht mit der naechsten Stufe fortfahren und keine Dateien oder Datenbanken
loeschen. Zuerst Ausgabe und Status sichern:

```bash
sudo systemctl status ssh apache2 mysql meilisearch --no-pager
sudo journalctl -u apache2 -u mysql -u meilisearch --since today --no-pager
sudo apache2ctl configtest
```

Solange die zweite SSH-Sitzung funktioniert, geoeffnet lassen. Der sichere
vollstaendige Rueckweg ist der vor Stufe 00 erstellte Provider-Snapshot.
CD-Wiki-Daten unter `/etc/cd-wiki`, `/var/lib/cd-wiki` und die Datenbank
`cd-wiki` vor jeder manuellen Bereinigung sichern.

## Betriebsdaten und Sicherung

- Anwendung: `/var/www/cd-wiki`
- Python-Umgebung: `/var/www/cd-wiki/.venv`
- Geheimnisse: `/etc/cd-wiki/wiki.env`
- Wiki-Dateien: `/var/lib/cd-wiki/storage`
- statische Dateien: `/var/lib/cd-wiki/static`
- Meilisearch: `/var/lib/meilisearch`
- lokale Sicherungen: `/var/backups/cd-wiki`
- Apache-Logs: `/var/log/apache2/cd-wiki-*.log`

Extern und gemeinsam sichern: MySQL-Datenbank `cd-wiki`,
`/var/lib/cd-wiki/storage` und `/etc/cd-wiki/wiki.env`. Der Suchindex kann mit
`reindex_search` neu aufgebaut werden.

## HSTS

Die Installation setzt HSTS zunaechst auf einen Tag und aktiviert weder
`includeSubDomains` noch Preload. Erst nach mehreren erfolgreich erneuerten
Zertifikaten darf `/etc/cd-wiki/wiki.env` kontrolliert angepasst werden. Apache
muss wegen einer reinen Django-Einstellung nicht neu gestartet werden; die
mod_wsgi-Prozesse koennen in einem getrennt geplanten Wartungsfenster neu
geladen werden.

## Updates

Die Stufen 00 bis 50 sind kein Update-Werkzeug. Ein Update benoetigt vorher eine
externe Sicherung, einen bekannten Git-Commit, einen eigenen Migrationsplan und
eine Rueckrollmoeglichkeit.

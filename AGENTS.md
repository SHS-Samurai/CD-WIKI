# AGENTS.md – Arbeitsanweisung für Codex

Diese Datei gilt für alle Arbeiten an diesem Projekt. Codex muss diese Regeln bei jeder Analyse, Planung und Änderung beachten.

## Projektziel

Dieses Projekt ist ein eigenes Wiki-System für:

```text
wiki.only-space.de
```

Das Wiki wird in Python entwickelt. Es soll Webs, Topics, Benutzer, Gruppen, Rechte, Revisionen, Dateianhänge, Kommentare, Auditlogs und Suche enthalten. Version 1 muss robust funktionieren, bevor Erweiterungen gebaut werden.

## Verbindliche Technik

Verwenden:

* Python
* Django
* MySQL
* Meilisearch
* Tiptap / ProseMirror JSON für Wiki-Inhalte
* Apache mit mod_wsgi
* Ubuntu-VPS

Node.js darf nur für den Editor-Build verwendet werden, nicht als Serverplattform.

Nicht verwenden:

* kein Gunicorn oder uWSGI; Django wird direkt ueber Apache mod_wsgi betrieben
* kein Java
* kein Solr
* kein PHP
* kein Laravel
* kein React-/Next.js-Projekt als Hauptarchitektur
* kein Tailwind ohne ausdrücklichen Auftrag
* kein Docker ohne ausdrücklichen Auftrag
* keine externen CDNs
* keine Tracker
* keine unnötigen Cloud-Dienste

## Arbeitsweise

Codex darf bei diesem Projekt nicht blind losprogrammieren.

Bei größeren Aufgaben gilt:

1. Projektstruktur lesen.
2. Relevante Dateien identifizieren.
3. Noch nichts ändern.
4. Kurzen Umsetzungsplan erstellen.
5. Betroffene Dateien nennen.
6. Risiken nennen.
7. Erst nach Freigabe Code ändern.
8. Änderungen klein und nachvollziehbar halten.
9. Nach Änderungen Testweg nennen.

Bei kleinen, klar begrenzten Fehlerkorrekturen darf direkt geändert werden, sofern keine Datenbank, Rechte, Sicherheit oder Architektur betroffen sind.

## Grundarchitektur

Das Wiki nutzt ein Hybridmodell.

| Bereich                | Speicherung      |
| ---------------------- | ---------------- |
| Benutzer               | MySQL            |
| Gruppen                | MySQL            |
| Rechte                 | MySQL            |
| Sessions               | Django / MySQL   |
| Web-Metadaten          | MySQL            |
| Topic-Metadaten        | MySQL            |
| aktuelle Topic-Inhalte | Dateisystem      |
| Topic-Revisionen       | Dateisystem      |
| Anhänge                | Dateisystem      |
| Attachment-Revisionen  | Dateisystem      |
| Kommentare             | MySQL            |
| Auditlog               | MySQL            |
| Suchindex              | Meilisearch      |

## Begriffe

### Web

Ein Web ist ein abgeschlossener Wiki-Bereich, ähnlich einem Verzeichnis.

Beispiele:

```text
admin
technik
projekte
dokumentation
privat
```

Webs werden nur durch Administratoren erstellt.

### Topic

Ein Topic ist eine einzelne Wiki-Seite innerhalb eines Webs.

Beispiele:

```text
technik/startseite
technik/server-setup
projekte/wiki-planung
```

### Attachment

Ein Attachment ist eine Datei, die an ein Topic angehängt wird.

### Revision

Jede Änderung an einem Topic oder Attachment erzeugt eine neue Revision.

## Inhaltsformat

Wiki-Inhalte werden nicht als HTML gespeichert.

Verbindlich:

* Inhalte werden als ProseMirror-/Tiptap-JSON gespeichert.
* Freies HTML im Editor ist in Version 1 verboten.
* Gespeicherte Inhalte dürfen keine ungeprüften HTML-Blöcke enthalten.
* Für die Anzeige wird aus JSON kontrolliertes HTML erzeugt.
* Suchtext wird aus JSON extrahiert.
* Jede Revision speichert den vollständigen Dokumentzustand.

## Adminbereich

Es gibt ein geschütztes Admin-Web:

```text
admin
```

Das Admin-Web ist technisch geschützt und nur für Administratoren zugänglich.

Pflichtbereiche im Admin-Web:

* Startseite
* Benutzer
* Gruppen
* Webs
* Rechte
* Registrierung
* Systemlog
* Suche
* Dateitypen
* Erweiterungen
* Systemstatus

Administrative Funktionen dürfen nicht nur über ausgeblendete Links geschützt werden. Die Rechteprüfung muss serverseitig erfolgen.

## Rechtekonzept

Version 1 verwendet Rechte pro Web.

Keine Topic-Sonderrechte in Version 1, außer sie werden später ausdrücklich beauftragt.

Rechte pro Web:

| Recht     | Bedeutung                                         |
| --------- | ------------------------------------------------- |
| `view`    | Web und Topics ansehen                            |
| `create`  | neue Topics erstellen                             |
| `edit`    | Topics bearbeiten                                 |
| `comment` | Kommentare schreiben                              |
| `upload`  | Dateien anhängen                                  |
| `manage`  | Web verwalten                                     |
| `delete`  | Topics oder Anhänge in den Papierkorb verschieben |

Rechte können vergeben werden an:

* einzelne Benutzer
* Gruppen
* registrierte Benutzer
* öffentliche Gäste

Mögliche Web-Sichtbarkeit:

* privat
* öffentlich lesbar
* nur registrierte Benutzer
* nur bestimmte Gruppen
* nur bestimmte Benutzer

Wichtig:

* Rechte immer serverseitig prüfen.
* Private Webs dürfen keine Inhalte an unberechtigte Benutzer ausliefern.
* Private Attachments dürfen nicht direkt über öffentliche Dateipfade abrufbar sein.
* Downloads müssen über eine Django-View mit Rechteprüfung laufen.

## Benutzerregistrierung

Die Registrierung ist im Adminbereich einstellbar.

Mögliche Modi:

* Registrierung deaktiviert
* Registrierung erlaubt mit Admin-Freigabe
* Registrierung erlaubt mit E-Mail-Bestätigung
* Registrierung erlaubt mit automatischer Aktivierung

Bot-Schutz in Version 1:

* Honeypot-Feld
* Mindestzeit für Formularausfüllung
* Rate-Limit pro IP
* Login-Versuchslimit
* E-Mail-Bestätigung
* optionale Admin-Freigabe

Externe Captcha-Dienste gehören nicht in den Kern. Sie können später als Erweiterung ergänzt werden.

## Auditlog

Das Wiki muss nachvollziehbar aufzeichnen, welcher Benutzer welche Änderung durchgeführt hat.

Pflichtinformationen:

* Zeitpunkt
* Benutzer-ID
* Benutzername
* IP-Adresse
* User-Agent
* Aktion
* betroffenes Web
* betroffenes Topic
* betroffener Anhang, falls vorhanden
* alte Revision
* neue Revision
* alter Hash
* neuer Hash
* optionale Detaildaten als JSON

Typische Aktionen:

* Login erfolgreich
* Login fehlgeschlagen
* Logout
* Benutzer registriert
* Benutzer freigeschaltet
* Web erstellt
* Web geändert
* Rechte geändert
* Topic erstellt
* Topic geändert
* Topic gelöscht
* Revision wiederhergestellt
* Attachment hochgeladen
* Attachment geändert
* Attachment gelöscht
* Kommentar erstellt
* Kommentar gelöscht
* Suchindex aktualisiert

Auditlogs dürfen nicht über normale Wiki-Funktionen veränderbar sein.

## Letzte Änderung im Topic

Jedes Topic zeigt sichtbar:

* letzter Bearbeiter
* Zeitpunkt der letzten Änderung
* aktuelle Revision
* Änderungsnotiz

Diese Daten müssen auch in den Topic-Metadaten gespeichert werden.

## Revisionen

Jede Topic-Änderung erzeugt eine neue Revision.

Regeln:

* Revisionen werden nicht überschrieben.
* Revisionen werden fortlaufend nummeriert.
* Wiederherstellung einer alten Revision erzeugt eine neue Revision.
* Die Historie bleibt vollständig erhalten.
* Jede Revision enthält Autor, Zeitpunkt, Änderungsnotiz und Hash.
* Eine spätere Vergleichsansicht zwischen Revisionen muss möglich bleiben.

Beispielstruktur:

```text
storage/
└── webs/
    └── technik/
        └── topics/
            └── startseite/
                ├── current.json
                └── revisions/
                    ├── 000001.json
                    ├── 000002.json
                    └── 000003.json
```

## Dateianhänge

Attachments werden versioniert.

Beispielstruktur:

```text
storage/
└── webs/
    └── technik/
        └── topics/
            └── startseite/
                └── attachments/
                    └── handbuch.pdf/
                        ├── current.bin
                        ├── meta.json
                        └── revisions/
                            ├── 000001.bin
                            ├── 000002.bin
                            └── 000003.bin
```

Uploads müssen geprüft werden:

* erlaubte Dateiendungen
* MIME-Type
* Dateigröße
* sicherer Dateiname
* keine Pfadmanipulation
* Speicherung außerhalb öffentlich direkt erreichbarer Pfade
* Rechteprüfung beim Download
* Auditlog bei Upload, Änderung und Löschung

In Version 1 keine ausführbaren Upload-Dateien erlauben.

Blockieren:

```text
.php
.py
.js
.sh
.exe
.bat
.cmd
.ps1
.jar
.war
```

## Suche

Die Suche erfolgt über Meilisearch.

Indexiert werden:

* Web-Titel
* Topic-Titel
* Topic-Inhalt aus ProseMirror-/Tiptap-JSON
* Dateinamen von Attachments
* extrahierter Text aus Attachments
* letzte Änderung
* Benutzername des letzten Bearbeiters

Durchsuchbare Dateitypen in Version 1:

* PDF
* DOCX
* TXT
* MD
* XLSX
* HTML als hochgeladene Datei

Mögliche Python-Bibliotheken:

* PDF: `pypdf` oder `PyMuPDF`
* DOCX: `python-docx`
* TXT/MD: direkt
* XLSX: `openpyxl`
* HTML: `BeautifulSoup`

Gescannte PDFs müssen in Version 1 nicht per OCR durchsuchbar sein.

Wichtig:

* Suchergebnisse dürfen nur Inhalte anzeigen, für die der Benutzer `view`-Rechte hat.
* Rechtefilterung muss serverseitig zuverlässig erfolgen.
* Private Inhalte dürfen nicht über Suchergebnisse sichtbar werden.

## Kommentare

Kommentare gehören zu Topics und werden in MySQL gespeichert.

Regeln:

* Nur Benutzer mit `comment`-Recht dürfen kommentieren.
* Administratoren dürfen Kommentare löschen.
* Gelöschte Kommentare sollen zunächst als gelöscht markiert werden.

## Erweiterungen

Das Wiki soll erweiterbar sein, aber Version 1 darf nicht überladen werden.

Vorgesehene Plugin-Struktur:

```text
apps/
└── plugins/
    └── example_plugin/
        ├── plugin.py
        ├── hooks.py
        ├── urls.py
        ├── templates/
        └── static/
```

Vorgesehene Hooks:

* `on_topic_before_save`
* `on_topic_after_save`
* `on_topic_render`
* `on_attachment_uploaded`
* `on_attachment_deleted`
* `on_search_index`
* `on_user_registered`
* `admin_menu_items`
* `topic_toolbar_items`

Regeln:

* Erweiterungen dürfen den Kern nicht direkt verändern.
* Erweiterungen müssen deaktivierbar sein.
* Erweiterungen dürfen keine Rechteprüfung umgehen.
* Erweiterungen dürfen keine ungeprüften HTML-Ausgaben erzeugen.

## Empfohlene Projektstruktur

```text
wiki_project/
├── AGENTS.md
├── README.md
├── TODO.md
├── requirements.txt
├── manage.py
├── config/
│   ├── settings.py
│   ├── urls.py
│   ├── wsgi.py
│   └── asgi.py
├── apps/
│   ├── accounts/
│   ├── webs/
│   ├── topics/
│   ├── attachments/
│   ├── comments/
│   ├── audit/
│   ├── search/
│   └── plugins/
├── templates/
├── static/
├── frontend/
│   └── editor/
├── storage/
│   ├── webs/
│   └── trash/
├── logs/
└── docs/
    ├── INSTALL.md
    ├── DEPLOYMENT.md
    └── SECURITY.md
```

## Datenbankregeln

Bei Datenbankänderungen immer zuerst planen.

Codex muss vor Datenbankänderungen nennen:

* betroffene Tabellen
* neue Tabellen
* geänderte Spalten
* mögliche Datenverluste
* Migrationen
* Sicherungshinweis
* Testweg

Keine Tabellen löschen.

Keine Spalten entfernen.

Keine produktiven Daten verändern.

Keine Zugangsdaten in öffentlich erreichbaren Dateien speichern.

## Sicherheit

Pflichtregeln:

* Eingaben validieren.
* Ausgaben escapen.
* CSRF-Schutz aktiv lassen.
* Rechte serverseitig prüfen.
* Upload-Dateien prüfen.
* Dateigrößen begrenzen.
* Slugs bereinigen.
* Keine `../`-Pfadmanipulation zulassen.
* Keine Secrets ins Repository schreiben.
* Keine Debug-Ausgaben im Produktivbetrieb.
* `DEBUG=False` im Produktivbetrieb.
* Login-Versuche begrenzen.
* Adminbereich besonders schützen.
* Auditlog nicht über normale UI manipulierbar machen.

## Editor

Der Editor basiert auf Tiptap/ProseMirror.

Version 1 braucht:

* Überschriften
* Absätze
* Fett
* Kursiv
* Listen
* nummerierte Listen
* Links
* Tabellen
* Zitate
* Codeblock
* horizontale Linie
* interne Wiki-Links
* Einbindung vorhandener Attachments
* Änderungsnotiz beim Speichern

Nicht in Version 1:

* freies HTML
* Makrosystem
* dynamische Skripte
* externe Einbettungen
* automatische Fremdinhalte

## Papierkorb

Löschungen sollen zunächst in den Papierkorb gehen.

Regeln:

* Topics nicht sofort physisch löschen.
* Attachments nicht sofort physisch löschen.
* Wiederherstellung durch Administrator ermöglichen.
* Auditlog schreiben.

## Deployment-Ziel

Produktivziel:

```text
https://wiki.only-space.de
```

Vorgesehen:

* Python Virtual Environment
* Django
* Apache mit mod_wsgi im Daemon-Modus
* MySQL
* Meilisearch als lokaler Dienst
* Storage außerhalb öffentlich direkt erreichbarer Webpfade
* projektbezogene Logs

Codex darf keine Server- oder Deploymentänderungen ausführen, ohne vorher zu nennen:

* betroffene Dienste
* betroffene Pfade
* betroffene Ports
* Testweg
* Rückrollmöglichkeit

## Version 1 – Fertigkriterien

Version 1 ist fertig, wenn Folgendes funktioniert:

* Admin kann sich anmelden.
* Admin kann Benutzer anlegen.
* Admin kann Gruppen anlegen.
* Admin kann Webs anlegen.
* Admin kann Rechte pro Web setzen.
* Web kann privat oder öffentlich lesbar sein.
* Benutzer sehen nur erlaubte Webs.
* Benutzer sehen nur erlaubte Topics.
* Benutzer können mit Recht `create` Topics erstellen.
* Benutzer können mit Recht `edit` Topics bearbeiten.
* Inhalte werden als ProseMirror-/Tiptap-JSON gespeichert.
* Jede Topic-Änderung erzeugt eine Revision.
* Alte Revisionen können angezeigt werden.
* Alte Revisionen können wiederhergestellt werden.
* Attachments können hochgeladen werden.
* Attachments werden versioniert.
* Private Attachments sind geschützt.
* Kommentare funktionieren.
* Auditlog zeichnet wichtige Aktionen auf.
* Topic zeigt letzte Änderung an.
* Suche findet Topics.
* Suche findet extrahierte Texte aus unterstützten Attachments.
* Suchergebnisse respektieren Rechte.
* Admin-Web enthält zentrale Verwaltung.
* Grundlayout ist responsiv und sauber bedienbar.

## Nicht Bestandteil von Version 1

Nicht bauen, solange nicht ausdrücklich beauftragt:

* Topic-Rechte zusätzlich zu Web-Rechten
* Makrosystem
* Import aus anderen Wikis
* OCR für gescannte PDFs
* Workflow-Freigaben
* E-Mail-Benachrichtigungen
* öffentliche REST-API
* Mehrsprachigkeit
* Themesystem
* Plugin-Marktplatz
* Docker-Setup
* externe Captcha-Dienste
* KI-Funktionen

## Tests

Nach Änderungen muss Codex einen Testweg nennen.

Mindestens prüfen:

* Login
* Logout
* Rechteprüfung
* Web sichtbar / nicht sichtbar
* Topic erstellen
* Topic bearbeiten
* Revision erzeugen
* Revision wiederherstellen
* Attachment hochladen
* Attachment herunterladen
* privates Attachment ohne Recht nicht abrufbar
* Kommentar erstellen
* Suche ausführen
* Auditlog prüfen
* mobile Ansicht grob prüfen

Bei Python-Code zusätzlich:

```bash
python manage.py check
python manage.py test
```

Wenn Tests fehlen, soll Codex sinnvolle erste Tests vorschlagen.

## Dokumentation

Wichtige Entscheidungen dokumentieren in:

* `README.md`
* `TODO.md`
* `docs/INSTALL.md`
* `docs/DEPLOYMENT.md`
* `docs/SECURITY.md`

Dokumentation soll knapp, praktisch und verständlich sein.

## Stilregeln

* Deutsch für sichtbare Texte und Projektdokumentation.
* Kein Gendern.
* Klare Begriffe.
* Keine KI-Floskeln.
* Keine langen Theorieblöcke.
* Code lesbar, robust und einfach halten.
* Kommentare nur, wenn sie wirklich helfen.
* Keine Massenformatierung ohne Auftrag.

## Fertigmeldung nach jeder Aufgabe

Nach jeder abgeschlossenen Aufgabe muss Codex knapp melden:

* was geändert wurde
* welche Dateien geändert wurden
* welche Datenbankänderungen erfolgt sind
* welche Risiken bestehen
* wie getestet werden kann

## Merksatz

Erst verstehen, dann planen, dann klein und sicher ändern. Das Wiki muss stabil funktionieren, bevor es erweitert wird.

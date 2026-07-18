# Wiki

Eigenes Wiki-System fuer `wiki.only-space.de` mit Rechte- und Versionskontrolle.

## Ziel

Version 1 soll Webs, Topics, Benutzer, Gruppen, Web-Rechte, Revisionen,
Dateianhaenge, Kommentare, Auditlog und Suche robust bereitstellen.

## Technik

- Python
- Django
- MySQL
- Meilisearch
- Tiptap / ProseMirror JSON fuer Inhalte
- Apache mit mod_wsgi auf Ubuntu

Node.js wird nur fuer den Editor-Build verwendet.

## Dokumentation

- [Bedienungsanleitung](docs/BEDIENUNGSANLEITUNG.md)
- [Installation](docs/INSTALL.md)
- [Serverinstallation Ubuntu 24.04](docs/SERVER_INSTALLATION.md)
- [Deployment](docs/DEPLOYMENT.md)
- [Sicherheit](docs/SECURITY.md)
- [Themes, CSS und Sidebars](docs/THEMES.md)

Die Serverinstallation beginnt immer mit genau einer kontrollierten Stufe:

```bash
sudo bash scripts/install_cd_wiki.sh preflight
```

Es gibt keinen automatischen Gesamtlauf. Details stehen in der
[Serverinstallation](docs/SERVER_INSTALLATION.md).

## Lokaler Start

1. `.env.example` nach `.env` kopieren und Werte setzen.
2. MySQL-Datenbank anlegen.
3. Virtuelle Umgebung erstellen.
4. Abhaengigkeiten installieren.
5. Migrationen ausfuehren.

```powershell
python -m venv .venv
.\.venv\Scripts\Activate.ps1
python -m pip install -r requirements.txt
python manage.py migrate
python manage.py createsuperuser
python manage.py runserver
```

## Wichtige Entscheidungen

- Der Benutzer basiert auf einem eigenen `accounts.User` mit Djangos `AbstractUser`.
- MySQL ist von Anfang an die konfigurierte Datenbank.
- Zugangsdaten werden nicht ins Repository geschrieben.
- Wiki-Inhalte werden spaeter als ProseMirror-/Tiptap-JSON gespeichert, nicht als freies HTML.
- Webs haben eine Grundsichtbarkeit und separate Web-Rechte fuer `view`, `create`, `edit`, `comment`, `upload`, `manage` und `delete`.
- Rechte koennen an Benutzer, Gruppen, registrierte Benutzer und oeffentliche Gaeste vergeben werden.
- Topic-Metadaten liegen in MySQL; aktuelle Inhalte und Revisionen liegen als JSON-Dateien im Storage.
- Jede Topic-Aenderung erzeugt eine vollstaendige Revision mit Autor, Zeitpunkt, Aenderungsnotiz und Inhalts-Hash.
- Alte Topic-Revisionen koennen angezeigt und wiederhergestellt werden; die Wiederherstellung erzeugt wieder eine neue Revision.
- Topics werden beim Loeschen per Soft-Delete in den Papierkorb verschoben und koennen von Staff-Benutzern wiederhergestellt werden.
- Auditlogs liegen in MySQL und speichern Aktion, Benutzer-Snapshot, IP-Adresse, User-Agent, Web-/Topic-Bezug, Revisionen, Hashes und optionale Details.
- Registrierung ist im Adminbereich einstellbar: deaktiviert, Admin-Freigabe, E-Mail-Bestaetigung oder automatische Aktivierung.
- Login und Registrierung nutzen persistente, IP-basierte Rate-Limits mit pseudonymisierten Schluesseln.
- Web- und Topic-Views pruefen `view`, `create` und `edit` serverseitig.
- Der Tiptap-Editor schreibt validiertes ProseMirror-JSON und kann Links, Tabellen, Wiki-Links sowie vorhandene Attachments einfuegen.
- Das Admin-Web `admin` wird per Migration vorbereitet, bleibt privat und ist nur fuer Staff-Benutzer erreichbar.
- Das Admin-Web zeigt Statusbereiche fuer System, Suche, Dateitypen und Erweiterungen.
- Attachments werden versioniert im Storage gespeichert und nur ueber eine Django-Download-View mit `view`-Recht ausgeliefert.
- Attachments werden beim Loeschen per Soft-Delete in den Papierkorb verschoben und koennen von Staff-Benutzern wiederhergestellt werden.
- Uploads pruefen Dateiname, Endung, MIME-Type, Groesse und speichern Auditlogs fuer Upload und Aktualisierung.
- Kommentare liegen in MySQL, werden serverseitig ueber das Web-Recht `comment` geschuetzt und per Soft-Delete geloescht.
- Die Suche nutzt Meilisearch als Index, fragt aber nur ueber Django ab; Suchtreffer werden vor der Ausgabe erneut per `view`-Recht gefiltert.
- Topic- und Attachment-Aenderungen stossen eine fehlertolerante Indexaktualisierung an; ein kompletter Neuaufbau erfolgt mit `python manage.py reindex_search`.
- Das CSS ist in zentrale Variablen, Grundgestaltung, Layout, Komponenten und Editor-Regeln gegliedert. Globale Theme-Werte werden ausschliesslich im geschuetzten Adminbereich verwaltet.
- Linke und rechte Sidebars sind standardmaessig deaktiviert, einzeln aktivierbar und werden bis einschliesslich 1.024 Pixel unter dem Hauptinhalt angeordnet. Details zu Theme, CSS-Variablen und Sidebars stehen in `docs/THEMES.md`.

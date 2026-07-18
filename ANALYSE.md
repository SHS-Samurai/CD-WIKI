# ANALYSE.md — Bestandsaufnahme des Django-Wikis (Phase 0)

Analysequelle: `DEPRECATED/` (Stand: 17.07.2026, Django 5.2, Python 3.13, MySQL).
Der Django-Code wurde ausschließlich gelesen, nicht ausgeführt und nicht verändert.

Für den Laravel-Nachbau sind nach Freigabe durch Wolf ausdrücklich verpflichtend:
**Webs, Web-Rechte, Kommentare, Auditlog, Papierkorb, Layout und Theme**.

## 1. Systemüberblick

Das Altsystem ist ein in Bereiche gegliedertes Wiki:

- Ein **Web** ist ein Namensraum und eine Berechtigungsgrenze.
- Ein Web enthält **Topics** (Wiki-Seiten).
- Topic-Metadaten liegen in MySQL; Inhalte und Revisionen liegen als
  ProseMirror-JSON im privaten Dateisystem.
- Anhänge sind pro Topic versioniert und werden ausschließlich über eine
  berechtigungsgeprüfte Download-Route ausgeliefert.
- Kommentare, Benutzer, Rechte, Theme, Rate-Limits und Auditlogs liegen in MySQL.
- Die Suche läuft über Meilisearch und indexiert auch extrahierten Anhangtext.
- Die Verwaltung besteht aus dem Django-Admin und einem besonderen, privaten
  Web mit dem Slug `admin`.

### Django-Apps

| App | Aufgabe |
|---|---|
| `accounts` | Benutzer, Registrierung, E-Mail-Bestätigung, Login-/Registrierungs-Rate-Limits |
| `webs` | Webs, Sichtbarkeit, Web-Rechte, Admin-Web und Statusseiten |
| `topics` | Topics, Editor-Inhalte, Rendering, Revisionen und Topic-Papierkorb |
| `attachments` | Upload, Download, Dateiprüfung, Revisionen und Anhang-Papierkorb |
| `comments` | Klartext-Kommentare mit Soft-Delete |
| `audit` | unveränderliches Aktionsprotokoll |
| `search` | Meilisearch-Index, Suchansicht und Textextraktion aus Anhängen |
| `theme` | globale Theme- und Layoutwerte, dynamisches CSS |
| `plugins` | leerer Erweiterungs-Stub ohne aktive Plugin-Implementierung |

## 2. Datenmodell

### 2.1 `accounts.User`

Erbt von Djangos `AbstractUser`.

| Feld/Relation | Typ | Bedeutung |
|---|---|---|
| `username` | String(150), unique | Anmeldename |
| `email` | E-Mail-String(254) | nicht auf DB-Ebene eindeutig |
| `password` | Passwort-Hash | Django-Passwortformat |
| `first_name`, `last_name` | String(150) | optionale Namen |
| `is_active` | Boolean | Konto aktiv |
| `is_staff` | Boolean | Adminzugang und vollständiger Wiki-Admin-Bypass |
| `is_superuser` | Boolean | Django-Superuser |
| `last_login`, `date_joined` | DateTime | Anmelde-/Erstellungszeit |
| `groups` | n:m → `auth.Group` | Gruppenmitgliedschaften |
| `user_permissions` | n:m → Django-Rechte | Django-Adminrechte |

Wichtig: Im Wiki-Rechtemodell genügt bereits `is_staff`, um alle Rechte in
allen Webs zu erhalten. Das gilt auch für das besondere Admin-Web.

### 2.2 `accounts.RegistrationSettings` (Singleton, ID 1)

| Feld | Typ | Bedeutung |
|---|---|---|
| `mode` | Enum-String(30) | `disabled`, `admin_approval`, `email_confirmation`, `automatic` |
| `updated_at` | DateTime | letzte Änderung |

`save()` erzwingt immer die ID `1`. Fehlt der Datensatz, wird er aus der
Umgebungsvariable `WIKI_REGISTRATION_MODE` erzeugt.

### 2.3 `accounts.EmailConfirmation`

| Feld/Relation | Typ | Bedeutung |
|---|---|---|
| `user` | FK → User, CASCADE | zu bestätigendes Konto |
| `token_hash` | String(64), unique | SHA-256 des versendeten Tokens |
| `created_at` | DateTime | Erstellung |
| `expires_at` | DateTime, Index | Ablauf; Standard 48 Stunden |
| `confirmed_at` | DateTime, nullable | Bestätigungszeit |

Das Klartext-Token wird nicht gespeichert.

### 2.4 `accounts.RateLimitBucket`

| Feld | Typ | Bedeutung |
|---|---|---|
| `scope` | Enum-String(30) | `login` oder `registration` |
| `key_hash` | String(64) | HMAC-pseudonymisierte Client-IP |
| `attempt_count` | Positive Integer | Versuche im Fenster |
| `window_started_at` | DateTime | Beginn des Zeitfensters |
| `blocked_until` | DateTime, nullable, Index | Sperrende |
| `updated_at` | DateTime, Index | letzte Aktualisierung |

Unique Constraint: `(scope, key_hash)`.

### 2.5 `webs.Web`

| Feld/Relation | Typ | Bedeutung |
|---|---|---|
| `slug` | Slug(80), unique | Namensraum/URL-Segment, kleingeschrieben |
| `title` | String(160) | Anzeigename |
| `description` | Text, optional | Beschreibung |
| `visibility` | Enum-String(20) | `private`, `public`, `authenticated`, `groups`, `users` |
| `is_admin_web` | Boolean | technisches Admin-Web |
| `created_by` | FK → User, SET_NULL | Ersteller |
| `updated_by` | FK → User, SET_NULL | letzter Bearbeiter |
| `created_at`, `updated_at` | DateTime | Zeitstempel |

Eine Datenmigration legt `admin` als privates Admin-Web an. Der Slug `admin`
setzt beim Validieren automatisch `is_admin_web=true`; ein Admin-Web darf nicht
öffentlich sein.

### 2.6 `webs.WebPermission`

| Feld/Relation | Typ | Bedeutung |
|---|---|---|
| `web` | FK → Web, CASCADE | betroffenes Web |
| `subject_type` | Enum-String(20) | `user`, `group`, `authenticated`, `public` |
| `subject_key` | String(191) | normalisierter eindeutiger Schlüssel |
| `user` | FK → User, nullable, CASCADE | nur bei `user` |
| `group` | FK → Group, nullable, CASCADE | nur bei `group` |
| `can_view` | Boolean | Web, Topics und Anhänge lesen |
| `can_create` | Boolean | Topics anlegen |
| `can_edit` | Boolean | Topics bearbeiten und Revisionen wiederherstellen |
| `can_comment` | Boolean | Kommentare anlegen |
| `can_upload` | Boolean | Anhänge hochladen |
| `can_manage` | Boolean | vorgesehenes Verwaltungsrecht |
| `can_delete` | Boolean | Topics/Anhänge in den Papierkorb verschieben |
| `created_at`, `updated_at` | DateTime | Zeitstempel |

Constraints:

- `(web, subject_key)` ist eindeutig.
- Genau die zum Subjekttyp passende Relation muss gesetzt sein.
- `subject_key` ist `user:<id>`, `group:<id>`, `authenticated` oder `public`.

Rechte sind additiv. Für angemeldete Benutzer werden öffentliche, allgemeine
angemeldete, Benutzer- und Gruppenfreigaben zusammengeführt. Es gibt keine
expliziten Verbote.

Die Grundsichtbarkeit gewährt ausschließlich `view`:

- `public`: jeder darf lesen.
- `authenticated`: jeder angemeldete Benutzer darf lesen.
- `private`, `groups`, `users`: Lesen nur über passende `WebPermission`.

Das Recht `manage` ist modelliert und in der Oberfläche dokumentiert, wird im
vorliegenden Code aber an keiner fachlichen Route geprüft oder genutzt.

### 2.7 `topics.Topic`

| Feld/Relation | Typ | Bedeutung |
|---|---|---|
| `web` | FK → Web, CASCADE | zugehöriges Web |
| `slug` | Slug(120) | URL-Segment, kleingeschrieben |
| `title` | String(180) | Titel |
| `current_revision` | Positive Integer | aktuelle Revisionsnummer |
| `current_hash` | String(64) | SHA-256 des kanonischen Inhalts-JSON |
| `change_note` | String(255), optional | Änderungsnotiz |
| `last_edited_by` | FK → User, SET_NULL | letzter Bearbeiter |
| `last_edited_at` | DateTime, nullable | letzte inhaltliche Änderung |
| `is_deleted` | Boolean | Papierkorbstatus |
| `created_by` | FK → User, SET_NULL | Ersteller |
| `created_at`, `updated_at` | DateTime | Zeitstempel |

Unique Constraint: `(web, slug)`. Ein gelöschtes Topic blockiert seinen Slug
weiterhin. Es gibt keine Felder für Löschzeit oder löschenden Benutzer.

### 2.8 `attachments.Attachment`

| Feld/Relation | Typ | Bedeutung |
|---|---|---|
| `topic` | FK → Topic, CASCADE | zugehöriges Topic |
| `original_filename` | String(255) | bereinigter Anzeigename |
| `storage_name` | String(255) | kleingeschriebener Speichername |
| `content_type` | String(120) | validierter Upload-MIME-Type bzw. abgeleiteter Typ |
| `size` | Positive Big Integer | Dateigröße |
| `current_revision` | Positive Integer | aktuelle Dateirevision |
| `current_hash` | String(64) | SHA-256 der Datei |
| `change_note` | String(255), optional | Änderungsnotiz |
| `uploaded_by` | FK → User, SET_NULL | erster Uploader |
| `updated_by` | FK → User, SET_NULL | letzter Uploader |
| `is_deleted` | Boolean | Papierkorbstatus |
| `created_at`, `updated_at` | DateTime | Zeitstempel |

Unique Constraint: `(topic, storage_name)`. Ein erneuter Upload desselben
Namens erzeugt eine neue Revision und reaktiviert einen gelöschten Anhang.

### 2.9 `comments.Comment`

| Feld/Relation | Typ | Bedeutung |
|---|---|---|
| `topic` | FK → Topic, CASCADE | zugehöriges Topic |
| `author` | FK → User, SET_NULL | Autor |
| `author_username` | String(150) | beständiger Benutzername-Snapshot |
| `body` | Text | Klartext; Formulargrenze 5.000 Zeichen |
| `is_deleted` | Boolean, Index | Soft-Delete |
| `deleted_by` | FK → User, SET_NULL | löschender Staff-Benutzer |
| `deleted_at` | DateTime, nullable | Löschzeit |
| `created_at`, `updated_at` | DateTime | Zeitstempel |

Index: `(topic, is_deleted, created_at)`. Kommentare können weder bearbeitet
noch beantwortet werden. Gelöschte Kommentare bleiben als Platzhalter sichtbar.

### 2.10 `audit.AuditLog`

| Feld/Relation | Typ | Bedeutung |
|---|---|---|
| `created_at` | DateTime, Index | Zeitpunkt |
| `user` | FK → User, SET_NULL | optionaler aktueller Benutzerbezug |
| `user_id_snapshot` | Big Integer, nullable | beständige frühere Benutzer-ID |
| `username` | String(150) | beständiger Benutzername |
| `ip_address` | IP-Adresse, nullable | ermittelte Client-IP |
| `user_agent` | Text | User-Agent |
| `action` | Enum-String(60), Index | Aktionstyp |
| `web` | FK → Web, SET_NULL | optionaler Objektbezug |
| `web_slug` | String(80) | beständiger Web-Snapshot |
| `topic` | FK → Topic, SET_NULL | optionaler Objektbezug |
| `topic_slug` | String(120) | beständiger Topic-Snapshot |
| `attachment_name` | String(255) | optionaler Anhangname |
| `old_revision`, `new_revision` | Positive Integer, nullable | Revisionswechsel |
| `old_hash`, `new_hash` | String(64) | Inhalts-/Dateihashes |
| `details` | JSON | freie Zusatzdaten |

Aktionstypen: `login_success`, `login_failed`, `logout`, `user_registered`,
`user_approved`, `web_created`, `web_updated`, `permissions_updated`,
`topic_created`, `topic_updated`, `topic_deleted`, `revision_restored`,
`attachment_uploaded`, `attachment_updated`, `attachment_deleted`,
`comment_created`, `comment_deleted`, `search_index_updated`, `theme_updated`,
`rate_limit_blocked`.

Die Typen für Web- und Rechteänderungen existieren, werden vom vorhandenen
Django-Admin jedoch nicht geschrieben.

### 2.11 `theme.ThemeSettings` (Singleton, ID 1)

| Feld | Typ/Grenze | Bedeutung |
|---|---|---|
| `primary_color` | Hex-Farbe | Primärfarbe |
| `page_background_color` | Hex-Farbe | Seitenhintergrund |
| `surface_color` | Hex-Farbe | Flächenfarbe |
| `text_color` | Hex-Farbe | Textfarbe |
| `muted_text_color` | Hex-Farbe | Sekundärtext |
| `border_color` | Hex-Farbe | Rahmenfarbe |
| `font_size_base` | Integer 14–20 | Basisschriftgröße in px |
| `page_max_width` | Integer 960–1920 | Seitenbreite in px |
| `content_max_width` | Integer 560–1600 | Inhaltsbreite in px |
| `sidebar_left_width` | Integer 180–400 | linke Sidebarbreite in px |
| `sidebar_right_width` | Integer 180–400 | rechte Sidebarbreite in px |
| `radius_strength` | Integer 0–24 | Rundungsstärke in px |
| `left_sidebar_enabled` | Boolean | linke Sidebar anzeigen |
| `right_sidebar_enabled` | Boolean | rechte Sidebar anzeigen |
| `updated_at` | DateTime | letzte Änderung |

Farben müssen `#RRGGBB` entsprechen und für Primär-, Text- und Sekundärtextfarbe
mindestens 4,5:1 Kontrast gegen Seiten- und Flächenfarbe erreichen.
`content_max_width` darf `page_max_width` nicht überschreiten.

### 2.12 Relationsübersicht

```text
User >──< Group
  │        │
  └──< WebPermission >── Web ──< Topic ──< Attachment
                                 │
                                 ├──< Comment
                                 └──< AuditLog

User ──< EmailConfirmation
User ──< AuditLog
RegistrationSettings (Singleton)
ThemeSettings (Singleton)
RateLimitBucket
```

## 3. Inhalts- und Dateispeicherung

### 3.1 Topics

Das Altsystem speichert keinen Markdown-Text. Der Tiptap-Editor liefert
ProseMirror-JSON. MySQL enthält nur Topic-Metadaten.

```text
storage/webs/<web>/topics/<topic>/
├── current.json
└── revisions/
    ├── 000001.json
    ├── 000002.json
    └── …
```

Eine Revisionsdatei enthält Web, Topic, Titel, Revision, Zeitpunkt, Autor-ID,
Autorname, Änderungsnotiz, SHA-256 und das vollständige Inhaltsdokument.

Eigenschaften:

- Jede Speicherung legt einen vollständigen, unveränderlichen Snapshot an.
- Revisionsnummern werden in einer DB-Transaktion mit `select_for_update()`
  vergeben.
- JSON wird kanonisch gehasht.
- Schreiben erfolgt über temporäre Datei und `os.replace()`.
- Bei Topic-DB- oder Auditfehlern werden die betroffenen Dateien auf den
  vorherigen Stand zurückgerollt.
- Pfadbestandteile werden gegen Traversal geprüft und müssen innerhalb des
  Storage-Roots liegen.
- Unix-Rechte neuer privater Verzeichnisse/Dateien: `0700`/`0600`.

### 3.2 Anhänge

```text
storage/webs/<web>/topics/<topic>/attachments/<storage-name>/
├── current.bin
├── meta.json
└── revisions/
    ├── 000001.bin
    ├── 000002.bin
    └── …
```

Die Dateirevisionen existieren im Storage. Die Oberfläche bietet jedoch nur
den Download der aktuellen Revision; es gibt keine Anhang-Historie und keine
Route für alte Dateirevisionen.

## 4. Features und Verhalten

### 4.1 Webs und Rechte

- Startseite listet ausschließlich sichtbare Webs.
- Web-Seite listet nicht gelöschte Topics.
- Webs werden im Django-Admin angelegt und bearbeitet.
- Das `admin`-Web wird automatisch angelegt und ist nur für Staff sichtbar.
- Sieben additive Rechte pro Web und vier Subjekttypen.
- Staff-Benutzer umgehen alle Web-Rechte.
- Fehlende Rechte führen derzeit zu HTTP 403; private Objekte werden nicht
  durch HTTP 404 verborgen.

### 4.2 Topics und Editor

- Topic anlegen, anzeigen, bearbeiten und per Soft-Delete löschen.
- Slug ist nur beim Anlegen änderbar und pro Web eindeutig.
- Tiptap-WYSIWYG mit Absatz, H1/H2, Fett, Kursiv, Listen, Zitat, Codeblock,
  Trennlinie, Link, internem Wiki-Link und Tabellen.
- Vorhandene Anhänge können als Link eingefügt werden.
- Interne Links werden als normale URLs `/w/<web>/<topic>/` gespeichert.
- Es gibt keine roten Links und keine automatische Erstellseite für fehlende Ziele.
- Änderungsnotizen sind im Code optional.

### 4.3 Inhaltsvalidierung und Rendering

Erlaubte Knoten: Dokument, Absatz, Text, Überschrift, Aufzählung, nummerierte
Liste, Listenelement, Zitat, Codeblock, Trennlinie, harter Umbruch, Tabelle,
Zeile, Zelle und Kopfzelle.

Erlaubte Marks: fett, kursiv, Link und Inline-Code. Links dürfen nur leeres,
`http`, `https` oder `mailto` als Schema verwenden. Inhalt wird nach JSON-Größe,
Knotenzahl, Tiefe und Textlänge begrenzt. Der Renderer escaped Text und Attribute
und erzeugt nur fest vorgegebenes HTML.

### 4.4 Topic-Versionierung

- Jedes Speichern erzeugt eine neue Vollrevision.
- Historie zeigt Revision, Zeit, Autor und Änderungsnotiz.
- Einzelrevisionen können gelesen werden.
- Wiederherstellen erzeugt eine weitere neue Revision.
- Es existiert keine Diff-Ansicht.

### 4.5 Anhänge

- Erlaubt: PDF, DOCX, TXT, MD, XLSX und HTML; Standardlimit 25 MB.
- Blockiert sind insbesondere PHP, Python, JavaScript, Shell-/PowerShell-Dateien,
  ausführbare Windows-Dateien sowie JAR/WAR.
- Dateiname, Endung, gemeldeter MIME-Type, Dateigröße und Dateiinhalt werden geprüft.
- PDFs benötigen eine PDF-Signatur; DOCX/XLSX eine passende ZIP-Struktur;
  Textdateien dürfen keine Nullbytes enthalten.
- ZIP-Mitglieder und entpackte Gesamtgröße sind begrenzt.
- Wiederholter Dateiname erzeugt eine neue Revision.
- Download nur über eine `view`-geschützte Route und als Attachment.
- Löschen ist Soft-Delete; Wiederherstellen ist Staff vorbehalten.

### 4.6 Kommentare

- Kommentare sind Klartext und auf 5.000 Zeichen begrenzt.
- Erstellen benötigt `view` und `comment`.
- Löschen ist ausschließlich Staff erlaubt, unabhängig vom Web-Recht `manage`.
- Löschen ist Soft-Delete; der Inhalt verschwindet, der Platzhalter bleibt.
- Kein Bearbeiten, keine Antworten, keine Verschachtelung.

### 4.7 Papierkorb

- Getrennte Staff-Seiten für gelöschte Topics und Anhänge.
- Keine endgültige Löschfunktion in der Wiki-Oberfläche.
- Wiederherstellung reaktiviert das Objekt und aktualisiert den Suchindex.
- Topic- und Anhangdateien bleiben beim Löschen unverändert im privaten Storage.

### 4.8 Suche

- Meilisearch durchsucht Webtitel, Topictitel, Wiki-Pfad, Topic-Text,
  Anhangnamen, extrahierten Anhangtext und letzten Bearbeiter.
- Extraktion: PDF, DOCX, XLSX, TXT, Markdown und HTML; kein OCR.
- HTML-Skripte und Styles werden vor der Textextraktion entfernt.
- Parsergrenzen bestehen für PDF-Seiten, Tabellenzellen, Archivgröße und Textlänge.
- Nach jeder Topic-/Anhangänderung wird fehlertolerant neu indexiert.
- Ein Management-Command baut den gesamten Index neu auf.
- Die ersten maximal 100 Meilisearch-Treffer werden anschließend serverseitig
  nochmals anhand des `view`-Rechts gefiltert.
- Bei Nichtverfügbarkeit erscheint eine Fehlermeldung; das übrige Wiki bleibt nutzbar.

### 4.9 Registrierung und Anmeldung

- Registrierung deaktiviert, mit Adminfreigabe, E-Mail-Bestätigung oder automatisch.
- E-Mail-Bestätigung verwendet ein zufälliges Token; gespeichert wird nur dessen Hash.
- Honeypot und Mindest-Ausfüllzeit gegen Bots.
- Persistentes IP-basiertes Rate-Limit für Login und Registrierung.
- Standards: 5 Loginversuche/15 Minuten, 3 Registrierungsversuche/Stunde.
- Erfolgreicher Login löscht den Login-Zähler.
- Django-Admin-Login wird auf denselben begrenzten Login umgeleitet.
- Login, Logout, Fehlversuche, Sperren und Registrierungen werden auditiert.

### 4.10 Auditlog

- Protokolliert Benutzer-/Netzwerk-Snapshot, Zielobjekt, Revisionen, Hashes und Details.
- Im Django-Admin nur lesbar; kein Hinzufügen oder Löschen.
- Aktive Protokollierung für Authentifizierung, Registrierung, Topics, Anhänge,
  Kommentare, Suchindex und Theme.
- Die definierten Aktionen `web_created`, `web_updated` und
  `permissions_updated` werden im vorhandenen Code nicht ausgelöst.

### 4.11 Layout und Theme

Das tatsächlich vorhandene Layout besteht aus:

- fester Kopfzeile mit Wiki-, Web-, Such-, Admin- und Login-/Logout-Navigation,
- Hauptinhalt,
- optionaler linker Sidebar mit Links zu Webs und Suche,
- optionaler rechter Sidebar mit Admin-/Sitzungslinks,
- festem Footer.

Die Sidebars enthalten keine frei editierbaren Inhalte. Es gibt kein
LayoutBlock-Modell und keine pro-Web-Layouts. Bei Breiten bis 1.024 px werden
Sidebars unter den Hauptinhalt gesetzt.

Das globale Theme ist im Django-Admin änderbar. Der öffentliche Endpunkt
`/theme/active.css` liefert ausschließlich fest definierte CSS-Variablen,
verwendet sichere Fallbackwerte, `ETag` und eine Stunde Browser-Cache. Theme-
Änderungen und Zurücksetzen werden innerhalb einer DB-Transaktion auditiert.

### 4.12 Verwaltung und Status

- Django-Admin für Benutzer, Gruppen, Webs, Web-Rechte, Registrierung, Theme,
  Topics, Anhänge, Kommentare und Auditlogs.
- Admin-Web-Dashboard mit Zählern für Benutzer, Webs, Topics, Anhänge,
  Rate-Limit-Sperren und Registrierungsmodus.
- Statusseiten für Datenbank/Storage/Sicherheitseinstellungen, Meilisearch,
  erlaubte Dateitypen und Erweiterungen.
- Erweiterungsseite ist nur ein Stub; gelistete Hook-Namen sind nicht implementiert.

## 5. Routen und Seiten

| Methode | URL | Recht/Zugriff | Zweck |
|---|---|---|---|
| GET | `/` | sichtbar gefiltert | Web-Übersicht |
| GET | `/search/?q=…` | Treffer per `view` gefiltert | globale Suche |
| GET | `/theme/active.css` | öffentlich | validierte Theme-CSS-Variablen |
| GET/POST | `/accounts/login/` | öffentlich | begrenzter Login |
| POST | `/accounts/logout/` | angemeldet | Logout |
| GET/POST | `/accounts/register/` | abhängig vom Modus | Registrierung |
| GET | `/accounts/register/confirm/<token>/` | Token | E-Mail-Bestätigung |
| GET | `/admin/login/` | öffentlich | Weiterleitung zum begrenzten Login |
| GET | `/admin/` | Django-Adminrechte | Django-Admin |
| GET | `/w/<web>/` | `view` | Web-/Topic-Übersicht oder Admin-Dashboard |
| GET/POST | `/w/<web>/new/` | `view` + `create` | Topic anlegen |
| GET | `/w/<web>/<topic>/` | `view` | Topic, Anhänge und Kommentare anzeigen |
| GET/POST | `/w/<web>/<topic>/edit/` | `view` + `edit` | Topic bearbeiten |
| POST | `/w/<web>/<topic>/delete/` | `view` + `delete` | Topic in Papierkorb |
| GET | `/w/<web>/<topic>/revisions/` | `view` | Revisionsliste |
| GET | `/w/<web>/<topic>/revisions/<n>/` | `view` | Revision ansehen |
| POST | `/w/<web>/<topic>/revisions/<n>/restore/` | `view` + `edit` | Revision als neue Revision wiederherstellen |
| GET/POST | `/w/<web>/<topic>/attachments/upload/` | `view` + `upload` | Anhang hochladen/aktualisieren |
| GET | `/w/<web>/<topic>/attachments/<id>/download/` | `view` | aktuellen Anhang herunterladen |
| POST | `/w/<web>/<topic>/attachments/<id>/delete/` | `view` + `delete` | Anhang in Papierkorb |
| POST | `/w/<web>/<topic>/comments/create/` | `view` + `comment` | Kommentar anlegen |
| POST | `/w/<web>/<topic>/comments/<id>/delete/` | Staff | Kommentar soft-löschen |
| GET | `/system/trash/topics/` | Staff | Topic-Papierkorb |
| POST | `/system/trash/topics/<id>/restore/` | Staff | Topic wiederherstellen |
| GET | `/system/trash/attachments/` | Staff | Anhang-Papierkorb |
| POST | `/system/trash/attachments/<id>/restore/` | Staff | Anhang wiederherstellen |
| GET | `/system/admin/status/` | Staff/Admin-Web | Systemstatus |
| GET | `/system/admin/search/` | Staff/Admin-Web | Meilisearch-Status |
| GET | `/system/admin/file-types/` | Staff/Admin-Web | erlaubte/gesperrte Dateitypen |
| GET | `/system/admin/extensions/` | Staff/Admin-Web | Erweiterungs-Stub |

## 6. Besondere Logik und Sicherheitsanforderungen

- Serverseitige Rechteprüfung ist für alle fachlichen Lese-/Schreibzugriffe nötig.
- Private Downloads dürfen nie direkt aus einem öffentlichen Storage-Pfad kommen.
- CSRF ist global aktiv; schreibende Aktionen verwenden POST.
- CSP, `Permissions-Policy`, `X-Frame-Options`, sichere Cookies, HTTPS-Redirect
  und HSTS sind konfigurierbar bzw. im Produktionsmodus erzwungen.
- Produktionsstart wird bei Debugmodus, unsicherem Secret, Wildcard-Host,
  fehlendem Meilisearch-Key oder Console-Mailbackend verweigert.
- Client-IP aus `X-Forwarded-For` wird nur hinter konfigurierten vertrauenswürdigen
  Proxies ausgewertet.
- ProseMirror-Inhalt wird serverseitig validiert und beim Rendern erneut escaped.
- Topic-Revisionen schützen sich durch DB-Sperre gegen parallele Revisionskollisionen.
- Topic-Dateien werden bei Transaktionsfehlern wiederhergestellt.
- Uploads liegen privat und werden gegen Pfadtraversal, Typ-Spoofing und
  komprimierte Größenangriffe geprüft.
- Theme erlaubt kein freies CSS, keine URLs und keine beliebigen Selektoren.
- Sichtbare Texte und Bedienungsdokumentation sind deutsch.

## 7. Bestandsdaten

### Lokal vorhanden

- `DEPRECATED/storage/` enthält ausschließlich drei `.gitkeep`-Dateien.
- `DEPRECATED/logs/` enthält ausschließlich `.gitkeep`.
- Keine SQLite-Datenbank, kein SQL-Dump, kein Medien-/Uploadbestand.
- `DEPRECATED/cd-wiki.zip` ist ca. 41,2 MB groß und enthält eine vollständige
  Projektkopie einschließlich `.git` und `.venv`, aber ebenfalls nur leere
  Storage-Platzhalter und keine Datenbankdatei bzw. keinen SQL-Dump.
- Eine lokale `DEPRECATED/.env` ist vorhanden; ihre Geheimwerte wurden nicht gelesen.

Damit existieren im Projektverzeichnis keine migrierbaren Wiki-Inhalte.
Mögliche Datensätze einer externen MySQL-Datenbank wurden nicht geprüft.

## 8. Konsequenzen für den Laravel-Phasenplan

Die nachfolgenden Punkte müssen gegenüber dem bisherigen Standardausbau in
`AGENTS.md` berücksichtigt werden:

1. **Webs und WebPermission** müssen vor dem Artikel-CRUD als Kernmodelle entstehen.
2. Laravel-Policies müssen alle sieben Web-Rechte und die vier Subjekttypen abbilden.
3. Artikel/Topics brauchen eine Relation zum Web und einen pro Web eindeutigen Slug.
4. Kommentare, Auditlog, Papierkorb, Layout und Theme sind Pflichtumfang und
   benötigen eigene Umsetzungsschritte.
5. Das Zielsystem verwendet gemäß `AGENTS.md` Tiptap mit Markdown-Serialisierung
   und `league/commonmark`; das ist eine bewusste technische Abweichung vom
   ProseMirror-JSON-Storage des Altsystems.
6. Die Sicherheitsprinzipien des Altsystems bleiben unabhängig vom neuen
   Speicherformat erhalten: serverseitige Validierung, HTML-Escaping,
   zentralisierte Rechte, private Downloads und sichere Uploadprüfung.
7. Das Altsystem besitzt keine Kategorien. Kategorien sind daher eine neue
   Laravel-Funktion und kein zu migrierendes Altfeature.
8. Das Altsystem besitzt keine Diff-Ansicht. Die in Phase 3 geplante Diff-Ansicht
   ist eine neue Laravel-Funktion.

## 9. Offene Fragen an Wolf

1. **Suche:** Muss die Suche weiterhin Inhalte aus PDF, DOCX, XLSX, TXT,
   Markdown und HTML durchsuchen, oder genügt der in `AGENTS.md` vorgesehene
   MySQL-FULLTEXT-Index über Topic-Titel und Topic-Inhalt?
2. **Anhänge:** Müssen die sechs Dokumentformate samt Dateiversionierung erhalten
   bleiben, oder soll der Laravel-Nachbau gemäß Phase 4 nur Bilder unterstützen?
3. **`manage`-Recht:** Soll `manage` im Laravel-System ein echtes Web-
   Verwaltungs- und Moderationsrecht erhalten? Im Django-Modell existiert es,
   der vorhandene Code nutzt es jedoch nicht.
4. **Wiki-Link-Syntax mit Webs:** Soll `[[Topic]]` innerhalb des aktuellen Webs
   verlinken und `[[Web/Topic]]` webübergreifend verlinken?
5. **Externe Bestandsdaten:** Existiert außerhalb des Projektordners noch eine
   MySQL-Datenbank mit produktiven Inhalten, die später migriert werden muss?

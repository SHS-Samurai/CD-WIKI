# AGENTS.md— Wiki-Umbau: Python/Django → PHP/Laravel

## Projektziel

Das bestehende Wiki-System (Python/Django, vollständiger Code liegt im Verzeichnis DEPRECATED) wird als neues System auf Basis von **PHP/Laravel** mit einem professionellen aber sicheren editornachgebaut. Ziel ist ein funktional gleichwertiges Wiki, das auf einem Standard-LAMP-Server (Apache, MySQL, PHP 8.3) läuft. Der Django-Code dient ausschließlich als Referenz und Analysequelle — er wird nicht verändert, nicht ausgeführt und nicht gelöscht.

## Kommunikation & Arbeitsweise

- Sämtliche Kommunikation auf **Deutsch**.
- **Minimale Chat-Ausgabe**: keine Status-Kommentare, kein Erklären der eigenen Arbeitsschritte. Nur melden bei Blockern (max. 2 Sätze + konkrete Frage) und nach Abschluss einer Phase (1 Satz).
- Nach jeder Phase **STOPPEN** und auf Freigabe warten. Niemals eigenmächtig in die nächste Phase wechseln.
- Bei Unklarheiten im Django-Code: nicht raten, sondern kurz nachfragen.

## Schutzregeln (VPS-Sicherheit — HÖCHSTE PRIORITÄT)

- **NIEMALS** `sudo` verwenden.
- **NIEMALS** systemweite Installationen, Apache-/MySQL-/PHP-Konfigurationen oder Dateien außerhalb des Projektverzeichnisses verändern.
- Keine Pakete über `apt` installieren. Erlaubt sind nur projektlokale Werkzeuge: `composer`, `php artisan`, `npm` (innerhalb des Projekts).
- Keine laufenden Dienste starten, stoppen oder neu konfigurieren. Wenn ein Systemeingriff nötig erscheint: STOPPEN, Befehl vorschlagen, Wolf führt ihn selbst aus.
- Datenbankzugriff nur auf die eigens für dieses Projekt angelegte MySQL-Datenbank (Zugangsdaten in `.env`), niemals auf andere Datenbanken.
- Vor destruktiven DB-Operationen (`migrate:fresh`, `db:wipe`, DROP) immer explizit fragen.

## Technologie-Stack (verbindlich)

| Bereich | Technologie |
|---|---|
| Framework | Laravel 13 (aktuellste stabile und unterstützte Version) |
| Auth | Laravel Breeze, **Blade-Variante** (kein Inertia, kein React/Vue) |
| Templates | Blade + Tailwind CSS (Breeze-Standard) |
| Datenbank | MySQL (bestehende LAMP-Installation) |
| Editor | Tiptap (ProseMirror-basierter WYSIWYG-Editor, per npm eingebunden); serialisiert nach Markdown, sodass das Markdown-Backend unveraendert bleibt |
| Markdown-Rendering | league/commonmark (serverseitig, mit HTML-Escaping gegen XSS) |
| JS | Nur Vanilla JS / Alpine.js falls nötig. Keine SPA. |

## Verzeichnisstruktur

```
projektverzeichnis/
├── django-alt/        ← bestehender Django-Code (READ-ONLY Referenz)
│                        (falls der Django-Code noch lose im Wurzelverzeichnis
│                         liegt: in Phase 0 zuerst nach django-alt/ verschieben)
├── wiki/              ← neues Laravel-Projekt (wird in Phase 1 erstellt)
├── ANALYSE.md         ← Ergebnis von Phase 0
├── MIGRATION.md       ← Ergebnis von Phase 5
└── CLAUDE.md          ← diese Datei
```

---

## Phase 0 — Analyse des Django-Systems (nur lesen)

1. Django-Code vollständig sichten: `models.py`, `views.py`/Views, `urls.py`, `forms.py`, `settings.py`, Templates, ggf. `admin.py`.
2. Prüfen, ob Bestandsdaten existieren (SQLite-Datei `db.sqlite3`, SQL-Dumps, Media-/Upload-Ordner). Fundorte notieren.
3. Ergebnis in **ANALYSE.md** dokumentieren:
   - **Datenmodell**: alle Models mit Feldern, Typen, Relationen (als Tabellen)
   - **Features**: vollständige Liste (z. B. Artikel anlegen/bearbeiten, Versionierung, Suche, Kategorien, Uploads, Rechte/Rollen)
   - **Routen/Seiten**: alle URLs mit Zweck
   - **Besonderheiten**: Custom-Logik, Signale, Middleware, Template-Filter
   - **Bestandsdaten**: was ist vorhanden, geschätzter Umfang
   - **Offene Fragen** an Wolf
4. STOPP — ANALYSE.md wird von Wolf freigegeben. Falls die Analyse Features zeigt, die in dieser CLAUDE.md nicht abgedeckt sind, werden die Phasen gemeinsam angepasst.

## Phase 1 — Laravel-Grundgerüst

1. Neues Laravel-13-Projekt im Ordner `wiki/` erstellen (`composer create-project`).
2. Breeze (Blade) installieren. Beim ersten Webaufruf führt ein geschützter Installationsassistent durch die Eingabe der vorhandenen MySQL-Projektdatenbank und führt danach die Migrationen aus; Zugangsdaten werden niemals fest im Repository hinterlegt.
3. Basis-Layout erstellen: Kopfzeile mit Wiki-Titel, Navigation (Startseite, Alle Artikel, Suche, Login/Logout), Fußzeile.
4. Startseite mit Platzhalter.
5. Test: `php artisan serve` lokal lauffähig, Registrierung/Login funktioniert.
6. STOPP.

## Phase 2 — Artikel-Grundfunktionen (CRUD + Markdown)

1. Migration + Model `Article`: Felder gemäß ANALYSE.md (mindestens: `title`, `slug` (unique), `content` (Markdown), `user_id`, Timestamps).
2. Routen: Übersicht, Anzeigen (`/wiki/{slug}`), Erstellen, Bearbeiten, Löschen (Soft Delete).
3. Tiptap als WYSIWYG-Editor im Erstellen-/Bearbeiten-Formular (serialisiert nach Markdown).
4. Serverseitiges Markdown-Rendering mit league/commonmark; **Wiki-Links** im Format `[[Artikeltitel]]` werden zu internen Links (rote Links für nicht existierende Artikel, die beim Klick das Erstellen-Formular mit vorausgefülltem Titel öffnen).
5. Berechtigungen: Lesen öffentlich, Erstellen/Bearbeiten nur eingeloggt, Löschen nur Autor oder Admin (Rollenmodell gemäß ANALYSE.md).
6. Validierung + deutschsprachige Fehlermeldungen.
7. Test: Artikel anlegen, bearbeiten, verlinken, löschen.
8. STOPP.

## Phase 3 — Versionierung

1. Migration + Model `ArticleRevision`: bei jedem Speichern wird der vorherige Stand als Revision abgelegt (`article_id`, `title`, `content`, `user_id`, `created_at`).
2. Versionsverlauf-Seite pro Artikel: Liste aller Revisionen mit Datum und Autor.
3. Einzelne Revision ansehen; **Diff-Ansicht** zwischen zwei Revisionen (einfaches zeilenbasiertes Diff genügt, Paket z. B. `jfcherng/php-diff`).
4. Wiederherstellen einer alten Revision (erzeugt neue Revision, keine Datenvernichtung).
5. Test: mehrfaches Bearbeiten, Verlauf prüfen, Wiederherstellen.
6. STOPP.

## Phase 4 — Suche, Kategorien, Uploads

Umfang gemäß ANALYSE.md, Standardausbau:

1. **Suche**: Volltextsuche über Titel + Inhalt (MySQL `FULLTEXT`-Index), Suchseite mit Treffer-Ausschnitten.
2. **Kategorien**: Model `Category`, Artikel n:m zuordenbar, Kategorieseiten, Kategorieliste.
3. **Uploads**: Bild-Upload im Editor (nur Bildformate, Größenlimit, Speicherung unter `storage/app/public`, `php artisan storage:link`), Einbindung im Markdown.
4. Test aller drei Bereiche.
5. STOPP.

## Phase 5 — Datenmigration (nur falls Bestandsdaten existieren)

1. Einmaliges Artisan-Command `wiki:import-django` schreiben, das die Django-Daten (SQLite/Dump laut ANALYSE.md) in die neuen MySQL-Tabellen überführt: Benutzer (mit Zufallspasswörtern + Hinweisliste für Passwort-Reset), Artikel, Revisionen, Kategorien, Uploads.
2. Probelauf gegen die Datenbank, Ergebnisbericht in **MIGRATION.md** (Anzahl importierter Datensätze, übersprungene Einträge mit Grund).
3. STOPP — Wolf prüft stichprobenartig.

## Phase 6 — Produktivbetrieb vorbereiten

1. `.env`-Produktionswerte dokumentieren (`APP_ENV=production`, `APP_DEBUG=false`).
2. Checkliste als **DEPLOY.md** erstellen: Apache-VHost-Beispiel (DocumentRoot auf `wiki/public`), benötigte PHP-Extensions, `composer install --no-dev`, `php artisan migrate --force`, Caching-Befehle, Rechte auf `storage/` und `bootstrap/cache/`.
   **Wichtig:** Die Serverbefehle nur dokumentieren — Ausführung übernimmt Wolf selbst.
3. Kurzer Sicherheits-Check: CSRF überall aktiv, XSS-Escaping im Markdown, Upload-Validierung, Rate-Limiting auf Login.
4. STOPP — Projektabschluss.

---

## Technische Konventionen

- Code-Kommentare und Commit-Messages auf Deutsch, kurz und sachlich.
- Laravel-Standards einhalten: Form Requests für Validierung, Policies für Berechtigungen, Eloquent statt Raw-SQL (Ausnahme: FULLTEXT-Suche).
- Keine zusätzlichen Composer-/npm-Pakete ohne Rückfrage, außer den hier genannten.
- Alle sichtbaren Texte der Oberfläche auf Deutsch.

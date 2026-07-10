# Themes

## Aufbau

`static/css/wiki.css` ist die einzige eingebundene CSS-Datei und ordnet die
folgenden Teilbereiche:

- `tokens.css`: sichere Standardwerte fuer Farben, Typografie, Abstaende und Layout
- `base.css`: Grundgestaltung, Formulare, Schaltflaechen und Fokuszustand
- `layout.css`: Header, Footer, Hauptbereich und optionale Sidebars
- `components.css`: wiederverwendete Wiki-Komponenten
- `editor.css`: Tiptap-Editor und Tabellen
- `utilities.css`: wenige Hilfsklassen

Die statischen Werte sind immer funktionsfaehig. `/theme/active.css` kann sie
mit validierten globalen Werten ueberschreiben und gibt ausschliesslich eine
feste Liste von CSS Custom Properties aus.

Wichtige Variablengruppen sind `--color-*`, `--font-*`, `--space-*`,
`--radius-*`, `--shadow-*`, `--page-max-width`, `--content-max-width` und die
beiden `--sidebar-*-width`-Werte. Die vollstaendigen Standardwerte stehen
zentral in `static/css/tokens.css`.

## Verwaltung

Staff-Benutzer finden die Einstellungen unter `Admin > Theme-Einstellungen`.
Konfigurierbar sind Farben, Basis-Schriftgroesse, Seiten- und Inhaltsbreite,
Sidebar-Breiten, Rundungen sowie die beiden optionalen Sidebars. Die Aktion
`Standardwerte wiederherstellen` setzt eine gespeicherte Konfiguration sicher
auf die eingebauten Standardwerte zurueck.

Farben sind ausschliesslich im Format `#RRGGBB` erlaubt. Masse werden als
ganze Zahlen innerhalb der Formulargrenzen gespeichert; die CSS-Ausgabe fuegt
die Einheit `px` selbst hinzu. Freies CSS, Selektoren, URLs und CSS-Funktionen
sind nicht speicherbar. Jede Aenderung und jede Ruecksetzung erzeugt einen
Auditlog-Eintrag. Text-, Hinweis- und Primaerfarbe muessen gegen Seiten- und
Oberflaechenfarbe jeweils mindestens ein Kontrastverhaeltnis von 4,5:1
erreichen.

Die Theme-URL nutzt die letzte Aenderungszeit als Versionsparameter. Dadurch
werden neue Werte im Browser sofort geladen, ohne dass der Endpunkt vertrauliche
Einstellungen ausliefert.

## Neues Standard-Theme

Ein spaeteres vordefiniertes Theme wird in `apps/theme/defaults.py` als Satz
derselben festen Werte angelegt. Anschliessend kann es gezielt als neue
Administrationsaktion angeboten werden. Neue CSS-Variablennamen sollen nur
ergaenzt werden, wenn sie in `tokens.css`, `css_variables()` und den Tests
gemeinsam definiert sind.

## Migration und Test

Die additive Migration `apps/theme/migrations/0001_initial.py` legt nur die
Singleton-Tabelle `theme_themesettings` an. Vor dem Produktiveinsatz die
Datenbank sichern und anschliessend ausfuehren:

```bash
python manage.py migrate
python manage.py check
python manage.py test
```

Manuell pruefen: Theme-Werte aendern, Seite neu laden, alle vier
Sidebar-Kombinationen aktivieren, eine Topic-Seite mit Tabelle und Codeblock
sowie Editor, Suche, Login und Adminbereich auf schmalen und breiten
Ansichten testen. Breite Tabellen werden in einem fokussierbaren internen
Scrollbereich dargestellt, statt einen horizontalen Seitenueberlauf zu erzeugen.
Bis einschliesslich 1.024 Pixel werden aktive Sidebars unter dem Hauptinhalt
angeordnet, damit der Arbeitsbereich ausreichend breit bleibt.

Betroffene Python-Dateien liegen unter `apps/theme/`; die Einbindung erfolgt
in `config/settings.py`, `config/urls.py` und `templates/base.html`. Der
Admin-Einstieg liegt in `templates/webs/admin_dashboard.html`.

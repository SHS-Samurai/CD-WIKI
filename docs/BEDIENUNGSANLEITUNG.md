# Bedienungsanleitung

Diese Anleitung beschreibt die Bedienung des Wikis unter
`https://wiki.only-space.de`. Welche Schaltflaechen und Inhalte sichtbar sind,
haengt von den Rechten des angemeldeten Benutzers ab.

## 1. Rollen und Rechte

Das Wiki vergibt Rechte pro Web. Ein Web ist ein abgeschlossener Wiki-Bereich,
zum Beispiel `technik`, `projekte` oder `dokumentation`.

Folgende Rechte sind moeglich:

| Recht | Bedeutung |
| --- | --- |
| `view` | Web, Topics und Anhaenge ansehen |
| `create` | neue Topics im Web erstellen |
| `edit` | vorhandene Topics bearbeiten und Revisionen wiederherstellen |
| `comment` | Kommentare schreiben |
| `upload` | Dateien an Topics anhaengen |
| `manage` | Web verwalten |
| `delete` | Topics und Anhaenge in den Papierkorb verschieben |

Rechte koennen fuer einzelne Benutzer, Gruppen, alle registrierten Benutzer
oder oeffentliche Gaeste gelten. Administratoren verwalten diese Zuordnung im
Adminbereich.

## 2. Navigation

Die Kopfzeile enthaelt:

- `Wiki`: zur Web-Uebersicht
- `Webs`: zur Web-Uebersicht
- Suchfeld und `Suchen`: globale Wiki-Suche
- `Admin`: Django-Adminbereich; Zugriff nur mit ausreichenden Staff-Rechten
- `Login` oder `Logout`: Anmeldung beziehungsweise Abmeldung

Wenn Sidebars aktiviert sind, zeigt die linke Sidebar Links zu Webs und Suche.
Die rechte Sidebar enthaelt Admin- und Sitzungsfunktionen. Bis einschliesslich
1.024 Pixel Bildschirmbreite erscheint zuerst der Hauptinhalt, danach die
linke und anschliessend die rechte Sidebar.

## 3. Anmeldung und Registrierung

### Anmelden

1. In der Kopfzeile `Login` waehlen.
2. Benutzername und Passwort eingeben.
3. `Einloggen` waehlen.

Nach mehreren fehlgeschlagenen Versuchen wird die Anmeldung zeitweise
gesperrt. Die angezeigte Wartezeit muss abgewartet werden.

### Abmelden

In der Kopfzeile `Logout` waehlen. Bei aktivierter rechter Sidebar kann auch
`Sitzung beenden` verwendet werden.

### Registrieren

Auf der Login-Seite fuehrt `Registrieren` zum Registrierungsformular. Der
Administrator legt einen der folgenden Modi fest:

- Registrierung deaktiviert
- Registrierung mit Admin-Freigabe
- Registrierung mit E-Mail-Bestaetigung
- Registrierung mit automatischer Aktivierung

Je nach Modus ist der neue Benutzer sofort aktiv, muss seine E-Mail-Adresse
bestaetigen oder auf die Freigabe durch einen Administrator warten.

## 4. Webs und Topics ansehen

Die Startseite zeigt nur Webs, fuer die der aktuelle Benutzer das Recht
`view` besitzt. Ein Web kann oeffentlich, nur fuer angemeldete Benutzer oder
nur fuer festgelegte Benutzer und Gruppen sichtbar sein.

1. Auf der Startseite ein Web waehlen.
2. In der Topic-Liste das gewuenschte Topic waehlen.
3. Auf der Topic-Seite Inhalt, aktuelle Revision, letzten Bearbeiter,
   Aenderungszeit und Aenderungsnotiz lesen.

Nicht berechtigte Webs und Topics werden nicht in der Uebersicht oder Suche
angezeigt. Geschuetzte Anhaenge koennen ebenfalls nicht direkt abgerufen
werden.

## 5. Topic erstellen

Die Schaltflaeche `Neues Topic` erscheint nur mit dem Recht `create`.

1. Das Ziel-Web oeffnen.
2. `Neues Topic` waehlen.
3. Einen Slug eingeben. Der Slug ist der dauerhafte URL-Name des Topics,
   zum Beispiel `server-setup`.
4. Einen Titel eingeben.
5. Den Inhalt im Editor erfassen.
6. Eine kurze Aenderungsnotiz eingeben.
7. `Speichern` waehlen.

Der Slug muss innerhalb des Webs eindeutig sein und kann beim spaeteren
Bearbeiten nicht geaendert werden. Beim ersten Speichern entsteht Revision 1.

## 6. Topic bearbeiten

Die Schaltflaeche `Bearbeiten` erscheint nur mit dem Recht `edit`.

1. Das Topic oeffnen.
2. `Bearbeiten` waehlen.
3. Titel oder Inhalt aendern.
4. Eine Aenderungsnotiz eintragen.
5. `Speichern` waehlen.

Jedes Speichern erzeugt eine neue, unveraenderliche Revision. Die vorherige
Version bleibt erhalten. Freies HTML und Skripte koennen nicht eingegeben
werden.

### Editor-Werkzeuge

| Werkzeug | Funktion |
| --- | --- |
| `P` | Absatz |
| `H1`, `H2` | Ueberschrift |
| `B` | fett |
| `I` | kursiv |
| `UL` | Aufzaehlung |
| `OL` | nummerierte Liste |
| `Zitat` | Zitatblock |
| `Code` | Codeblock |
| `HR` | horizontale Trennlinie |
| `Link` | normalen Link setzen oder entfernen |
| `Wiki` | internen Wiki-Link im Format `web/topic` einfuegen |
| `Tabelle` | Tabelle mit Kopfzeile einfuegen |
| `Anhang` | bereits vorhandenen Topic-Anhang als Link einfuegen |

Bei einem normalen Link kann markierter Text verlinkt werden. Wird beim
Link-Dialog ein leeres Ziel bestaetigt, wird der Link entfernt.

## 7. Revisionen und Wiederherstellung

1. Auf der Topic-Seite `Historie` waehlen.
2. Eine Revision aus der Liste oeffnen.
3. Inhalt, Autor, Zeitpunkt und Aenderungsnotiz pruefen.
4. Mit `Aktuelle Seite` zur aktuellen Version wechseln.

Benutzer mit dem Recht `edit` koennen `Diese Revision wiederherstellen`
waehlen. Die alte Revision wird dabei nicht ueberschrieben: Das Wiki erzeugt
eine neue Revision mit dem wiederhergestellten Inhalt.

## 8. Dateianhaenge

### Datei hochladen

Die Schaltflaeche `Datei hochladen` erscheint mit dem Recht `upload`.

1. Das zugehoerige Topic oeffnen.
2. Im Bereich `Dateianhaenge` auf `Datei hochladen` klicken.
3. Eine Datei auswaehlen.
4. Eine Aenderungsnotiz eingeben.
5. `Hochladen` waehlen.

Unterstuetzte Formate sind PDF, DOCX, TXT, MD, XLSX und HTML. Die
Standardgrenze betraegt 25 MB. Ausfuehrbare und gefaehrliche Dateitypen werden
abgelehnt. Wird derselbe Dateiname erneut hochgeladen, entsteht eine neue
Attachment-Revision.

### Datei herunterladen

Den Dateinamen auf der Topic-Seite waehlen. Der Download wird nur ausgeliefert,
wenn das Recht `view` fuer das Web vorhanden ist.

### Datei loeschen

Mit dem Recht `delete` kann `In Papierkorb` gewaehlt werden. Die Datei wird
nicht physisch geloescht und kann durch einen Staff-Benutzer wiederhergestellt
werden.

## 9. Kommentare

Das Kommentarformular erscheint mit dem Recht `comment`.

1. Zum Bereich `Kommentare` am Ende der Topic-Seite wechseln.
2. Kommentar als Klartext eingeben.
3. `Kommentar speichern` waehlen.

Staff-Benutzer koennen Kommentare mit `Loeschen` als geloescht markieren. Der
Inhalt wird danach nicht mehr angezeigt; der Loeschhinweis bleibt sichtbar.

## 10. Suche

Die Suche kann in der Kopfzeile oder auf der Seite `Suche` verwendet werden.
Durchsucht werden unter anderem:

- Web- und Topic-Titel
- Topic-Inhalt
- Namen von Anhaengen
- extrahierter Text aus PDF, DOCX, TXT, MD, XLSX und HTML
- letzter Bearbeiter und Aenderungsinformationen

Suchergebnisse enthalten nur Topics, fuer die der aktuelle Benutzer das Recht
`view` besitzt. Gescannte PDFs ohne eingebetteten Text werden nicht per OCR
erkannt.

## 11. Topics und Anhaenge loeschen

Mit dem Recht `delete` verschiebt `In Papierkorb` ein Topic oder einen Anhang
in den Papierkorb. Es erfolgt keine sofortige physische Loeschung.

Nur Staff-Benutzer koennen die Papierkorbseiten im geschuetzten Admin-Web
oeffnen und Eintraege mit `Wiederherstellen` zurueckholen:

- `Topic-Papierkorb`
- `Dateianhang-Papierkorb`

## 12. Adminbereich

Der Link `Admin` in der Kopfzeile fuehrt zum Django-Admin. Das geschuetzte
Admin-Web `admin` bietet eine zusammengefasste Uebersicht. Beide Bereiche sind
nur fuer Staff-Benutzer bestimmt.

### Benutzer und Gruppen

- Benutzer anlegen, bearbeiten, aktivieren oder deaktivieren
- Benutzer Gruppen zuordnen
- ausgewaehlte Benutzer ueber die Admin-Aktion freischalten
- Gruppen anlegen und pflegen

### Webs

Administratoren legen Webs an und bestimmen Titel, Slug, Beschreibung und
Sichtbarkeit. Das technische Admin-Web darf nicht als normales Web verwendet
oder oeffentlich freigegeben werden.

### Rechte

Unter `Rechte` wird pro Web festgelegt:

1. welches Web betroffen ist,
2. ob das Recht fuer Benutzer, Gruppe, registrierte Benutzer oder Gaeste gilt,
3. welche Rechte `view`, `create`, `edit`, `comment`, `upload`, `manage` und
   `delete` aktiviert sind.

Nach einer Rechteaenderung sollte die betroffene Ansicht mit einem passenden
Testbenutzer geprueft werden.

### Registrierung

Unter `Registrierung` wird der globale Registrierungsmodus eingestellt. Es
existiert genau ein Einstellungsdatensatz.

### Systemlog

Das Auditlog zeigt sicherheits- und inhaltsrelevante Aktionen mit Zeitpunkt,
Benutzer, IP-Adresse, Zielobjekt, Revisionen und Detaildaten. Auditlogs sind
nur lesbar und koennen nicht ueber normale Wiki-Funktionen geaendert werden.

### Status und Wartung

Das Admin-Web enthaelt Links zu:

- Systemstatus
- Suche und Meilisearch-Status
- erlaubten Dateitypen
- registrierten Erweiterungen
- Topic- und Attachment-Papierkorb

## 13. Theme und Sidebars verwalten

Staff-Benutzer finden die globale Gestaltung unter
`Admin > Theme-Einstellungen`.

Einstellbar sind:

- Primaer-, Hintergrund-, Oberflaechen-, Text-, Hinweis- und Rahmenfarbe
- Basis-Schriftgroesse
- maximale Seiten- und Inhaltsbreite
- Breite der linken und rechten Sidebar
- Rundungsstaerke
- linke und rechte Sidebar ein- oder ausschalten

Farben muessen als Hex-Wert im Format `#RRGGBB` eingegeben werden und einen
ausreichenden Kontrast besitzen. Unsichere oder ungueltige Werte werden nicht
gespeichert.

Mit der Admin-Aktion `Standardwerte wiederherstellen` wird die gesamte
Theme-Konfiguration zurueckgesetzt. Theme-Aenderungen werden im Auditlog
aufgezeichnet.

Bei zwei aktiven Sidebars sollte die maximale Seitenbreite erhoeht werden. Auf
Bildschirmen bis einschliesslich 1.024 Pixel werden die Sidebars automatisch
unter den Hauptinhalt verschoben.

Weitere technische Details stehen in `docs/THEMES.md`.

## 14. Typische Meldungen

### Keine Webs sichtbar

Der Benutzer besitzt fuer kein Web das Recht `view`. Ein Administrator muss
Sichtbarkeit oder Rechte pruefen.

### Schaltflaeche fehlt

Schaltflaechen fuer Erstellen, Bearbeiten, Kommentieren, Hochladen und Loeschen
werden nur mit dem jeweils erforderlichen Recht angezeigt.

### Zugriff verweigert

Der Benutzer ist nicht angemeldet, besitzt nicht das erforderliche Web-Recht
oder versucht auf eine Staff-Funktion zuzugreifen.

### Suchdienst nicht erreichbar

Meilisearch ist nicht verfuegbar. Ein Administrator muss Dienst und
Systemstatus pruefen. Die normalen Wiki-Inhalte bleiben davon unberuehrt.

### Datei wird abgelehnt

Dateityp, MIME-Type, Dateigroesse oder Dateiinhalt entspricht nicht den
Uploadregeln. Einen erlaubten Dateityp verwenden oder den Administrator
kontaktieren.

## 15. Empfohlener Funktionstest fuer Administratoren

Nach Deployment oder groesseren Aenderungen mindestens pruefen:

1. Login und Logout.
2. Oeffentliches und privates Web mit verschiedenen Benutzern.
3. Topic erstellen und bearbeiten.
4. Historie anzeigen und Revision wiederherstellen.
5. Anhang hochladen, herunterladen und schuetzen.
6. Kommentar erstellen und als Staff-Benutzer loeschen.
7. Suche nach Topic- und Anhangtext.
8. Topic und Anhang ueber den Papierkorb wiederherstellen.
9. Theme aendern und Standardwerte wiederherstellen.
10. Keine, linke, rechte und beide Sidebars auf Desktop und Mobilgeraet.

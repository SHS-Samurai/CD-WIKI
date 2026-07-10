# Sicherheit

## Grundregeln

- Keine Secrets im Repository speichern.
- `DEBUG=False` im Produktivbetrieb.
- Rechte immer serverseitig pruefen.
- Web-Rechte werden pro Web ueber `WebPermission` geprueft.
- Topic-Sonderrechte werden in Version 1 nicht verwendet.
- Topic-Inhalte werden als validiertes ProseMirror-/Tiptap-JSON gespeichert.
- Freie HTML-Knoten und gefaehrliche Link-Schemata werden im Topic-Inhalt abgelehnt.
- Revisionen werden nur nach `view`-Rechtepruefung angezeigt; Wiederherstellung braucht `edit` und erzeugt ein Auditlog.
- Topic-Loeschungen brauchen das Web-Recht `delete`, sind Soft-Deletes und schreiben ein Auditlog.
- Topic-Wiederherstellung aus dem Papierkorb ist Staff-Benutzern vorbehalten.
- Auditlogs sind im Adminbereich nur lesbar und werden nicht ueber normale Wiki-Funktionen veraendert.
- Web- und Topic-Views liefern private Inhalte nur nach serverseitiger Rechtepruefung aus.
- Das Admin-Web `admin` bleibt privat und wird vor Auslieferung serverseitig auf Staff-Zugriff geprueft.
- Private Webs und Attachments duerfen nicht direkt aus oeffentlichen Pfaden ausgeliefert werden.
- Downloads laufen ueber Django-Views mit Rechtepruefung.
- Attachment-Downloads laufen ueber eine Django-View mit `view`-Rechtepruefung.
- Attachment-Uploads pruefen Dateinamen, Pfade, erlaubte Endungen und Dateigroesse.
- Attachment-Loeschungen brauchen das Web-Recht `delete`, sind Soft-Deletes und schreiben ein Auditlog.
- Attachment-Wiederherstellung aus dem Papierkorb ist Staff-Benutzern vorbehalten.
- Uploads werden auf Endung, MIME-Type, Groesse und sicheren Dateinamen geprueft.
- Kommentare werden als Klartext gespeichert und im Template escaped ausgegeben.
- Kommentar-Erstellung prueft `view` und `comment`; Kommentar-Loeschung ist auf Staff-Benutzer begrenzt und erfolgt als Soft-Delete.
- Registrierung kann im Adminbereich deaktiviert oder auf Admin-Freigabe, E-Mail-Bestaetigung oder automatische Aktivierung gesetzt werden.
- Registrierungsformulare nutzen Honeypot, Mindestzeit und ein persistentes IP-Rate-Limit.
- Fehlgeschlagene Logins werden pro IP begrenzt; erfolgreiche Logins setzen den Zaehler zurueck.
- Rate-Limit-Schluessel werden mit HMAC pseudonymisiert und enthalten keine lesbaren IP-Adressen.
- `X-Forwarded-For` wird nur von Eintraegen aus `WIKI_TRUSTED_PROXY_IPS` ausgewertet.
- E-Mail-Bestaetigungen speichern nur Token-Hashes in MySQL.
- Die Suche wird ausschliesslich ueber Django ausgeliefert; Browser erhalten keinen direkten Zugriff auf Meilisearch.
- Suchtreffer werden nach der Meilisearch-Abfrage serverseitig gegen das `view`-Recht des Benutzers gefiltert.
- CSRF-Schutz bleibt aktiv.
- Auditlogs werden nicht ueber normale Wiki-Funktionen veraendert.

## Konfiguration

Produktive Werte gehoeren in `.env` oder in sichere Server-Umgebungsvariablen.
Die Datei `.env.example` enthaelt nur Platzhalter.

Gunicorn oder uWSGI darf nur hinter dem konfigurierten Reverse Proxy erreichbar
sein. Neue Proxy-Adressen muessen explizit in `WIKI_TRUSTED_PROXY_IPS` stehen.

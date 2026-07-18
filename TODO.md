# Release-Status

## Abgeschlossen

- Laravel-13-Anwendung mit Breeze, Blade, Tailwind CSS und Tiptap
- Webs und fein abgestuftes Rechtevergabesystem
- Artikel, Revisionen, Wiki-Links, Kategorien und Kommentare
- versionierte Anhänge und Volltextsuche einschließlich extrahierter Anhangtexte
- Auditlog, Papierkorb, Layout- und Theme-Verwaltung
- geschützte Ersteinrichtung mit automatischer lokaler MySQL-Datenbankanlage
- konsistente Backups mit Prüfsummen und Tiefenprüfung
- Sicherheits-, Funktions-, Build- und Formatprüfungen

## Vor jeder Produktionsfreigabe

- MySQL-Integrations- und Parallelitätstests gegen eine separate `_test`-Datenbank ausführen
- Wiederherstellung eines Backups in Staging proben
- Lasttest und fachliche Abnahme auf der Zielumgebung durchführen

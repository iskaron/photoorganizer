# Architekturübersicht (ENTWURF)

Stand: 2026-10-09 · Runde 1 · **Status: technischer Entwurf [T] – nichts hierin ist beschlossen.** Beschlossenes steht in `decisions.md`.

## 1. Bewertung der Vorüberlegungen aus dem Auftrag

| Vorüberlegung | Bewertung | Anmerkung |
|---|---|---|
| Native Nextcloud-App, PHP-Backend | Empfohlen | Nur so sind Datei-API, Events, Background Jobs, Benutzerkontext nutzbar. |
| Vue/TypeScript-Frontend | Empfohlen | Üblicher Weg; Versionen an Zielversion koppeln (OQ-002). |
| Nextcloud-DB statt SQLite | Stark empfohlen | Mehrere gleichzeitige Cron-Worker, Transaktionen, atomare Nummernreservierung per Unique-Constraint, Backup zusammen mit Nextcloud. Risiko: Portabilität über MySQL/MariaDB/PostgreSQL – Query-Builder statt Roh-SQL, keine DB-spezifischen Features. |
| Eigene Tabellen mit Migrationen | Empfohlen | Datenmodell wird in Runde „data-model“ ausgearbeitet. Achtung bei sehr großen Tabellen (Indizes, Oracle-Namenslänge, `string`-Längenlimits für Pfade). |
| Background Jobs + Event-Listener | Empfohlen mit Vorbehalt | Nextcloud-Jobs sind kurz laufend, gestartet durch Cron (typisch alle 5 Min.). Durchsatz für Hashing von Terabytes ist begrenzt → `occ`-Worker für Bulk. |
| Zusätzliche `occ`-Befehle | Empfohlen | Für Erstindexierung, Neuabgleich, Diagnose. `occ` läuft ohne Request-Kontext (CLI) und ohne Web-Timeouts. |
| Benutzer-API und getrennte Admin-API | Empfohlen | Getrennte Controller und DTOs (Admin-DTOs strukturell ohne Medienfelder). |
| Storage-unabhängige Dateioperationen | Empfohlen | Entspricht DEC-NCI-001/002. Grenzen: Operationen über Storage-Grenzen sind Copy+Delete statt Rename (nicht atomar) – daher Home-Storage-only (REQ-SRC-06). |

## 2. Vorgeschlagene Gesamtarchitektur [T]

```text
 Nextcloud Core
 ├─ Files API (OCP\Files)      ◄── ausschließlicher Dateizugriff (DEC-NCI-001)
 ├─ Events (Node*Event)       ───► Listener: schreibt nur Queue-Eintrag
 ├─ Background Jobs / Cron    ───► Worker (Scheduler + Batch-Worker)
 └─ occ                       ───► Wartungs-/Bulk-Befehle

 Photo Organizer (App)
 ├─ Listener        : Event → ChangeQueue (leicht, keine Hashes)
 ├─ Scanner         : inkrementell/voll, erzeugt Observations
 ├─ Analyzer        : SHA-256, Metadaten, pHash, Gruppierungserkennung
 ├─ Planner         : Regelauswertung → Dry-Run-Plan (reine Berechnung)
 ├─ Executor        : führt freigegebene Plan-Items aus (Journal first)
 ├─ Journal/Undo    : Write-Ahead-Operationslog, Zustandsprüfung
 ├─ Review service  : Quarantäne, Konflikte, Entscheidungen
 ├─ User API (OCS/Controller)  ◄── Vue-Frontend
 └─ Admin API (getrennt, nur Aggregate)

 Datenhaltung: Nextcloud-DB (app-eigene Tabellen), Appdata für Thumbnails/Analyseartefakte
```

### Verarbeitungspipeline (Skizze) [T]

1. **Erkennen:** Event/Scan → `observation(file_id, size, mtime, etag, first_seen_at)`.
2. **Stabilisieren:** Mindestalter + zwei gleiche Beobachtungen (DEC-STB).
3. **Analysieren:** SHA-256 (Streaming), Metadaten, pHash (nur Bilder), Gruppenzuordnung.
4. **Planen:** Regeln der Quelle (freigegebene Regelversion) → Ziel, Name, Dedup-Status.
5. **Freigabe:** Dry-Run-Plan (initial) bzw. gültige dauerhafte Freigabe (laufend).
6. **Ausführen:** Revalidieren → Journal-Intent → Move/Rename → Journal-Ergebnis.
7. **Prüffälle:** Unsicheres, Konflikte, Fehler → Prüfbereich.

### Grundprinzipien [T]

- **Plan/Execute-Trennung:** Der Planner ist reine Funktion über den Index; nur der Executor mutiert.
- **`file_id` als Identität, Pfad als Attribut.**
- **Fail-safe:** Bei Zweifel nichts tun, Prüffall erzeugen (NFR-INT-01).
- **Idempotente Jobs, Zustandsautomat je Datei/Operation.**
- **Self-Origin-Unterdrückung:** Jede App-Operation hat eine Operations-ID; der Listener ignoriert Events, die dem Journal als erwarteter Nachzustand entsprechen (DEC-NCI-005, Mechanismus offen; Details in `nextcloud-integration.md`).

## 3. Schichten und Trennung der Berechtigungen [T]

| Schicht | Verantwortung | Zugriff auf Medieninhalte |
|---|---|---|
| User-API | Benutzerspezifische Daten, strikt `user_id = Sitzungsbenutzer` | Ja (eigene) |
| Admin-API | Zähler, Zustände, Pausen | **Nein** (strukturell) |
| Jobs/occ | Arbeiten im Kontext eines einzelnen Benutzers | Ja, im Benutzerkontext |

## 4. Technische Risiken (Auszug, Details in späteren Runden)

| ID | Risiko | Schwere | Gegenmaßnahme (Vorschlag) |
|---|---|---|---|
| RISK-INT | Datenverlust/Fehlsortierung durch Race Conditions zwischen Prüfung und Move (TOCTOU), insbesondere bei Client-Sync | Hoch | Revalidierung unmittelbar vor Mutation, Dateisperren der Nextcloud nutzen, Rename statt Copy+Delete, Journal-Intent, `file_id`-Abgleich |
| RISK-TRASH | Papierkorb-Schnittstelle nicht öffentlich/stabil → Löschen/Wiederherstellen/Undo unsicher (CONFLICT-006) | Hoch | OQ-005; Aktion verweigern, wenn Papierkorb nicht verifizierbar |
| RISK-LOOP | Verarbeitungsschleifen durch eigene Events (DEC-NCI-005) und Konflikte mit Client-Sync (Rename wird zu Re-Download) | Hoch | Operations-ID/Erwartungszustand im Journal; Debounce |
| RISK-PERF | Hashing/pHash/Metadaten großer Bestände im Cron-Kontext sprengt Zeit-/Speicherlimits; Object-Storage lädt Dateien zum Hashen vollständig | Hoch | Kleine Batches, `occ`-Worker, Hash-Cache, Streaming, Fairness-Budget |
| RISK-ADM | Admin-Privacy: Logs, Exceptions, Job-Argumente enthalten Pfade; Admins haben Zugriff außerhalb der App-UI | Mittel | Log-Hygiene, DTO-Tests, ehrliche Dokumentation, dass DEC-USR-009 die App-Oberfläche betrifft |
| RISK-GRP | Fehlerkennung von Mediengruppen (Live Photo/RAW) führt zu falscher Zusammenlegung | Mittel | Konservative Regeln, Unsicheres im Eingang (DEC-GRP-003) |
| RISK-META | Metadatenparser (EXIF/QuickTime/HEIC) in PHP lückenhaft; externe Tools erhöhen Angriffsfläche und Abhängigkeiten | Mittel | Parserwahl in `nextcloud-integration.md`/`performance.md` evaluieren; Sandboxing |
| RISK-VER | Abhängigkeit von Nextcloud-Interna; API-Änderungen zwischen Hauptversionen | Mittel | Supportmatrix, CI gegen Zielversionen, Adapter-Schicht um OCP/OCA-Zugriffe |
| RISK-NAME | Namensschema-Kollisionen, Pfadlängen, Unicode, Windows-Kompatibilität | Mittel | Zentrale Namensfunktion mit Property-Tests, Reservierungstabelle |
| RISK-MT | Mandantenfehler (Cross-User-Zugriff, falscher Kontext in Jobs) | Hoch | Zentraler `UserScope`, erzwungener `user_id`-Filter, Tests |

## 5. Zu verifizierende Annahmen

Siehe `nextcloud-integration.md` (API-Status) und `open-questions.md` (OQ-002, OQ-005, OQ-006, OQ-029).

---

# Ergänzungen Runde 2 (Entwurf [T])

Nichts in diesem Abschnitt ist beschlossen. Fachlich beschlossene Klarstellungen aus Runde 2 stehen in `decisions.md` Abschnitt E.

## 6. Journal- und Recovery-Konzept

### 6.1 Das Problem
Eine DB-Transaktion kann eine Dateisystemoperation nicht einschließen. Der Ablauf „Datei verschieben, dann DB aktualisieren“ kann an drei Stellen abbrechen: vor der Operation, zwischen Operation und DB-Commit, nach Commit. Außerdem besteht die Operation selbst aus Dateicache- und Storage-Änderung (Nextcloud-intern, nicht von uns kontrollierbar). Gleichzeitig gelten:

| Eigenschaft | Konsequenz |
|---|---|
| Unveränderlichkeit (DEC-IMM) | Es gibt nur Move/Rename/Trash. Kein Schreiben, daher kann kein Zwischenzustand Inhalte beschädigen; Fehler äußern sich nur als „Datei an unerwartetem Ort/Namen“. |
| Gruppenkonsistenz (DEC-GRP-009/010) | Eine Gruppenoperation besteht aus mehreren Dateioperationen und ist nie atomar. |
| Idempotenz/Wiederaufnahme (DEC-JOB-006, DEC-JNL-004) | Jeder Schritt muss anhand des beobachtbaren Zustands entscheidbar sein. |

### 6.2 Grundmuster: Intent-Journal mit Roll-forward [T]
1. **Plan-Item → Operation.** Jede Mutation erhält eine `operation_id` (stabil, vor Ausführung vergeben).
2. **Intent persistieren (eigene, committete DB-Transaktion) vor der Dateioperation**: `operation_id`, Typ (`move`, `rename`, `trash`, `reassign`, `undo`), `file_id`, **erwarteter Vorzustand** (Pfad, Größe, MTime, SHA-256), **Sollzustand** (Zielpfad/-name), zugehöriger Plan/Regelversion, Gruppen-ID, reservierte Kollisionsnummer. Status `intended`.
3. **Revalidieren** (DEC-STB-003): Knoten per `file_id` laden, Eigentümer/Mount prüfen, Vorzustand vergleichen, Ziel darf nicht existieren. Bei Abweichung: Status `aborted_precondition`, kein Eingriff, Prüffall.
4. **Ausführen** über `Node::move()` (Status vorher `executing`, auf demselben Weg wie 2.).
5. **Ergebnis persistieren**: Beobachteter Nachzustand (Pfad, `file_id`, Größe, MTime, Etag), Status `done`.
6. **Event-Abgleich**: Der Listener sieht das erwartete Event, findet die passende Operation (`file_id` + Zielpfad) und erzeugt keinen Folgejob (DEC-NCI-005).

### 6.3 Recovery-Algorithmus (beim Start jedes Workers und im Kontrollscan) [T]
Für jede Operation im Status `intended` oder `executing`, die älter als eine Karenzzeit ist, per `file_id` beobachten:

| Beobachtung | Schluss | Aktion |
|---|---|---|
| Datei liegt am Vorzustand, unverändert | Operation nicht ausgeführt | wiederholen (Schritt 3) oder bei Pause/Planänderung `aborted` |
| Datei liegt am Sollzustand | Operation ausgeführt, Ergebnis nicht gespeichert | als `done` nachtragen (`recovered`) |
| Datei ist weder an Vor- noch Sollort | externe Änderung oder Teilfehler | Status `needs_review` (Konflikt), Quelle ggf. pausieren (DEC-JOB-011) |
| `file_id` existiert nicht mehr | gelöscht oder ID-Wechsel | Prüffall; kein Auto-Reparieren über Dateinamen |
| Ziel durch fremde Datei belegt | Konflikt | `needs_review`; niemals überschreiben (DEC-JNL-008) |

**Roll-forward statt Rollback.** Unterbrochene Operationen werden fortgesetzt oder zur Prüfung gestellt. Ein Rückgängigmachen ist ausschließlich eine **neue** Operation (`undo`, DEC-JNL-006), die denselben Mechanismus nutzt, mit Vor-/Sollzustand vertauscht und zusätzlicher Prüfung, dass der aktuelle Zustand dem Nachzustand des Originals entspricht (DEC-JNL-005).

### 6.4 Gruppenoperationen
- Eine Gruppenoperation ist eine **übergeordnete** Operation mit je einer Kindoperation pro Komponente. Alle Kinder werden vor Beginn als Intent geschrieben, Ziel-Basisname und Nummer sind vorab für die gesamte Gruppe reserviert.
- Reihenfolge: stabil (Hauptdatei zuletzt oder zuerst – Entscheidung offen), jede Kindoperation einzeln idempotent.
- Zwischenzustand „Gruppe teilweise bewegt“ ist ein **gültiger, erkennbarer** Zustand (Gruppe `inconsistent_pending`). Er darf nicht als manuelle Verschiebung gedeutet werden; Recovery setzt fort. Erst wenn die Elternoperation `done` ist, gilt die Gruppe als konsistent.
- Jede Komponente prüft einzeln ihren Vorzustand; scheitert eine, bleibt die Gruppe im Zustand `needs_review` – bereits bewegte Komponenten werden nicht automatisch zurückbewegt.

### 6.5 Unveränderlichkeit prüfbar machen
- SHA-256 wird vor dem ersten Mutationsschritt gespeichert und bei Folgeschritten gegen die unveränderte Signatur geprüft; bei geänderter Signatur wird neu gehasht, nicht stillschweigend übernommen.
- Die Plattformgrenzen: Die App ruft weder `putContent`-artige noch `fopen(...,'w')`-artige Methoden auf Medien auf (Codereview-Regel, statische Analyse/Test: Verbotsliste für Schreib-APIs in Medienpfaden).

### 6.6 Sperren (ergänzend)
Während der kurzen Mutationsphase kann `Node::lock(LOCK_EXCLUSIVE)` (öffentliche API) zusätzlich genutzt werden; Wirkung gegen Clients ist nicht verifiziert (`nextcloud-integration.md` §2.6). Die Korrektheit darf davon **nicht** abhängen.

### 6.7 Worker-Leasing
Pro `(user_id)` und optional pro `(user_id, source_id)` gibt es eine DB-Lease (Besitzer, Ablaufzeit, Heartbeat). Zeitgesteuerte Jobs prüfen die Lease; `setAllowParallelRuns(false)` allein genügt nicht, weil es nur Instanzen desselben Jobs betrifft und nicht `occ`-Worker.

## 7. Bewertung: PHP-only vs. externe Werkzeuge

| Aufgabe | PHP-native | Externe Werkzeuge | Empfehlung [T] |
|---|---|---|---|
| SHA-256 | `hash_init`/`hash_update_stream`/`hash_final` per Stream | – | PHP-nativ, Streaming |
| EXIF (JPEG/TIFF) | Extension `exif` (Aufnahmezeit, Offsets teils lückenhaft) | ExifTool (sehr vollständig: HEIC, RAW, XMP, QuickTime, Apple Content Identifier) | Basis nativ, ExifTool optional |
| Videometadaten (MP4/MOV) | Eigener Atom-Parser (`mvhd`, `keys/ilst`) machbar, aber wartungsintensiv | `ffprobe` oder ExifTool | optional, mit PHP-Fallback `mvhd` (UTC) |
| Live-Photo-Zuordnung (Content Identifier in HEIC/MOV) | nur mit eigenem Parser | ExifTool | ExifTool bevorzugt; ohne ExifTool nur Dateinamen-/Zeit-Heuristik (→ unsichere Gruppen bleiben im Eingang, DEC-GRP-003) |
| pHash JPEG/PNG | GD/Imagick-Decode, DCT in PHP (für ~10⁵ Bilder langsam, aber machbar) | – | PHP mit Imagick/GD |
| pHash HEIC/RAW | nur mit Imagick+libheif/libraw | `dcraw`/`libraw`/ExifTool `PreviewImage` | optional; ohne Decoder wird kein pHash berechnet (Bild bleibt gültig) |
| Vorschaubilder UI | `IPreview` | – | Nextcloud-Mechanismus |

Kritische Bewertung:
- **Alles in PHP zu erzwingen** würde HEIC/RAW/Videos/Live-Photos nur lückenhaft abdecken. **Alles auf externe Tools zu stützen** vergrößert Angriffsfläche (Parser-Schwachstellen in Binärformaten), Betriebs- und Versionsabhängigkeit; auf Servern ohne Shell-Funktionen (`proc_open` deaktiviert) nicht möglich.
- Empfehlung: **Capability-Abstraktion** (`MetadataProvider`, `PerceptualHasher`) mit PHP-Basis; optionale Provider werden bei Start per Capability-Probe erkannt und im UI/Admin-Zustand als Fähigkeit (nicht als Medieninhalt) angezeigt. Fehlende Fähigkeit bedeutet *kein* Import-Block, sondern konservative Behandlung (DEC-GRP-003/005, DEC-PHA-004).
- Aufruf externer Tools nur mit Argumentliste (`proc_open` ohne Shell), Zeit-/Speicherlimit, kein Schreiben ins Benutzerverzeichnis, Eingabe über temporäre Kopie bzw. Stream aus der Datei-API (kein Datadir-Pfad, DEC-NCI-002). Der Aufwand der Kopie ist Teil von `performance.md`.
- pHash-Stabilität: Der Hash muss aus dem **Originalpixelstrom** (nach standardisierter Dekodierung, ohne Nextcloud-Preview) berechnet werden, damit er unabhängig von Nextcloud-Updates reproduzierbar bleibt; Algorithmus-ID/-Version wird mitgespeichert.
- Abhängigkeit zur Zielumgebung: Welche Provider verfügbar sind, entscheidet `environment.md`.

## 8. Technische Ausarbeitungen zu P0-Fragen (Vorschläge [T])

### 8.1 Kollisionsnummer (OQ-011)
Ausgangsvorschlag des Product Owners: Reservierung pro Benutzer, Zielverzeichnis, Quellenpräfix und Zeitstempel, gemeinsam für alle Komponenten einer Gruppe.

**Prüfung:**

| Prüfpunkt | Ergebnis |
|---|---|
| Reservierungsschlüssel | `(user_id, target_dir_key, source_prefix_key, local_ts_second)`. `target_dir_key` und `source_prefix_key` normalisiert (Unicode NFC, Case-Folding wegen Windows-Kompatibilität). Da das Zielverzeichnis selbst aus Jahr/Monat/Quelle folgt, ist `target_dir_key` aus Zeitstempel und Quelle abgeleitet, aber bei `location_override` kann es abweichen → daher Teil des Schlüssels. |
| Atomarität | Tabelle der vergebenen Nummern mit Unique-Constraint auf `(Schlüssel, seq)`. Vergabe: `seq = max+1`, `INSERT`; bei Unique-Verletzung erneut. Funktioniert portabel auf allen Nextcloud-Datenbanken ohne Sequenzen oder Sperrtabellen. |
| Nie wiederverwendet | Zeilen werden **nie gelöscht**; Löschen/Papierkorb/Undo/Abbruch lassen die Reservierung stehen („verbrannt“). Lücken sind erlaubt. |
| Gruppe | Eine Reservierung je Gruppe; alle Komponenten erhalten dasselbe `seq`. Reservierung gehört zur `media_id`/`group_id`. |
| Kollision mit fremden Dateien | Vor Move wird das Ziel geprüft; existiert dort bereits (manuell angelegt) der Name, wird die Nummer als verbrannt markiert und die nächste genommen. |
| Neuzuordnung (Reassign) ändert Zeitstempel/Quelle | Der Schlüssel ändert sich → **neue** Reservierung; die alte bleibt verbrannt. Nummer gehört damit zum Namen *in dieser Phase*, nicht dauerhaft zum Medium. Das widerspricht `DEC-NAM-009` nicht (keine Umnummerierung *innerhalb* desselben Namens), ist aber eine **Auslegung**, die bestätigt werden muss (OQ-011, Teilfrage c). |
| Manuelle Verschiebung (location_override) | Name bleibt unverändert, keine neue Nummer. |
| Wiederholter Plan (Dry-Run) | Der Plan zeigt nur *vorläufige* Nummern. Verbindliche Reservierung erfolgt erst bei Ausführung (sonst würden Dry-Runs Nummern verbrennen). Folge: Namen im Plan können sich bei Ausführung ändern, wenn zwischenzeitlich Kollisionen entstehen; Freigabe bezieht sich auf die Regel, nicht auf die letzte Nummer. Entscheidung offen (OQ-031). |
| Zwei verschiedene Dateien, gleicher Zeitstempel | bekommen 01, 02 – Reihenfolge deterministisch (z. B. SHA-256) im Dry-Run, bei Ausführung nach Reservierungsreihenfolge. |
| Gruppenerweiterung (neue Komponente) | übernimmt vorhandene Nummer der Gruppe; bei bereits belegtem Zielnamen → Prüffall. |
| Nummernformat | zweistellig im Beispiel (`01`); Überlauf >99 → dreistellig (OQ-011, Teilfrage d). |

### 8.2 Zeitspeicherung (OQ-012)
Getrennt zu speichern (Feldnamen als Vorschlag, noch kein SQL):

| Feld | Inhalt |
|---|---|
| `time_raw` | Originalwert als Text (z. B. `2026:10:09 13:12:12`), plus Rohwert des Offsets (`+02:00`) |
| `time_source` | Enum: `exif_datetime_original`, `exif_create_date`, `quicktime_creation_date`, `quicktime_mvhd`, `mtime`, … |
| `utc_offset_minutes` | nullable; nur wenn vorhanden |
| `local_time_naive` | lokale Wanduhrzeit **ohne** Zeitzone, als Text/Integer „Sekunden als wären UTC“ – niemals als Zeitzonen-konvertierter DB-Zeitstempel |
| `utc_instant` | nullable; nur bei Offset oder UTC-Quelle (z. B. MTime, QuickTime-UTC) |
| `sort_time_basis` | Enum: `floating_local`, `offset_local`, `user_tz_from_utc` – wie `local_time_naive` entstanden ist |
| `user_tz_used` | verwendete Benutzerzeitzone (IANA-Name) zum Zeitpunkt der Berechnung, falls umgerechnet |

Damit sind Originalwert, Quelle, Offset und tatsächlich verwendete lokale Sortierzeit getrennt darstellbar. Prüfpunkt: Wird später die Benutzerzeitzone geändert, bleiben abgeleitete Pfade unverändert (keine automatische Neuorganisation, DEC-DRY-007); die Zeitzone ist Teil der „abhängigen globalen Einstellungen“ der Regelversion (8.3).

### 8.3 Regelversionierung und gezielte Invalidierung (OQ-023)
- Freigabe bindet an `rule_fingerprint` pro Quelle = Hash über **nur relevante Bestandteile**: Datumsstrategie und -parameter, Namensschema, Beschreibungsregeln/Muster, Zielstruktur, Gruppierungsregeln, Quellordner, Quellenname, Priorität *relativ zu konkurrierenden Quellen*.
- **Abhängige globale Einstellungen** (Teil des Fingerprints jeder Quelle, die sie nutzt): Sammlungsordner, Benutzerzeitzone, Muster „automatisch erzeugter Name“.
- **Nicht** im Fingerprint: Anzeigename ohne Wirkung auf Pfade, Mindestalter/Stabilitätsparameter, Pausen, UI-Optionen. (Mindestalter *verlängert* nur Wartezeit; ein verkürztes Mindestalter erhöht Risiko – offene Frage, ob es doch invalidieren soll, OQ-023.)
- **Priorität** ist relational: Wird Quelle A priorisiert, ändert sich die Rangfolge gegenüber B, C. Invalidiert werden nur Quellen, deren relative Rangfolge zu einer anderen **aktiven** Quelle sich geändert hat (Paarvergleich).
- Der Fingerprint wird bei jeder Konfigurationsänderung neu berechnet; nur wenn er sich ändert, wird die Freigabe ungültig (Quelle pausiert bis zur neuen Freigabe, OQ-023). Zuvor freigegebene Dateiebenen-Freigaben historischer Importe (DEC-DRY-003) sind an Datei-Signatur und Plan gebunden, nicht an die Quelle, und bleiben unberührt.
- Die Vergleichsimplementierung ist reine Funktion (testbar), siehe `testing.md` später.

### 8.4 Erkennung manueller Verschiebungen (OQ-006)
1. Jede verwaltete Datei hat `last_known_path`, `last_known_parent_id`, `expected_by_operation`.
2. Event `NodeRenamedEvent` (Quelle/Ziel) → Queue-Eintrag. Verarbeitung: gibt es eine Journal-Operation mit passender `file_id`+Ziel → eigene Aktion. Sonst: manuell.
3. Kontrollscan (täglich) gleicht `file_id → Pfad` mit `last_known_path` ab; Abweichungen ohne Operation = manuell (auch wenn Events fehlten, z. B. `occ files:scan` nach externer Änderung). **Scan ist Wahrheit, Events sind Beschleuniger.**
4. Manuelle Verschiebung innerhalb der Sammlung: `location_override` setzen, Gruppennachzug nach Verzögerung (DEC-GRP-010). Außerhalb der Sammlung: Prüffall (DEC-GRP-011). In den Papierkorb: Löschentscheidung → Tombstone (DEC-DUP-008).
5. Voraussetzung: `file_id`-Stabilität bei Move (`nextcloud-integration.md` §2.2, ungeprüft).

### 8.5 Tombstone-Grundsatz (OQ-003, Datenmodell offen)
Ein Tombstone ist eine dauerhafte Löschentscheidung zu einer Inhaltsidentität `(user_id, sha256)`: Art (manuell gelöscht/App-Papierkorb), Zeit, ursprüngliche Herkunft. Wiederauftauchen desselben Inhalts oder eine Quarantänekopie darf nicht automatisch wieder kanonisch werden. Offen: Aufhebung nur manuell? Gilt der Tombstone je Komponente? Skizze nur; Modellierung später.

---

# Ergänzungen Runde 2b (Entwurf [T], sofern nicht auf `DEC-…` verwiesen)

Änderungen gegenüber Runde 2: Die Architektur ist für eine **veröffentlichungsfähige, allgemeine App** auszulegen (`DEC-PRD-006`); die Referenzumgebung (`environment.md`) ist nur Testziel. Wo frühere Abschnitte (§6–§8) im Widerspruch zu den folgenden stehen, gelten die Abschnitte 9–14; §8.3 (Fingerprint) und §8.5 (Tombstone) werden dadurch präzisiert, nicht ersetzt.

## 9. Berechtigung: `processing_eligible` vs. `provenance_eligible` (`DEC-DAT-013`)

| Eigenschaft | Frage | Hängt ab von | Hängt **nicht** ab von |
|---|---|---|---|
| `processing_eligible(source)` | Darf jetzt gescannt, analysiert, ausgeführt werden? | Benutzer aktiviert, Benutzerpause, Quellenpause, Admin-Pause (Benutzer/global), Quelle nicht in `approval_required`, Lease/Fairness, kritischer Fehler (DEC-JOB-011) | Herkunftsbewertung |
| `provenance_eligible(origin)` | Zählt diese dokumentierte Herkunft bei der Bestimmung des kanonischen Ablageortes? | Herkunftsdatensatz existiert; der Herkunft ist die **zum Importzeitpunkt freigegebene Regelversion** zugeordnet; Quelle (bzw. ihre Historie) ist nicht gelöscht/zurückgezogen | **Jede Pause** (Benutzer, Quelle, Admin, global), Deaktivierung durch Benutzer |

Konsequenzen [T]:
- Der **Soll-Ablageort** eines Mediums ist eine reine Funktion der `provenance_eligible`-Herkünfte (höchste Priorität → deren Datumsstrategie, DEC-DAT-011) und der Overrides (DEC-DAT-012). Eine Pause ändert dieses Ergebnis nie.
- **Ausführen** (Move/Rename/Reassign) setzt zusätzlich `processing_eligible` der betroffenen Quellen und des Benutzers voraus. Während einer Pause wird nichts verschoben; nach Ende der Pause wird derselbe Soll-Ort erreicht wie ohne Pause. Pausen erzeugen daher **keine** Neuzuordnung (DEC-DRY-014).
- Eine Regeländerung, die eine neue Freigabe erfordert, setzt die Quelle auf `approval_required`: `processing_eligible = false` für **neue** Verarbeitung nach der neuen Regel. Bereits dokumentierte Herkünfte bleiben `provenance_eligible` und werden weiter nach der damals freigegebenen Regelversion bewertet, bis eine neu freigegebene Version oder ein genehmigter Reorganisationsplan (DEC-DRY-008) etwas anderes festlegt.
- Offen (`OQ-036` Restfragen): Verhalten bei **gelöschter** Quelle (Historie behalten, aber `provenance_eligible`?), bei Quellenumbenennung.

## 10. Tombstones: Anforderungen an das Modell (`DEC-DUP-009`)

Kein Schema (Auftrag 3). Ein einzelner Eintrag `(user_id, sha256)` reicht nicht, weil folgende Fragen beantwortbar sein müssen:

| Anforderung | Warum |
|---|---|
| **Löschereignis** als eigenes Ereignis (wer: Benutzer/App-Aktion; wann; wie: Löschen in Nextcloud, Papierkorb durch App-Sammelaktion, Verschieben aus der Sammlung; Quelle des Hinweises: Event oder Scan) | Nur manuelle, bewusste Löschung begründet einen Schutz; Papierkorb-Sammelaktionen für **Duplikatkopien** dürfen den Schutz der kanonischen Identität nicht auslösen, weil das kanonische Exemplar dort erhalten bleibt. |
| Verweis auf die **bisherige kanonische Datei** (`file_id`, Pfad zum Löschzeitpunkt, Name) | Nachvollziehbarkeit, Undo-/Restore-Hinweis, Abgrenzung zu später neu importierten Dateien |
| **Inhaltsidentität** (`sha256`) | Erkennung des erneuten Auftauchens |
| **Herkunftssnapshot** (Quellen, Prioritäten, Regelversion) | Bewertung, ob der Wiederauftritt aus einer anderen, höher priorisierten Herkunft kommt (informativ; kein Automatismus) |
| **Gruppenbezug** (Gruppe, Komponentenrolle) | Löschung einer Komponente betrifft nur diese Identität (DEC-GRP-013); die Gruppe darf weder gelöscht noch aufgelöst werden |
| **Wiederzulassung** als eigenes Ereignis (manuell, Zeit, Umfang) | Aufhebung ist nie automatisch; Mehrfach-Zyklen löschen/wiederzulassen müssen unterscheidbar sein |
| Wiederauftreten als **Prüffall** `previously_deleted` | Weder automatischer Import noch automatische Löschung (DEC-DUP-009); Quarantänekopien bleiben Quarantäne |
| Abgrenzung **Löschen vs. Verschieben** | Verschwindet die `file_id`, ist dies eine Löschung; Verschiebung aus der Sammlung heraus ist ein Prüffall (DEC-GRP-011), kein Tombstone |

Offen: Granularität bei Gruppen, Dauer/Aufbewahrung, Verhalten, wenn der Benutzer eine Datei außerhalb des Index-Scopes (z. B. in einen Share) verschiebt (→ Fragen in `open-questions.md`, Auftrag 3).

## 11. Freigaben und selektive Invalidierung (`DEC-DRY-013/014`)

### 11.1 Drei Zustände von Entscheidungen
| Klasse | Beispiel | Wirkung einer relevanten Regeländerung |
|---|---|---|
| (a) **Abgeschlossene Operation** | Datei wurde einsortiert und journalisiert | **Keine** (DEC-DRY-007). Nur neuer Reorganisationsplan darf sie anfassen. |
| (b) **Freigegebenes, noch nicht ausgeführtes Plan-Item** (historischer Bestand) | Dry-Run-Plan genehmigt, Ausführung läuft noch | Selektiv ungültig, wenn das Item eine **geänderte** Regelkomponente tatsächlich verwendet hat. Unberührte Items bleiben freigegeben. |
| (c) **Dauerhaft freigegebene Incoming-Regel** | laufende Quelle | Freigabe gilt für die Fingerprint-Komponenten der Quelle; ändert sich eine, ist die **gesamte** Quellenfreigabe erneut nötig (neue Regelversion, Dry-Run). |

### 11.2 Fingerprint-Komponenten [T]
Statt eines einzigen Hashes: ein Fingerprint **je Komponente**, damit selektiv verglichen werden kann.

| Komponente | Inhalt | Beispiel für Auswirkung |
|---|---|---|
| `date_fp` | Datumsstrategie, Feldreihenfolge, Zeitzonenregeln der Quelle, abhängige Benutzerzeitzone | Ändert Jahr/Monat/Datum im Namen |
| `naming_fp` | Namensschema, Beschreibungsregeln, Muster „automatisch erzeugt“ (global), Quellenname | Ändert Dateinamen |
| `target_fp` | Zielstruktur, Sammlungsordner | Ändert Zielordner |
| `group_fp` | Gruppierungsregeln (Live Photo/RAW/XMP) | Ändert Gruppenzuordnung |
| `source_fp` | Quellordner(e), Rekursion, Ausschlussmuster | Ändert Zuordnung der Dateien zur Quelle |
| `priority_rel_fp` | **Relative Rangfolge** der Quelle zu allen anderen aktiven Quellen | Ändert die bevorzugte Herkunft |
| **nicht enthalten** | Pausen (Benutzer, Quelle, Admin, global), Aktivierung, Anzeigeeinstellungen, Mindestalter (Vorschlag, s. `OQ-023`) | Keine Invalidierung |

### 11.3 Plan-Item-Abhängigkeiten [T]
Jedes Plan-Item speichert die **tatsächlich verwendeten** Komponenten und ihre Fingerprint-Werte zum Planzeitpunkt (z. B. Item mit EXIF-Datum und einziger Herkunft: `date_fp`, `naming_fp`, `target_fp`, `source_fp`; Item mit konkurrierender Herkunft zusätzlich `priority_rel_fp`). Bei Konfigurationsänderung wird pro Quelle verglichen; nur Items mit geänderter verwendeter Komponente werden `invalidated_by_rule_change`. Der Benutzer sieht „n von m Items betroffen“ und kann neu planen und gezielt freigeben. Da ein Plan über Quellen hinweg verwoben sein kann (Priorität), sind Quellenbeziehungen im Plan-Item mitzuführen.

### 11.4 Pausen
Pause ändert keinen Fingerprint, erzeugt keine Invalidierung, keine Neuzuordnung (DEC-DRY-014). Beim Ende der Pause wird ohne neue Freigabe fortgesetzt.

## 12. Nummern, Dry-Run und stille Änderungen (`DEC-NAM-013`)

Ergänzt §8.1. Ziel: Vorläufigkeit der Nummer darf nicht zu inhaltlich anderer Ablage führen.

**Plan-gebundene Eingaben** eines Items: Datumsergebnis (lokale Sortierzeit samt Quelle), bevorzugte Herkunft, Zielordner, Beschreibungsteil, Quellenpräfix, Komponentenmenge der Gruppe. **Nur die Kollisionsnummer** (und daraus der vollständige Name) ist vorläufig.

Ablauf bei Ausführung [T]:
1. Plan-gebundene Eingaben werden neu berechnet.
2. Weicht **irgendeine** davon vom Plan ab (z. B. neue höher priorisierte Herkunft, geänderte Zeitzonenregel, geänderte Beschreibung durch manuelle Umbenennung, neue Komponente verändert die Gruppe) → Item wird `stale` und nicht ausgeführt; es entsteht ein neuer Plan-Eintrag bzw. Prüffall. **Keine stille Anpassung.**
3. Stimmen sie überein, wird die Nummer reserviert; weicht sie von der Plananzeige ab, wird dies im Journal vermerkt, die Ausführung läuft.
4. Gleichzeitige Importe: Unique-Constraint-Wettlauf entscheidet; der Verlierer wiederholt mit der nächsten Nummer; zwei Items mit identischem Schlüssel sind danach unterschiedlich nummeriert, die Reihenfolge ist nicht Teil der Freigabe.

**Nachträglich ergänzte Mediengruppen:** Eine neue Komponente übernimmt Basisname und Nummer der Gruppe (DEC-NAM-010). Würde sie dabei ein anderes Datum, eine andere Herkunft oder einen anderen Zielordner implizieren, wird sie **nicht** still eingefügt, sondern als Konflikt geprüft; stimmen die Eingaben überein, wird sie mit gleichem Basisnamen nachgezogen. Ist der resultierende Name durch eine fremde Datei belegt → Prüffall (nie überschreiben).

**Manuelle Änderungen** am technischen Präfix oder an der Nummer (`DEC-NAM-012`): Erkennung, dass der Name nicht mehr dem erwarteten Schema entspricht → Prüffall `name_modified`, keine automatische Normalisierung. Reine Beschreibungsänderungen werden gemäß DEC-NAM-006 übernommen.

**Originalnamen** (`DEC-NAM-011`): Erkennung hat zwei Konfidenzstufen:
- *eindeutig automatisch erzeugt* (vollständiger Treffer auf bekannte Kamera-/Messenger-Muster): Original nicht anhängen.
- *unsicher* (Teiltreffer, gemischter Name): Original als Beschreibung beibehalten. Die Datenbank behält den Originalnamen in beiden Fällen (DEC-NAM-005).
Konkrete Muster sind Daten, nicht Code (konfigurierbar, versioniert, Teil von `naming_fp`).

## 13. Integrität: bewusste Unveränderlichkeit vs. Ende-zu-Ende (`DEC-IMM-006`)

| Garantie | Wer gewährleistet | Wie prüfbar |
|---|---|---|
| **G1 – Keine bewusste Inhaltsänderung durch App-Code** | App | Code- und Testregel: kein Schreib-API-Aufruf auf Medienknoten; Mutationen nur über Move/Rename/Delete (Papierkorb) |
| **G2 – Ende-zu-Ende-Integrität bei Storage-Operationen** | Nextcloud-Storage + App-Verifikation | **Nicht allein durch G1 zusichern.** Move ist nicht transaktional (Dateisystem, Dateicache und Metadaten werden getrennt aktualisiert). Die App verifiziert nach der Mutation: Knoten per `file_id` erneut laden; Pfad/Name = Soll; Größe unverändert; MTime unverändert; Etag-Änderung ist erlaubt. Bei Abweichung: `needs_review`, Quelle pausieren (DEC-JOB-011). |
| G3 – Inhaltsgleichheit nachweisbar | App | SHA-256 vor Mutation gespeichert; stichprobenweise bzw. im Kontrollscan Neu-Hash bei geänderter Signatur; Abweichung bei unveränderter Signatur = kritischer Integritätsfehler |

Bleibt unverändert: Intent-Journal, Roll-forward, Eltern-/Kindoperationen (§6). Nicht behauptet wird, dass die App Defekte im Storage-Unterbau erkennen oder reparieren könnte; sie erkennt Abweichungen und stoppt.

**Außerhalb des MVP** (`DEC-IMM-007`): externe Mounts, Group Folders, Shares und jegliche Operation über Storage-Grenzen. Die App erkennt solche Knoten (Mountpoint/Eigentümer, `nextcloud-integration.md` §2.1) und verarbeitet sie nicht. **Nicht nachgewiesen** für die Referenzumgebung: Storage-Typ (lokal vs. Object Storage); der MVP behandelt jeden Storage, bei dem Eingang und Sammlung im selben Home-Storage liegen und der Move ein Rename im selben Storage ist, als unterstützt, **sofern** der Spike (`nextcloud-integration.md` §3) dies bestätigt.

## 14. Allgemeine Veröffentlichungsfähigkeit: Leitplanken (`DEC-ARC-*`)

| Leitlinie | Architekturkonsequenz [T] |
|---|---|
| DEC-ARC-001 OCP bevorzugt | Alle Zugriffe auf Nextcloud-Funktionen hinter schmalen Schnittstellen der App (`FileGateway`, `TrashGateway`, `EventSource`, `PreviewGateway`, `UserSettingsGateway`). Interne Klassen (Klasse 3) nur in Adapterklassen, jede mit Verweis auf die betroffene Zeile in `nextcloud-integration.md`. |
| DEC-ARC-002 DB-Abstraktion | Ausschließlich Query Builder und Schema-Wrapper; Schlüsselgrenzen (z. B. maximale indizierbare Länge) berücksichtigen; lange Pfade nicht als Index, sondern über Hash-Spalten; Groß-/Kleinschreibung und Collation **nicht** annehmen, Normalisierung im Code. Keine Features einer speziellen Datenbank (z. B. `ON DUPLICATE KEY`, `RETURNING`). Eindeutigkeit durch Unique-Constraints und Insert-und-Retry. |
| DEC-ARC-003 Keine absoluten Pfade | Dateipfade nur relativ zum Benutzerordner (`Folder`), Konfiguration als Ordner-Auswahl (Node-ID + relativer Pfad), nie als Linux-Pfad. Externe Tools erhalten ein temporäres, von Nextcloud bereitgestelltes Verzeichnis (`ITempManager`: Existenz zu prüfen, 4) oder Stream. Keine Annahmen über Datadir. |
| DEC-ARC-004 Nextcloud-Background-Jobs | `TimedJob` als Scheduler/Worker; Batches begrenzen. `occ`-Befehl als **optionales Betriebswerkzeug**, nicht Voraussetzung (DEC-ARC-008). |
| DEC-ARC-005/006 Capability Detection | Beim App-Start/bei Bedarf Probe jeder Fähigkeit (z. B. `exif_available`, `imagick_heic`, `ffprobe_available`, `exiftool_available`, `proc_open_allowed`), Ergebnis im App-Zustand (Admin sieht nur Fähigkeiten, keine Medieninhalte). Jede Funktion deklariert benötigte und optionale Fähigkeiten; fehlt eine optionale, wird die Funktion **degradiert** (z. B. keine pHash für HEIC; Live Photos nur Heuristik → unsichere Gruppen im Eingang), nie die App deaktiviert. |
| DEC-ARC-007 Kapselung | Versionsabhängige Unterschiede (z. B. verfügbare Event-Klassen, `getFirstNodeById`) hinter Adapter mit Versionserkennung zur Laufzeit; Adapter-Tests je unterstützter Hauptversion. |
| DEC-ARC-008 Keine manuelle Serverkonfiguration | App funktioniert mit Standardinstallation: kein Zusatzdienst, kein eigener Cron, keine Config-Änderung. Optionale Beschleuniger (ExifTool, Worker per `occ`) sind Empfehlungen. |
| DEC-ARC-009 Evidenztrennung | In `nextcloud-integration.md` und `environment.md` jede Aussage mit Evidenzart: *Referenz bestätigt* / *API verifiziert (Version)* / *nicht verifiziert*. |

Bewusst als Konsequenz: Alles, was auf der Referenz „nur zufällig“ funktioniert (Pfade, MySQL-Verhalten, Imagick-Formate), darf weder in Code noch in Defaults einfließen.

---

# Ergänzungen Runde 3b (Entwurf [T], Vorschläge [Z])

Details und Begründungen: `data-model.md` §14–22. Hier nur die architektonischen Konsequenzen.

## 15. Scan-Scopes statt Quellen als Beobachtungsbereich
Die Pipeline (§2) beobachtet **Scan-Scopes**: Eingangs-Scopes (je Quelle, mit Regeln, Priorität, Freigabe) und den Sammlungs-Scope (ohne Regeln, nur Indexierung). Die bestehende Sammlung wird ohne künstlichen Import indexiert (`DEC-DRY-010`): rein lesend, ohne Plan-Items, ohne Nummernreservierung. Adoptierte Dateien sind gegen automatische Neuzuordnung geschützt, bis ein ausdrücklich freigegebener Plan sie einschließt.

## 16. Herkunftsentscheidung als persistente Zwischenschicht
Zwischen unveränderlicher Provenance und den Sortierplänen steht die persistente `po_origin_decision`. Sie ändert sich nur durch neue Herkunftsereignisse nach freigegebener Regel, durch freigegebene Pläne oder manuell — nie durch eine Konfigurationsänderung allein (`DEC-DRY-007`). Erweitert §8.3: Eine Prioritätsänderung entwertet offene Plan-Items (`priority_ctx`), ordnet aber nichts Bestehendes um.

## 17. Korrektur zu §6.6/§6.7: Was das Fencing leistet
Das Lease-Token schützt **Datenbankschreibvorgänge**. Gegen einen verspäteten Storage-Aufruf eines alten Workers schützt es nicht. Die Architektur begrenzt das Risiko durch: Beanspruchung der Operation per bedingtem Update, höchstens eine aktive Operation je Instanz, Ziel im Intent festgelegt, Frist und Prüfung vor dem Aufruf, Nachprüfung danach, Abbruch nur vor der Beanspruchung, Roll-forward durch Recovery. Das Fenster zwischen letzter Prüfung und `Node::move()` ist **nicht schließbar** und wird nicht verschwiegen. Atomizität von Move wird nicht behauptet.

## 18. Ergänzung zu §13 (Integrität)
Die Garantie **G2** erhält Stufen: Metadatenkontinuität (`meta_continuous`) ist nur ein Hinweis; vollständig verifiziert ist ein Inhalt erst nach Neuhash des Ziels (`hash_verified`). Operationen mit möglicher Kopier-/Löschsemantik, unbekanntem Storage und per Recovery abgeschlossene Operationen verlangen den Neuhash vor `done`. Duplikate gehen nur dann in den Papierkorb, wenn die kanonische Instanz nach ihrer letzten Mutation `hash_verified` ist. Ergebnisse werden in `po_integrity_check` abfragbar abgelegt.

## 19. pHash als versionierter Index
Algorithmus, Version und Bandlayout sind Daten (`po_phash_algo`), nicht Schema. Das Verfahren garantiert nur bis `b·(r+1)−1` Bit Distanz, begrenzt Arbeit durch Kappung und wird vor der Festlegung des Layouts mit dem Benchmarkplan (`data-model.md` §19.6) gemessen.


---

# Ergänzungen Runde 4 (Entwurf [T], Vorschläge [Z])

## 20. Korrekturen und Verweise
- **§6.3 (Recovery-Tabelle) ist präzisiert:** Die Zeile „Datei liegt am Vorzustand, unverändert → wiederholen“ gilt nur nach **Ruhefrist**, positiver Beobachtung, neuem Anspruch mit höherem Fencing, erneuter Pre-Verifikation und mit **demselben** Intent (`DEC-JOB-014`, `crash-recovery.md` §3). Kein blindes Wiederholen, kein Rollback.
- **§13 (G2) ist verschärft:** Für App-veranlasste Verschiebungen sind vollständiger Pre- und Post-SHA-256 Pflicht; erst danach endgültiger Erfolg (`DEC-IMM-008`). Der klassenbasierte Entwurf aus §18 gilt nur noch als Risikokennzeichnung und für nicht App-veranlasste Prüfungen.
- **§17 (Fencing)** bleibt gültig: das Token schützt nur die Datenbank. `crash-recovery.md` §2 beschreibt Schutz und Restrisiken.
- Zustandsautomaten, Transaktionsgrundsätze, Worker, Fairness und Event-Verarbeitung: `job-processing.md`; Crash-Szenarien: `crash-recovery.md`.

## 21. Konsequenz für die Schichten
Die Ausführungsschicht (Executor) kennt drei Datenbankzugriffsmuster: (1) bedingte Übergänge mit Fence, (2) Intent-/Reservierungs-TX, (3) Abschluss-TX. Storage-Aufrufe laufen ausschließlich über den `FileGateway`; dieser ist der einzige Ort mit Zugriff auf `Node::move()`/`delete()`/Sperren. Dispatcher, Scheduler und Recovery sind getrennte Komponenten, die dieselben Zustandsübergänge verwenden (keine eigenen Schreibpfade).

---

# Ergänzungen Runde 4b

## 22. Fail-closed-Architektur der Mutation
Die Ausführungsschicht ist **fähigkeitsgesteuert**: Jede Mutationsart (Move, Papierkorb) ist nur aktiv, wenn für das erkannte (Nextcloud-Version, Storage-Klasse)-Tupel das erforderliche Verhalten verifiziert ist. Sonst arbeitet die App im Modus „Nur Indexierung und Analyse“ (`DEC-ARC-010`). Der `FileGateway` bietet keine überschreibende Methode. Details: `job-processing.md` §6.3.

## 23. Korrekturen
- Operationsreihenfolge endgültig `intended → preverified → claimed → executing` (`job-processing.md` §5.8). Der Pre-Hash gehört **nicht** unter einen Operationsanspruch.
- Sechs Operationstypen mit Autorisierungsmatrix auf einem gemeinsamen Mutationskern (`job-processing.md` §6.1–6.2).
- Löschsicherheit: Neuhash beider Exemplare unmittelbar vor dem Papierkorb-Aufruf (`DEC-DUP-010`); überholt die Formulierung in §18 (Hash-Bestätigung der kanonischen Instanz „nach ihrer letzten Mutation“).
- Pausen: Wartungsmodus ohne Storage-Zugriffe; Kontrollscan nach Freigabe (`DEC-JOB-016`).
- Events: Änderungszähler statt reinem Unique-Dedup.
- Hashing: drei Stufen; kein Ersatzhash (`DEC-IMM-010`).

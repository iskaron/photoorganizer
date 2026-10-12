# Anforderungen – Photo Organizer (Arbeitstitel)

Stand: 2026-10-09 · Runde 1 (Planung) · Status: Entwurf zur Abnahme durch Product Owner

Statuskennzeichnung wie in `decisions.md`: **[V]** verbindlich, **[T]** technisch vorgeschlagen, **[O]** noch zu entscheiden.

Alle mit **[V]** markierten Anforderungen verweisen auf die zugrunde liegenden Entscheidungen (`DEC-…`). Anforderungen ohne `DEC`-Bezug sind Ableitungen und als **[T]** markiert.

---

## 1. Produktziel [V]

Eine native Nextcloud-App zur **sicheren Organisation und Deduplikation privater Foto- und Videobestände** (DEC-PRD-001). Sie wird zentral installiert; jeder Benutzer konfiguriert und aktiviert sie individuell.

Nicht-Ziele (abgeleitet aus den Entscheidungen) [V]:

- Keine Bildbearbeitung, keine Metadatenkorrektur (DEC-IMM-001/002).
- Keine automatische Löschung (DEC-DUP-005, DEC-PHA-004).
- Keine Verarbeitung freigegebener Dateien, Gruppenordner oder externer Mounts (DEC-USR-002/003).
- Kein direkter Datadir-Zugriff (DEC-NCI-002).
- Kein Medienzugriff für Administratoren über die App (DEC-USR-009).

## 2. Begriffe

| Begriff | Bedeutung |
|---|---|
| **Eingang / Quelle (Source)** | Vom Benutzer konfigurierter Ordner, aus dem importiert wird, mit eigenen Regeln. |
| **Sammlung (Collection)** | Der konfigurierte Zielordner mit der organisierten Struktur `Jahr/Monat/Quelle`. |
| **Datei (File)** | Konkrete Nextcloud-Datei, identifiziert durch `file_id`. |
| **Inhaltsidentität (Content Identity)** | SHA-256 des Dateiinhalts. |
| **Medium** | Logische Einheit (Foto/Video) in der Sammlung. Ein Medium kann aus mehreren Komponenten (Mediengruppe) bestehen. |
| **Mediengruppe (Media Group)** | Zusammengehörige Komponenten: Live Photo (Foto + Video), RAW+JPEG, XMP-Sidecar. |
| **Kanonisches Exemplar** | Die eine Datei pro SHA-256 und Benutzer, die in der Sammlung liegt (DEC-DUP-002). |
| **Quarantäne** | Rein logischer Zustand „zur Prüfung“; die Datei bleibt physisch am Ort (DEC-JNL-001). |
| **Prüfbereich (Review)** | Oberfläche für exakte Duplikate, mögliche Duplikate, Konflikte, Fehler. |
| **Journal** | Vollständiges Protokoll aller Dateioperationen. |
| **`location_override`** | Marker, dass der Ablageort manuell bestimmt wurde und automatische Sortierung ihn respektiert. |
| **Importplan** | Ergebnis eines Dry-Runs: geplante Operationen, die zur Freigabe vorgelegt werden. |
| **Regelversion** | Unveränderlicher Stand der Regeln einer Quelle (Datum, Namen, Priorität, Stabilität), an den Freigaben gebunden sind. |

---

## 3. Funktionale Anforderungen

IDs: `REQ-<Bereich>-<Nr>`. Spalte „Basis“ nennt die Entscheidung.

### 3.1 Benutzer, Aktivierung, Berechtigungen

| ID | Anforderung | Status | Basis |
|---|---|---|---|
| REQ-USR-01 | Nach Installation ist für keinen Benutzer eine Verarbeitung aktiv; es werden weder Events verarbeitet noch Scans oder Hashes ausgeführt. | V | DEC-USR-005 |
| REQ-USR-02 | Jeder Benutzer kann seinen Organizer aktivieren, deaktivieren, pausieren. | V | DEC-USR-004 |
| REQ-USR-03 | Verarbeitung für einen Benutzer läuft nur, wenn: Benutzer aktiviert ∧ Benutzer nicht pausiert ∧ Admin hat weder den Benutzer noch global pausiert. | V | DEC-USR-006/007 |
| REQ-USR-04 | Admin kann einzelne Benutzer und die gesamte Verarbeitung pausieren, aber nie aktivieren. | V | DEC-USR-006/007 |
| REQ-USR-05 | Es werden ausschließlich Dateien im eigenen Home-Storage des Benutzers verarbeitet. Eingehende Shares, Gruppenordner, externe Mounts werden nicht indiziert (auch nicht „nur gelesen“). | V | DEC-USR-001–003 |
| REQ-USR-06 | Deaktivieren stoppt Verarbeitung; ob und wie Index-/Journaldaten verbleiben oder gelöscht werden können, ist zu klären. | O | OQ-018 |
| REQ-USR-07 | Bei Löschung eines Nextcloud-Benutzers werden alle App-Daten dieses Benutzers entfernt. | T | – |

### 3.2 Admin-Oberfläche und -API

| ID | Anforderung | Status | Basis |
|---|---|---|---|
| REQ-ADM-01 | Admin sieht je Benutzer: Identität, Aktivierungszustand, Pausenzustand, Betriebszustand, aggregierte Zähler (z. B. Anzahl indizierter Dateien, offene Jobs, Anzahl Fehler/Prüffälle, letzter Lauf). | V | DEC-USR-008 |
| REQ-ADM-02 | Admin-API liefert niemals Dateinamen, Pfade, Vorschauen, EXIF, Hashes oder Einzelheiten zu Benutzerfotos – auch nicht in Fehlermeldungen, Logs, Exceptions oder Job-Argumenten, die über die Admin-API erreichbar sind. | V | DEC-USR-009 |
| REQ-ADM-03 | Globale und benutzerbezogene Pause durch Admin. | V | DEC-USR-006 |
| REQ-ADM-04 | Fehlerzustände werden für Admins nur als Kategorie/Anzahl dargestellt (z. B. „12 Fehler: Typ ‚Speicherfehler‘“), nicht mit Pfaden. | T | DEC-USR-009 |
| REQ-ADM-05 | Fehlerdetails in Nextcloud-Logs (`nextcloud.log`) enthalten keine Pfade/Dateinamen von Benutzermedien oder sind pseudonymisiert. | T | DEC-USR-009 (Randbedingung) |
| REQ-ADM-06 | Aggregate dürfen bei sehr kleinen Mengen keine Einzelfotos identifizierbar machen (z. B. „1 Duplikat in Quelle X“ ist unkritisch, Quellennamen sind ggf. benutzerprivat). Ob Quellennamen in der Admin-Sicht erscheinen dürfen, ist zu klären. | O | OQ-014 |

### 3.3 Quellen und Ziel

| ID | Anforderung | Status | Basis |
|---|---|---|---|
| REQ-SRC-01 | Pro Benutzer beliebig viele Eingangsordner konfigurierbar. | V | DEC-SRC-001 |
| REQ-SRC-02 | Pro Benutzer genau ein Sammlungsordner konfigurierbar. | V | DEC-SRC-002 |
| REQ-SRC-03 | Pro Quelle konfigurierbar: Name, Datumsstrategie, Namensregeln, Priorität, Mindestalter/Stabilität, Typ (historisch / laufend). | V | DEC-SRC-003/006, DEC-STB-001 |
| REQ-SRC-04 | Zielstruktur: `<Sammlung>/<Jahr>/<Monat>/<Quelle>/`; Fotos und eigenständige Videos gemeinsam. | V | DEC-SRC-004/005 |
| REQ-SRC-05 | Eingangsordner dürfen sich weder mit der Sammlung noch untereinander überschneiden (Verschachtelung). Validierung bei Konfiguration. | T | – (Datenintegrität) |
| REQ-SRC-06 | Eingangsordner und Sammlung müssen im Home-Storage des Benutzers liegen (keine Mounts/Shares); Validierung inklusive Prüfung auf Mountpoints unterhalb des Ordners. | T | DEC-USR-002/003, DEC-NCI-004 |
| REQ-SRC-07 | Rekursionstiefe pro Quelle (rekursiv/flach) und Ausschlussmuster. | O | OQ-019 |
| REQ-SRC-08 | Format des Monatsordners (`01`, `01 Januar`, …) und Jahresordner. | O | OQ-009 |
| REQ-SRC-09 | Umbenennung/Neuanlage von Quellen und Auswirkungen auf bestehende Dateinamen/Pfade. | O | OQ-020 |

### 3.4 Datum und Quellenpriorität

| ID | Anforderung | Status | Basis |
|---|---|---|---|
| REQ-DAT-01 | Kamera-Strategie: EXIF `DateTimeOriginal` (ggf. `CreateDate`-Fallback, **zu klären**) bzw. QuickTime-Aufnahmezeit, sonst MTime. | V (Grundsatz) / O (Feldreihenfolge) | DEC-DAT-001, OQ-008 |
| REQ-DAT-02 | Messenger-Strategie: MTime als Empfangsdatum. | V | DEC-DAT-002 |
| REQ-DAT-03 | Persistiert werden: ermittelter Zeitstempel, Zeitquelle (enum), Rohwerte (EXIF-String, Offset-Felder, MTime als Unix-Timestamp), Zeitzonenstatus (lokal/mit Offset), verwendete Benutzerzeitzone. | V | DEC-DAT-007 |
| REQ-DAT-04 | EXIF-Zeit ohne Zeitzone wird als lokale Zeit („floating“) behandelt und **nicht** in UTC umgerechnet. | V | DEC-DAT-004 |
| REQ-DAT-05 | Mit Zeitzonenangabe (`OffsetTimeOriginal`, QuickTime UTC): Offset wird berücksichtigt. Ob für Ordner/Dateinamen lokale Zeit am Aufnahmeort oder Benutzerzeitzone gilt, ist zu entscheiden. | V (berücksichtigen) / O (Darstellung) | DEC-DAT-005, OQ-012 |
| REQ-DAT-06 | MTime wird in der konfigurierten Benutzerzeitzone dargestellt/in Pfad- und Namensbestandteile umgesetzt. Quelle der „konfigurierten Benutzerzeitzone“ (Nextcloud-Benutzerprofil vs. App-Einstellung) ist zu klären. | V / O | DEC-DAT-006, OQ-012 |
| REQ-DAT-07 | Quellen haben konfigurierbare Prioritäten; höhere Priorität gewinnt bei Herkunftskonflikten. | V | DEC-DAT-008/009 |
| REQ-DAT-08 | Höher priorisierte spätere Herkunft darf bereits einsortierte Datei neu zuordnen, sofern kein `location_override`. Verhältnis zur Freigabepflicht ist zu klären. | V / O | DEC-DAT-010, OQ-001 |
| REQ-DAT-09 | Plausibilitätsprüfung: Daten wie `0000:00:00`, 1970-01-01, 1904-01-01 (QuickTime-Epoche), Zukunft werden nicht als Aufnahmedatum akzeptiert; Verhalten (Fallback/Prüffall) ist zu definieren. | O | OQ-013 |
| REQ-DAT-10 | MTime wird zur Zeitgewinnung nie verändert (keine Rückschreibung). | V | DEC-IMM-001 |

### 3.5 Dateinamen

| ID | Anforderung | Status | Basis |
|---|---|---|---|
| REQ-NAM-01 | Schema: `<Quelle> <YYYY-MM-DD> <HH-MM-SS> <NN>[ <Beschreibung>].<ext>`. Beispiel: `Kamera 2026-10-09 13-12-12 01 Mein Lieblingsfoto.jpg`. | V | DEC-NAM-001 |
| REQ-NAM-02 | Alle erzeugten Namen sind Windows-kompatibel: keine Doppelpunkte, keine Zeichen `\ / : * ? " < > |`, keine reservierten Gerätenamen, kein Punkt/Leerzeichen am Ende, Längenlimits. | V | DEC-NAM-002 |
| REQ-NAM-03 | Automatisch erzeugt wirkende Originalnamen (z. B. `IMG_1234`, `DSC01234`, `PXL_…`, `WhatsApp Image …`, UUID-artige Namen) werden nicht als Beschreibung übernommen. Die Erkennungsregeln sind zu definieren. | V / O | DEC-NAM-003, OQ-010 |
| REQ-NAM-04 | Aussagekräftige Originalnamen werden als Beschreibung übernommen. | V | DEC-NAM-004 |
| REQ-NAM-05 | Vollständiger Originalname bleibt in der Datenbank. | V | DEC-NAM-005 |
| REQ-NAM-06 | Manuelle Umbenennung in Nextcloud → neuer Name wird als persönliche Beschreibung übernommen und in das Schema eingepasst. | V | DEC-NAM-006 |
| REQ-NAM-07 | Bereits vorhandene Präfixe (Quelle/Datum/Uhrzeit/Nummer) werden erkannt und nicht erneut vorangestellt (Idempotenz: Anwenden der Namensregel auf einen bereits normalisierten Namen ändert nichts). | V | DEC-NAM-007 |
| REQ-NAM-08 | Kollisionsnummer wird atomar reserviert (DB-seitig eindeutig; Eindeutigkeit über Schlüssel `(user, Quelle, Datum, Uhrzeit)` bzw. Basisname – Scope offen). | V / O | DEC-NAM-008, OQ-011 |
| REQ-NAM-09 | Nummern werden nie wiederverwendet (auch nicht nach Löschung/Papierkorb) und nie umnummeriert. | V | DEC-NAM-009 |
| REQ-NAM-10 | Alle Komponenten einer Mediengruppe teilen Nummer und Basisnamen; Extension unterscheidet. | V | DEC-NAM-010 |
| REQ-NAM-11 | Namenskollision mit einer **existierenden fremden Datei** gleichen Namens (z. B. manuell angelegt) wird nie überschrieben; stattdessen neue Nummer oder Prüffall. | T | DEC-JNL-008 |
| REQ-NAM-12 | Größenbegrenzung der Beschreibung; Unicode-Normalisierung (NFC), Behandlung von Emojis/Sonderzeichen. | O | OQ-021 |

### 3.6 Unveränderlichkeit

| ID | Anforderung | Status | Basis |
|---|---|---|---|
| REQ-IMM-01 | Kein Schreibzugriff auf Dateiinhalte. Die App ruft nie Methoden zum Schreiben/Ersetzen von Dateiinhalten auf Medien auf. | V | DEC-IMM-001 |
| REQ-IMM-02 | Erlaubte Mutationen: Move/Rename und (manuell ausgelöst) Verschieben in den Papierkorb. | V | DEC-IMM-003, DEC-DUP-005 |
| REQ-IMM-03 | SHA-256 des Originals wird erfasst und gegen spätere Zustände geprüft; Abweichung bei unveränderter `(size, mtime, etag)`-Signatur gilt als Integritätsfehler. | T | DEC-IMM-004, DEC-JOB-011 |
| REQ-IMM-04 | Thumbnails, pHash, extrahierte Metadaten werden in App-Tabellen oder App-eigenem Speicher (Nextcloud Appdata) abgelegt, nie als Sidecar im Benutzerordner. | V | DEC-IMM-005 |
| REQ-IMM-05 | Erzeugung von Vorschaubildern darf Originaldateien nicht verändern (insbesondere keine Rotation „einbrennen“). | V | DEC-IMM-002/005 |

### 3.7 Deduplikation (SHA-256)

| ID | Anforderung | Status | Basis |
|---|---|---|---|
| REQ-DUP-01 | Gleiches SHA-256 = gleiche Inhalte; Grundlage aller exakten Duplikaterkennung. | V | DEC-DUP-001 |
| REQ-DUP-02 | Pro `(user, sha256)` höchstens ein kanonisches Exemplar in der Sammlung. | V | DEC-DUP-002 |
| REQ-DUP-03 | Weitere Kopien dürfen im Eingang verbleiben und werden als „exaktes Duplikat“ logisch in Quarantäne geführt. | V | DEC-DUP-003/005 |
| REQ-DUP-04 | Jede Herkunft (Quelle, ursprünglicher Pfad, Zeitpunkt, Importvorgang) bleibt dokumentiert. | V | DEC-DUP-004 |
| REQ-DUP-05 | Keine automatische Löschung. | V | DEC-DUP-005 |
| REQ-DUP-06 | Manuelle Sammelaktion „Duplikate in Papierkorb verschieben“; je Datei werden unmittelbar vor der Aktion `file_id`, Eigentümer, Pfad innerhalb erlaubter Bereiche, Größe/MTime **und** SHA-256 (erneut oder durch gültigen Cache bei unveränderter Signatur – zu entscheiden) geprüft. | V / O | DEC-DUP-005/006, OQ-015 |
| REQ-DUP-07 | Schutz: Kanonisches Exemplar und jede Komponente einer Mediengruppe, deren andere Komponenten nicht ebenfalls als redundant gelten, sind von der Sammelaktion ausgenommen. | V | DEC-DUP-007 |
| REQ-DUP-08 | Vor der Sammelaktion: Vorschau/Dry-Run mit Anzahl, Gesamtgröße und Liste der betroffenen Dateien. | T | DEC-DRY (sinngemäß) |
| REQ-DUP-09 | Dedup umfasst auch Videos und Nicht-Bild-Komponenten (SHA-256 gilt für alle Medientypen). | T | DEC-DUP-001 |

### 3.8 Perceptual Hashing

| ID | Anforderung | Status | Basis |
|---|---|---|---|
| REQ-PHA-01 | pHash wird nur für Bilder berechnet; nur zur Erkennung möglicher Duplikate. | V | DEC-PHA-001 |
| REQ-PHA-02 | Bilder mit verschiedenem SHA-256 werden unabhängig vom pHash regulär importiert. | V | DEC-PHA-002 |
| REQ-PHA-03 | Ähnliche Bilder erscheinen im Prüfbereich als „mögliche Duplikate“. | V | DEC-PHA-003 |
| REQ-PHA-04 | pHash löst nie Löschung, Quarantäne-Zwang oder Importblockierung aus. | V | DEC-PHA-004 |
| REQ-PHA-05 | Entscheidung „Kein Duplikat“ wird dauerhaft an Inhaltsidentitäten (Paar von SHA-256-Werten) gebunden. | V | DEC-PHA-005 |
| REQ-PHA-06 | SHA-256-identische Kopien übernehmen die Entscheidung. | V | DEC-PHA-006 |
| REQ-PHA-07 | Ähnlichkeit allein überträgt keine Entscheidung auf andere Paare. | V | DEC-PHA-007 |
| REQ-PHA-08 | pHash-Algorithmus, Hashlänge, Distanzmaß, Schwelle, Behandlung von Rotation/Spiegelung, HEIC/RAW-Dekodierung. | O | OQ-016 |
| REQ-PHA-09 | Nachbarschaftssuche in großen Beständen (>100 000 Bilder) muss skalieren (kein O(n²)-Vergleich im Request). | T | – (Performance) |

### 3.9 Mediengruppen

| ID | Anforderung | Status | Basis |
|---|---|---|---|
| REQ-GRP-01 | Gruppentypen: Live Photo (Standbild + kurzes Video), RAW+JPEG(+weitere), XMP-Sidecar. | V | DEC-GRP-001 |
| REQ-GRP-02 | Gruppierung nur bei eindeutigen Metadaten (z. B. Apple Content Identifier) oder zuverlässiger Kombination aus Basisname, Zeitstempeln und Quellordner. | V | DEC-GRP-002 |
| REQ-GRP-03 | Unsichere Gruppierung → Dateien bleiben im Eingang und erscheinen im Prüfbereich. | V | DEC-GRP-003 |
| REQ-GRP-04 | Einzelne RAW/JPEG ohne Partner sind gültige Medien; fehlende optionale Begleitdateien blockieren nicht. | V | DEC-GRP-004/005 |
| REQ-GRP-05 | Erkennbar unvollständige Live Photos warten eine konfigurierbare Zeit (Default offen). | V / O | DEC-GRP-006, OQ-022 |
| REQ-GRP-06 | Neue Komponenten können eine bestehende Gruppe ergänzen, auch wenn die Gruppe bereits einsortiert ist (Nachziehen von Name/Ort). | V | DEC-GRP-007/009 |
| REQ-GRP-07 | Dedup je Komponente (SHA-256 je Datei). Ergebnis bei teilweise doppelten Gruppen ist zu definieren. | V / O | DEC-GRP-008, OQ-004 |
| REQ-GRP-08 | Manuelle Verschiebung einer Komponente innerhalb der Sammlung → nach kurzer Verzögerung (Wert offen) automatisches Nachziehen der übrigen; `location_override` wird gesetzt. | V / O | DEC-GRP-010/012, OQ-017 |
| REQ-GRP-09 | Verschiebung außerhalb der Sammlung → keine Mitnahme; Prüffall. | V | DEC-GRP-011 |
| REQ-GRP-10 | Manuelles Löschen einer Komponente löscht nie weitere Komponenten. | V | DEC-GRP-013 |
| REQ-GRP-11 | Gelöschte kanonische Datei wird nie automatisch durch Quarantäne-Kopie ersetzt; stattdessen Prüffall bzw. manuelle Entscheidung. | V | DEC-GRP-014 |
| REQ-GRP-12 | Eine Komponente kann höchstens einer Gruppe angehören. | T | – |

### 3.10 Dry-Run und Freigaben

| ID | Anforderung | Status | Basis |
|---|---|---|---|
| REQ-DRY-01 | Vor der ersten automatischen Organisation einer Quelle: vollständiger Dry-Run, der keine Dateioperation ausführt. | V | DEC-DRY-001 |
| REQ-DRY-02 | Ausdrückliche Freigabe des Plans durch den Benutzer. | V | DEC-DRY-002 |
| REQ-DRY-03 | Historische Bestände: einmalige, dateibezogene Freigabe (Plan-Items). Änderungen an Dateien nach dem Dry-Run machen das jeweilige Item ungültig (Staleness). | V / T | DEC-DRY-003 |
| REQ-DRY-04 | Laufende Quellen: dauerhafte Freigabe, gebunden an eine konkrete Regelversion. | V | DEC-DRY-004 |
| REQ-DRY-05 | Freigaben sind pro Quelle. | V | DEC-DRY-005 |
| REQ-DRY-06 | Relevante Regeländerungen (welche genau = „relevant“, ist festzulegen) erzeugen eine neue Regelversion und erfordern erneuten Dry-Run und Freigabe; bis dahin ruht die Quelle oder arbeitet mit der alten freigegebenen Version weiter (zu entscheiden). | V / O | DEC-DRY-006, OQ-023 |
| REQ-DRY-07 | Bereits importierte Medien werden durch Regeländerungen nicht automatisch verändert. | V | DEC-DRY-007 |
| REQ-DRY-08 | „Sammlung neu organisieren“ als eigene Funktion mit eigenem Dry-Run und eigener Freigabe; `location_override` bleibt standardmäßig geschützt (optional abwählbar nur explizit). | V | DEC-DRY-008/009 |
| REQ-DRY-09 | Dry-Run-Ergebnis ist reproduzierbar/persistiert (Plan-ID, Regelversion, Zeitpunkt, Zusammenfassung) und im Journal referenzierbar. | T | DEC-JNL-009 |
| REQ-DRY-10 | Ausführung eines freigegebenen Plans prüft pro Datei erneut den Zustand (siehe Stabilität) und führt veränderte Items nicht aus. | T | DEC-STB-003 |

### 3.11 Upload-Stabilität

| ID | Anforderung | Status | Basis |
|---|---|---|---|
| REQ-STB-01 | Neue Datei darf erst verarbeitet werden, wenn Alter ≥ Mindestalter (Default 5 min, pro Quelle). | V | DEC-STB-001 |
| REQ-STB-02 | Zwei aufeinanderfolgende Beobachtungen mit identischer Größe und MTime. | V | DEC-STB-002 |
| REQ-STB-03 | Unmittelbar vor jeder Änderung erneute Prüfung (Existenz, `file_id`, Größe, MTime, ggf. Sperrstatus). | V | DEC-STB-003 |
| REQ-STB-04 | Unvollständige Uploads (z. B. Chunked-Upload-Reste, `.part`-Dateien, aktive Sperre) werden nicht verarbeitet. | V | DEC-STB-004 |
| REQ-STB-05 | Mediengruppe wird erst verarbeitet, wenn alle bekannten Komponenten stabil sind (bzw. Wartezeit abgelaufen). | V | DEC-STB-005, DEC-GRP-006 |
| REQ-STB-06 | MTime-Rückdatierung durch Clients (Desktop/Mobile-Sync setzen MTime auf Quellwert) darf das Mindestalter nicht umgehen; Alter bezieht sich auf **Beobachtungszeit** der App (`first_seen_at`), nicht auf MTime. | T | DEC-DAT-003 |

### 3.12 Hintergrundverarbeitung

| ID | Anforderung | Status | Basis |
|---|---|---|---|
| REQ-JOB-01 | Event-Listener schreiben nur leichte Queue-Einträge (Datei-ID, Ereignistyp, Zeit). | V | DEC-JOB-001/002 |
| REQ-JOB-02 | Hashing, EXIF-Lesen, pHash, Dateioperationen laufen ausschließlich in Background-Jobs bzw. `occ`. | V | DEC-JOB-002 |
| REQ-JOB-03 | Täglicher inkrementeller Kontrollscan je aktiviertem Benutzer. | V | DEC-JOB-003 |
| REQ-JOB-04 | Manueller vollständiger Neuabgleich durch den Benutzer (UI und/oder `occ`). | V | DEC-JOB-004 |
| REQ-JOB-05 | Unveränderte Dateien (gleiche Signatur) werden nicht erneut gehasht. | V | DEC-JOB-005 |
| REQ-JOB-06 | Verarbeitung in kleinen, wiederaufnehmbaren Batches mit persistiertem Fortschritt. | V | DEC-JOB-006 |
| REQ-JOB-07 | Benutzerbezogene und globale Pause greifen spätestens zwischen zwei Batches/Items. | V | DEC-JOB-007 |
| REQ-JOB-08 | Faire Verteilung der Rechenzeit zwischen Benutzern (z. B. Round-Robin mit Budget). | V | DEC-JOB-008 |
| REQ-JOB-09 | Retry mit Backoff bei temporären Fehlern; Klassifikation temporär/dauerhaft. | V | DEC-JOB-009 |
| REQ-JOB-10 | Dauerhafte Fehler im Prüfbereich. | V | DEC-JOB-010 |
| REQ-JOB-11 | Kritischer Integritätsfehler pausiert die betroffene Quelle. | V | DEC-JOB-011 |
| REQ-JOB-12 | Systematische Fehler (z. B. wiederholte Fehlerrate über Schwelle, Speicher voll, Berechtigungsfehler) pausieren größere Einheiten (Benutzer oder global); Schwellen offen. | V / O | DEC-JOB-012, OQ-024 |
| REQ-JOB-13 | Keine unkontrollierten parallelen Operationen: pro Benutzer höchstens ein schreibender Worker (und pro Quelle serialisierte Mutationen); Obergrenze für Gesamtparallelität. | V (Prinzip) | DEC-JOB-013 |
| REQ-JOB-14 | Idempotenz: Jeder Job kann abgebrochen und wiederholt werden, ohne doppelte Wirkung. | T | DEC-JOB-006/009 |

### 3.13 Quarantäne, Prüfbereich, Journal, Rückgängig

| ID | Anforderung | Status | Basis |
|---|---|---|---|
| REQ-JNL-01 | Quarantäne ist ein logischer Zustand; keine Quarantäne-Ordner. | V | DEC-JNL-001 |
| REQ-JNL-02 | Prüfbereich-Kategorien: exakte Duplikate, mögliche Duplikate, Konflikte (dauerhafte Fehler als weitere Kategorie oder Unterart von Konflikten – zu entscheiden). | V / O | DEC-JNL-002, DEC-JOB-010, OQ-025 |
| REQ-JNL-03 | Einzel- und Stapelentscheidungen, Filter (Quelle, Zeitraum, Typ, Status, …). | V | DEC-JNL-003 |
| REQ-JNL-04 | Jede Dateioperation (Move, Rename, Trash, Restore, Undo) wird journalisiert: Absicht vor Ausführung, Ergebnis danach, Vorher-/Nachher-Zustand (`file_id`, Pfad, Name, Größe, MTime, SHA-256), Auslöser (Automatik/Plan/Benutzer/Undo), Plan-/Regelversion. | V / T | DEC-JNL-004 |
| REQ-JNL-05 | Rückgängig prüft den aktuellen Zustand gegen den im Journal erwarteten Nachzustand. Weicht er ab → kein Undo, Hinweis/Prüffall. | V | DEC-JNL-005/008 |
| REQ-JNL-06 | Undo erzeugt einen neuen Journal-Eintrag (verweist auf Original), das Original bleibt unverändert. | V | DEC-JNL-006 |
| REQ-JNL-07 | Papierkorb-Wiederherstellung nur, wenn Datei tatsächlich im Papierkorb wiederherstellbar ist (und Ziel nicht belegt). | V | DEC-JNL-007 |
| REQ-JNL-08 | Undo überschreibt nie Dateien Dritter/des Benutzers; Zielkollision → Abbruch mit Prüffall. | V | DEC-JNL-008 |
| REQ-JNL-09 | Manuelle Entscheidungen (Kein Duplikat, Override, Bestätigungen) werden mit Zeit und Kontext dokumentiert. | V | DEC-JNL-009 |
| REQ-JNL-10 | Aufbewahrungsdauer/Bereinigung des Journals; Journal darf bei Deaktivierung des Organizers nicht stillschweigend verloren gehen. | O | OQ-018 |

### 3.14 Webfrontend

| ID | Anforderung | Status | Basis |
|---|---|---|---|
| REQ-UI-01 | Dashboard mit Zustand (aktiv/pausiert), Fortschritt, Zählern, offenen Prüffällen, letzten Operationen. | V (Bestandteil laut Produktziel) | DEC-PRD-002 |
| REQ-UI-02 | Quarantäne-/Prüfbereich mit Filtern, Einzel- und Stapelaktionen. | V | DEC-JNL-003 |
| REQ-UI-03 | Importpläne: Dry-Run-Ergebnis ansehen, filtern, freigeben, ablehnen. | V | DEC-DRY-002 |
| REQ-UI-04 | Quellen- und Regelkonfiguration inklusive Validierung und Regelversionierung. | V | DEC-SRC-003, DEC-DRY-004 |
| REQ-UI-05 | Journal-Ansicht mit Undo. | V | DEC-JNL-004–006 |
| REQ-UI-06 | Vorschaubilder im Prüfbereich nur für den eigenen Benutzer; Auslieferung über Nextcloud-Preview-Mechanismen mit Berechtigungsprüfung. | T | DEC-USR-009, DEC-IMM-005 |
| REQ-UI-07 | Barrierefreiheit, Lokalisierung (Deutsch als Referenz, Texte übersetzbar), Dark Mode (durch Nextcloud-Theme). | T | – |
| REQ-UI-08 | Separate Admin-Ansicht (Zähler/Zustände). | V | DEC-USR-008 |

### 3.15 Import des Python-Fotoindexers

| ID | Anforderung | Status | Basis |
|---|---|---|---|
| REQ-IMP-01 | Optionaler Import vorhandener SQLite-Indexdaten (SHA-256, pHash). Kein MVP-Pflichtbestandteil. | V (nice-to-have) | DEC-PRD-003 |
| REQ-IMP-02 | Importierte Hashes gelten als Hinweise und müssen gegen die tatsächlichen Nextcloud-Dateien verifiziert werden (Pfad-Mapping, Größe/MTime), bevor sie Dedup-Entscheidungen tragen. | T | DEC-IMM-004 |

---

## 4. Nichtfunktionale Anforderungen

| ID | Anforderung | Status |
|---|---|---|
| NFR-SEC-01 | **Mandantentrennung:** Jede Datenbankabfrage der User-API und der Jobs ist auf `user_id` gefiltert; automatisierte Tests auf Cross-User-Zugriff. | T (Anforderung aus DEC-USR-001) |
| NFR-SEC-02 | Admin-API nutzt getrennte Datentransferobjekte ohne Medienfelder; Test, der verbotene Felder per Reflection/Schema ausschließt. | T (DEC-USR-009) |
| NFR-SEC-03 | Alle Endpunkte mit CSRF-Schutz und Nextcloud-Authentifizierung; Admin-Endpunkte nur für Admins; kein anonymer Zugriff. | T |
| NFR-SEC-04 | Dateiverarbeitung folgt dem Rechtekontext des Eigentümers (Benutzer-Ordner über `IRootFolder::getUserFolder`), nicht eines Admin-/Systemkontexts. | T (DEC-NCI-004) |
| NFR-SEC-05 | Eingaben (Pfade, Namen, Regeln) werden validiert; Pfad-Traversal ausgeschlossen. | T |
| NFR-SEC-06 | Externe Tools (z. B. ExifTool, ImageMagick/libheif), falls verwendet, werden ohne Shell-Interpolation aufgerufen und gegen bösartige Mediendateien gehärtet (Timeouts, Speicher-/CPU-Limits). | T |
| NFR-INT-01 | Datenintegrität hat Vorrang vor Durchsatz und Vollständigkeit: im Zweifel nichts tun und Prüffall erzeugen. | T (aus Auftrag, Abschnitt 6) |
| NFR-INT-02 | Crash-Sicherheit: Abbruch an beliebiger Stelle (Kill, Timeout, Neustart) hinterlässt keinen unbekannten Zustand; Wiederaufnahme anhand Journal. | T (DEC-JNL-004, DEC-JOB-006) |
| NFR-INT-03 | Kein Datenverlust bei Fehlern in Zwischenzuständen (z. B. nach Rename, vor DB-Commit). Rekonstruktion über `file_id`. | T |
| NFR-PERF-01 | Zielbestand: Größenordnung 10⁵–10⁶ Dateien pro Benutzer, mehrere Benutzer; Zielwerte sind festzulegen. | O (OQ-026) |
| NFR-PERF-02 | Hashing speicherschonend per Streaming; kein vollständiges Laden von Dateien in den Speicher. | T |
| NFR-PERF-03 | Jeder Job respektiert PHP-Limits (Zeit, Speicher) und ist kurz genug für Nextcloud-Cron. | T |
| NFR-PERF-04 | UI-Listen paginiert/virtualisiert. | T |
| NFR-OPS-01 | Betriebszustand sichtbar (Queue-Tiefe, Fehlerraten) für Admin ohne Medienbezug. | V (DEC-USR-008) |
| NFR-OPS-02 | Ausführlich dokumentierte `occ`-Befehle für Wartung. | T (PROP-ARC-005) |
| NFR-OPS-03 | Backup/Restore: Konsistenz zwischen Nextcloud-Dateien und App-Tabellen; Wiederherstellung eines älteren DB-Stands darf nicht zu Datenverlust führen (Neuabgleich per `file_id`/SHA-256). | T |
| NFR-COMP-01 | Kompatibilität nur mit explizit festgelegten Nextcloud-Versionen; API-Nutzung gegen diese Versionen verifiziert. | V (DEC-NCI-006) |
| NFR-COMP-02 | Unterstützte Datenbanken: mindestens die von Nextcloud offiziell unterstützten; Festlegung offen. | O (OQ-002) |
| NFR-PRIV-01 | Datenschutz: App-Daten sind personenbezogen; Löschkonzept (Benutzer-/App-Deinstallation); keine Übertragung an Dritte; keine externen Dienste. | T |
| NFR-I18N-01 | Deutsch Referenz; Nextcloud-L10n-Mechanismus. | T |

---

## 5. Akzeptanzkriterien auf Produktebene (Entwurf) [T]

1. Nach Installation ohne Benutzeraktivierung entstehen keine Jobs mit Dateizugriff (REQ-USR-01).
2. Ein Dry-Run verändert nachweislich keine Datei und keinen Nextcloud-Dateistatus (REQ-DRY-01).
3. Ein vollständiger Import-Zyklus erzeugt für jede Dateioperation einen Journal-Eintrag und ist per Undo prüfbar rückgängig zu machen, falls Zustand unverändert (REQ-JNL-04/05).
4. Für jede Datei: SHA-256 vor und nach Verarbeitung identisch (REQ-IMM-03).
5. Admin-API-Antworten enthalten keines der verbotenen Felder (REQ-ADM-02).
6. Zwei parallel laufende Worker erzeugen nie dieselbe Kollisionsnummer (REQ-NAM-08).
7. Eigene Operationen der App lösen keine Folge-Jobs aus (DEC-NCI-005).
8. Bei Abbruch mitten in einer Operation ist der Zustand rekonstruierbar (NFR-INT-02).

## 6. Annahmen [T]

| ID | Annahme |
|---|---|
| ASM-01 | Nextcloud läuft mit Cron als Background-Job-Modus (nicht AJAX/Webcron), sonst reicht der Durchsatz nicht. Zu prüfen. |
| ASM-02 | Der Home-Storage der Benutzer ist ein lokaler oder objektbasierter Primärspeicher; Object-Store-Primary-Storage verhält sich bei Rename/Move anders (nur Metadaten). Zu prüfen. |
| ASM-03 | Serverseitige Verschlüsselung (Encryption-App) ist nicht aktiv oder wird nicht unterstützt (Hashing wird sonst deutlich teurer, Rename-Verhalten abweichend). Zu klären. |
| ASM-04 | Nextcloud-Dateiversionierung und Papierkorb sind aktiviert. |
| ASM-05 | Einzelne Medien liegen in der Größenordnung bis wenige GB (Videos). |

## 7. Abgrenzung zu späteren Runden

Datenmodell, Zustandsautomaten, API-Verträge, Frontend-Struktur und Teststrategie werden in den jeweiligen Dokumenten fortgeschrieben. Dieses Dokument wird nur bei Änderungen fachlicher Anforderungen angepasst.

---

# Änderungen Runde 2b

Stand: 2026-10-09. Die folgenden Einträge **präzisieren oder ersetzen** frühere Anforderungen, wo angegeben. Frühere IDs bleiben bestehen; der „Ersetzt“-Hinweis gilt als maßgeblich.

## 8. Neue bzw. geänderte Anforderungen

| ID | Anforderung | Status | Basis | Verhältnis zu früherem |
|---|---|---|---|---|
| REQ-PRD-01 | Die App ist allgemein nutzbar und veröffentlichungsfähig; die Installation des Product Owners ist Referenz, nicht Annahme. | V | DEC-PRD-006/007 | neu |
| REQ-ARC-01 | Öffentliche OCP-APIs bevorzugen; interne Klassen nur gekapselt. | V | DEC-ARC-001 | neu |
| REQ-ARC-02 | Keine datenbankspezifischen Funktionen; Nextcloud-DB-Abstraktion. | V | DEC-ARC-002 | präzisiert NFR-COMP-02 |
| REQ-ARC-03 | Keine absoluten Linux-Pfade in Code, Defaults und Konfiguration. | V | DEC-ARC-003 | neu |
| REQ-ARC-04 | Nextcloud-eigene Background Jobs; kein eigener Dienst. | V | DEC-ARC-004/008 | präzisiert NFR-OPS |
| REQ-ARC-05 | Optionale Medienwerkzeuge nur über Capability Detection; Fehlen blockiert die App nie. | V | DEC-ARC-005/006 | neu |
| REQ-ARC-06 | Versionsabhängige Integration hinter Adaptern. | V | DEC-ARC-007 | neu |
| REQ-ARC-07 | Installation/Betrieb ohne zusätzliche manuelle Serverkonfiguration. | V | DEC-ARC-008 | neu |
| REQ-ELG-01 | Pro Quelle `processing_eligible` und `provenance_eligible` getrennt führen; Pausen ändern `provenance_eligible` nie. | V | DEC-DAT-013 | ersetzt Annahme „berechtigt“ in DEC-DAT-011 |
| REQ-ELG-02 | Quellenpause löst keine Neuzuordnung und keine Invalidierung aus. | V | DEC-DRY-014 | präzisiert REQ-DRY-06 |
| REQ-TMB-01 | Manuelle Löschung wird dauerhaft als Löschentscheidung geführt; kein automatischer Reimport, keine Wiederaufnahme durch Quarantänekopie. | V | DEC-DUP-008/009 | präzisiert REQ-GRP-11 |
| REQ-TMB-02 | Wiederauftauchen identischen Inhalts erzeugt Prüffall; weder automatischer Import noch automatische Löschung. | V | DEC-DUP-009 | neu |
| REQ-TMB-03 | Wiederzulassung nur manuell und protokolliert. | V (Grundsatz) | DEC-DUP-009 | neu |
| REQ-NAM-14 | Eindeutig automatisch erzeugte Originalnamen nicht anhängen; bei Unsicherheit Originalbeschreibung beibehalten. | V | DEC-NAM-011 | ersetzt REQ-NAM-03 (Erkennungsregeln bleiben Daten, `OQ-010`) |
| REQ-NAM-15 | Manuelle Änderungen von Präfix/Nummer werden nicht blind korrigiert; unklare Fälle zur Prüfung. | V | DEC-NAM-012 | präzisiert REQ-NAM-06/-09 |
| REQ-NAM-16 | Dry-Run-Nummern vorläufig; bei Ausführung keine stillen Änderungen an Datumsstrategie, Herkunft, Zielordner oder Beschreibung. Abweichung → Plan-Item veraltet. | V | DEC-NAM-013 | präzisiert REQ-NAM-08 |
| REQ-DRY-11 | Relevante Konfigurationsänderungen berücksichtigen noch nicht ausgeführte historische Plan-Items; selektive Invalidierung nach Regelkomponenten. | V | DEC-DRY-013 | ersetzt REQ-DRY-06 (Teil „relevante Regeländerung“) |
| REQ-DRY-12 | Unterscheidung abgeschlossene Operationen / freigegebene nicht ausgeführte Plan-Items / dauerhafte Incoming-Freigaben. | V | DEC-DRY-013 | neu |
| REQ-DAT-11 | Keine pauschale Altersuntergrenze; auffällige Datumswerte nur kennzeichnen (z. B. `date_quality = suspect`), nicht blockieren oder verwerfen. | V | DEC-DAT-014 | ersetzt REQ-DAT-09 |
| REQ-DAT-12 | Zeit: Originalwert, Zeitquelle, Offset, lokale Sortierzeit getrennt vorhanden. | V | DEC-DAT-015 | präzisiert REQ-DAT-03 |
| REQ-IMM-08 | Unterscheidung „keine bewusste Inhaltsänderung“ vs. Ende-zu-Ende-Integrität; Nachzustandsprüfung nach Mutationen; Move nicht als transaktional annehmen. | V | DEC-IMM-006 | präzisiert REQ-IMM-01–03 |
| REQ-IMM-09 | Externe und Storage-übergreifende Operationen sind nicht Teil des MVP. | V | DEC-IMM-007 | präzisiert REQ-SRC-06 |
| REQ-JNL-11 | Papierkorb-Wiederherstellung im MVP als angeleitete manuelle Aktion (Vorschlag); automatisch erst nach API-Verifikation. | T | PROP-JNL-003 | präzisiert REQ-JNL-07 |

## 9. Geänderte Annahmen (Abschnitt 6 oben)

- `ASM-01` (Cron) ist für die Referenz bestätigt, **für andere Installationen nicht vorauszusetzen**: Bei Webcron/AJAX sinkt der Durchsatz; die App muss dennoch korrekt, nur langsamer arbeiten.
- `ASM-03` (keine Verschlüsselung): in der Referenz erfüllt; in anderen Installationen **nicht unterstützt im MVP** (Erkennung/Verweigerung, soweit technisch möglich).
- `ASM-04` (Papierkorb aktiviert): in der Referenz erfüllt; sonst Papierkorb-Aktion verweigern.
- `ASM-02` (Storage-Typ): nicht angegeben; Home-Storage-Move nach Spike.

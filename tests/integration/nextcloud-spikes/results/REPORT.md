# Nextcloud-API-Spikes – automatisch erzeugter Ergebnisbericht

*Erzeugt aus `results/ncXX/runs/<Lauf>/results.json`. Maßgeblich sind die JSON-Dateien; dieser Bericht ist eine Lesehilfe.*

**Lesehinweis:** PASS = die geprüfte Architekturannahme wurde bestätigt, FAIL = widerlegt, INCONCLUSIVE = Test nicht aussagekräftig (Harness-Fehler, zu schwache Stichprobe, Instrumentierung nicht gegriffen). FAIL und INCONCLUSIVE sind nie PASS. Ein PASS in einem Race-Test ist nur eine endliche Stichprobe und keine Garantie. Alle Ergebnisse gelten nur für die aufgeführte Umgebung (lokaler Heim-Storage, Docker-Named-Volume) und nicht für andere Storage-Klassen.

## 1. Umgebungen

| | Nextcloud 34 | Nextcloud 35 |
|---|---|---|
| Nextcloud | 34.0.4 (34.0.4.1) | 35.0.0 (35.0.0.10) |
| PHP | 8.5.11 cli | 8.5.10 cli |
| Datenbank | mysql 11.4.13-MariaDB-ubu2404-log | mysql 11.4.13-MariaDB-ubu2404-log |
| Storage-Klasse (Heim) | OC\Files\Storage\Home | OC\Files\Storage\Home |
| Storage-Wrapper-Kette | OCA\Files_Trashbin\Storage → OCA\FilesLock\Storage\LockWrapper → OCA\PoSpikeProbe\Support\RenameHookWrapper → OC\Files\Storage\Wrapper\Quota → OC\Files\Storage\Home | OCA\Files_Trashbin\Storage → OCA\FilesLock\Storage\LockWrapper → OCA\PoSpikeProbe\Support\RenameHookWrapper → OC\Files\Storage\Wrapper\Quota → OC\Files\Storage\Home |
| Mount-Klasse | OC\Files\Mount\HomeMountPoint | OC\Files\Mount\HomeMountPoint |
| Dateisystem Datadir | ext2/ext3 | ext2/ext3 |
| Locking | OC\Lock\MemcacheLockingProvider / \OC\Memcache\Redis, filelocking=True | OC\Lock\MemcacheLockingProvider / \OC\Memcache\Redis, filelocking=True |
| Papierkorb / Versionen / Verschlüsselung | True / True / False | True / True / False |
| Testlauf | 20261009T143510Z, 20261009T153748Z, 20261009T163948Z, 20261009T164052Z, 20261009T164822Z | 20261009T143510Z, 20261009T153748Z, 20261009T163956Z, 20261009T164058Z, 20261009T164442Z |

## 2. Gesamtübersicht

| Version | PASS | FAIL | INCONCLUSIVE | Fälle |
|---|---|---|---|---|
| NC 34 | 42 | 18 | 0 | 60 |
| NC 35 | 42 | 18 | 0 | 60 |
| HashContext-Matrix (F10) | 1 | 0 | 0 | 1 |

## 3. Testfälle im Überblick

| ID | Spike | Titel | NC 34 | NC 35 | Evidenz |
|---|---|---|---|---|---|
| A01 | SP-A | Einfacher Move/Rename auf demselben Storage | PASS | PASS | Einzelbeobachtung |
| A02 | SP-A1 | Ziel existiert bereits (Datei) mit gleichem Inhalt | FAIL | FAIL | Gegenbeispiel (Annahme widerlegt) |
| A03 | SP-A1 | Ziel existiert bereits (Datei) mit anderem Inhalt | FAIL | FAIL | Gegenbeispiel (Annahme widerlegt) |
| A04 | SP-A2 | Ziel existiert als Ordner (nicht leer) / Ordner auf Datei | FAIL | FAIL | Gegenbeispiel (Annahme widerlegt) |
| A05 | SP-A4 | Quelle = Ziel, Groß-/Kleinschreibung, Unicode-Normalisierung | FAIL | FAIL | Gegenbeispiel (Annahme widerlegt) |
| A06 | SP-A5 | Verspäteter zweiter Aufruf mit veraltetem Knoten | FAIL | FAIL | Gegenbeispiel (Annahme widerlegt) |
| A07 | SP-A6 | Fehlerfälle: Zielordner fehlt, ungültiger Name, Quelle fehlt | PASS | PASS | Einzelbeobachtung |
| A08 | SP-I | Move eines zuvor geladenen, inzwischen umbenannten Knotens (folgt er der File-ID?) | PASS | PASS | Einzelbeobachtung |
| A09 | SP-A3 | Ziel entsteht zwischen Existenzprüfung und Move (Kindprozess legt das Ziel an) | FAIL | FAIL | Gegenbeispiel (Annahme widerlegt) |
| A10 | SP-A1 | Node::newFile auf vorhandenem Pfad (Neuanlage-API als mögliche Reservierung) | FAIL | FAIL | Gegenbeispiel (Annahme widerlegt) |
| A11 | SP-A1 | WebDAV MOVE mit Overwrite: F auf vorhandenes Ziel (Vergleichspunkt) | PASS | PASS | Einzelbeobachtung |
| A12 | SP-A3/SP-E | Während eines (künstlich angehaltenen) Moves: kann ein Konkurrent das Ziel anlegen? | PASS | PASS | Einzelbeobachtung |
| A13 | SP-A3 | WebDAV MOVE Overwrite: F – Ziel entsteht NACH dem Overwrite-Check (Sabre beforeMove angehalten) | FAIL | FAIL | Gegenbeispiel (Annahme widerlegt) |
| A14 | SP-A1/A3 | Prototyp „geschützter Move“ (Storage-Wrapper, interne API) mit Dateisperren | PASS | PASS | Einzelbeobachtung |
| A15 | SP-A1/A3 | Prototyp „geschützter Move“ (Storage-Wrapper, interne API) ohne Dateisperren | FAIL | FAIL | Gegenbeispiel (Annahme widerlegt) |
| A16 | SP-A | Fehler und Ausnahmen während des Moves: Listener bricht vor dem Rename ab / scheitert nach dem Rename | PASS | PASS | Einzelbeobachtung |
| A20 | SP-A3 | Konkurrenz am Ziel: node_move gegen node_newfile | FAIL (150×) | FAIL (150×) | Gegenbeispiel (Annahme widerlegt) |
| A21 | SP-A3 | Konkurrenz am Ziel: node_move gegen dav_put | FAIL (150×) | FAIL (150×) | Gegenbeispiel (Annahme widerlegt) |
| A22 | SP-A3 | Konkurrenz am Ziel: node_move gegen node_move | FAIL (150×) | FAIL (150×) | Gegenbeispiel (Annahme widerlegt) |
| A23 | SP-A3 | Konkurrenz am Ziel: dav_move_F gegen node_newfile | PASS (150×) | PASS (150×) | endliche Stichprobe (keine Garantie) |
| A24 | SP-A3 | Konkurrenz am Ziel: dav_move_F gegen dav_put | PASS (150×) | PASS (150×) | endliche Stichprobe (keine Garantie) |
| A25 | SP-A3 | Konkurrenz am Ziel: dav_move_F gegen dav_put_inm | PASS (150×) | PASS (150×) | endliche Stichprobe (keine Garantie) |
| A26 | SP-A3 | Konkurrenz am Ziel: dav_move_F gegen dav_move_F | PASS (150×) | PASS (150×) | endliche Stichprobe (keine Garantie) |
| A27 | SP-A3 | Konkurrenz am Ziel: node_move_guarded gegen node_newfile | PASS (150×) | PASS (150×) | endliche Stichprobe (keine Garantie) |
| A28 | SP-A3 | Konkurrenz am Ziel: node_move_guarded gegen dav_put | PASS (150×) | PASS (150×) | endliche Stichprobe (keine Garantie) |
| A29 | SP-A3 | Konkurrenz am Ziel: node_move_guarded gegen node_move_guarded | PASS (150×) | PASS (150×) | endliche Stichprobe (keine Garantie) |
| A30 | SP-I | Konkurrenz an der Quelle: App-Move gegen Client-Umbenennung derselben Datei | PASS (150×) | PASS (150×) | endliche Stichprobe (keine Garantie) |
| B01 | SP-B | Rename im selben Ordner: File-ID und Storage-Referenz | PASS | PASS | Einzelbeobachtung |
| B02 | SP-B | Move in anderen Ordner (inkl. Ordner-Move mit Kindern) | PASS | PASS | Einzelbeobachtung |
| B03 | SP-B | Move durch WebDAV-Client (Umbenennen, Verschieben) | PASS | PASS | Einzelbeobachtung |
| B04 | SP-B | Copy (Node::copy und WebDAV COPY) | PASS | PASS | Einzelbeobachtung |
| B05 | SP-B/SP-J | Trash und Restore: File-ID, Pfad, Storage-Referenzen | PASS | PASS | Einzelbeobachtung |
| B06 | SP-B | Inhaltsänderung (gleiche Größe): File-ID, ETag, MTime | FAIL | FAIL | Gegenbeispiel (Annahme widerlegt) |
| B07 | SP-B/SP-C | Move über Storage-Grenzen (Heim-Storage → Freigabe-Storage eines anderen Benutzers) | FAIL | FAIL | Einzelbeobachtung |
| C01 | SP-E | Node::lock – Verträglichkeitsmatrix shared/exclusive zwischen zwei Prozessen | PASS | PASS | Einzelbeobachtung |
| C02 | SP-E | Konkurrenz durch WebDAV-Client und Node-API gegen eine gesperrte Quelldatei | PASS | PASS | Einzelbeobachtung |
| C03 | SP-E | Sperre auf Zielordner: wirkt sie auf Anlage/Move von Kindern? | PASS | PASS | Einzelbeobachtung |
| C04 | SP-E | Nicht existierender Zielpfad: Sperrbarkeit über ILockingProvider (lock_target) | PASS | PASS | Einzelbeobachtung |
| C05 | SP-E | Wiedereintritt: eigene Sperre auf der Quelle und anschließender Node::move | FAIL | FAIL | Einzelbeobachtung |
| C06 | SP-E | Zwei Worker erwerben gleichzeitig dieselbe exklusive Sperre (kooperativer Mutex) | PASS (37×) | PASS (37×) | endliche Stichprobe (keine Garantie) |
| C07 | SP-E | Prozessabbruch (kill -9) mit gehaltener Sperre: bleibt die Sperre bestehen, wie lange? | PASS | PASS | Einzelbeobachtung |
| D01 | SP-J | Node::delete() verwendet den Nextcloud-Papierkorb; Identität, Erreichbarkeit und Inhalt des gelöschten Knotens | PASS | PASS | Einzelbeobachtung |
| D02 | SP-J | Nachweis einer Papierkorboperation (Pre-Delete-Hash beider Exemplare, Post-Beobachtung) | PASS | PASS | Einzelbeobachtung |
| D03 | SP-J | Zwei Löschungen gleichnamiger Dateien am selben Pfad kurz nacheinander (gleiche Sekunde) und mit 2 s Abstand | FAIL | FAIL | Gegenbeispiel (Annahme widerlegt) |
| D04 | SP-J | Restore bei inzwischen vorhandener Zieldatei | PASS | PASS | Einzelbeobachtung |
| D05 | SP-J | Löschen per WebDAV DELETE und Löschen eines Ordners | PASS | PASS | Einzelbeobachtung |
| D06 | SP-J | Fähigkeitserkennung trash_verified (nicht destruktiv) | PASS | PASS | Quelltextanalyse (nicht experimentell) |
| E01 | Events | App-interne Dateioperationen über die Node-API (CLI-Prozess): Create, Write, Rename, Move, Copy, Delete | PASS | PASS | Einzelbeobachtung |
| E02 | Events | WebDAV-Operationen eines Clients (Apache-Prozess): PUT neu/überschreiben, MOVE (Rename/Move), COPY, DELETE | PASS | PASS | Einzelbeobachtung |
| E03 | Events | Chunked Upload v2 (Nextcloud-/Mobil-/Desktop-Clients): wann und wie oft Ereignisse für die Zieldatei | PASS | PASS | Einzelbeobachtung |
| E04 | Events | Papierkorb: Delete und Restore (Node-API und WebDAV-Restore) | PASS | PASS | Einzelbeobachtung |
| E05 | Events | Identitätsmerkmale der Ereignisse (Pfad, File-ID, Quelle/Ziel) über alle Szenarien | FAIL | FAIL | Einzelbeobachtung |
| E06 | Events | Ordner-Move: Ereignisse für den Ordner und für enthaltene Dateien (group_follow-Erkennung) | PASS | PASS | Einzelbeobachtung |
| F01 | SP-H | SHA-256-Kontext: Serialisieren und Fortsetzen an vielen Offsets (reines PHP) | PASS | PASS | Einzelbeobachtung |
| F02 | SP-H | Prozessneustart: Zustand in Datei, Fortsetzung in neuem PHP-Prozess; Datei aus der Nextcloud-Node-API | PASS | PASS | Einzelbeobachtung |
| F03 | SP-H | Manipulierter Zwischenzustand: alle Einzelbitfehler im serialisierten Kontext | PASS | PASS | endliche Stichprobe (keine Garantie) |
| F04 | SP-H | Ungültiger Offset beim Fortsetzen | PASS | PASS | Einzelbeobachtung |
| F05 | SP-H | Dateiänderung während der Unterbrechung: Erkennung über Signatur (Größe, MTime, ETag) | FAIL | FAIL | Gegenbeispiel (Annahme widerlegt) |
| F06 | SP-H | Deserialisierung nur mit allowed_classes und Algorithmus-Fremdobjekte | PASS | PASS | Einzelbeobachtung |
| F07 | SP-F | Durchsatz SHA-256 über die Node-API (Messung, Referenz für Pre-/Post-Hash-Kosten) | PASS | PASS | Einzelbeobachtung |
| F10 | SP-H | HashContext-Zustand zwischen PHP-Versionen und Laufzeitumgebungen übertragen | PASS (147×) | (gleiche Matrix) | endliche Stichprobe (keine Garantie) |

## 4. Details je Testfall

### A01 – Einfacher Move/Rename auf demselben Storage

- **Spike:** SP-A · **Dokumentbezug:** job-processing.md §6.3, DEC-ARC-010, OQ-069
- **Geprüfte Annahme:** Move ohne vorhandenes Ziel verschiebt den Knoten unverändert (Inhalt, Anzahl) und liefert den Zielknoten.
- **NC 34: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 713 ms
  - Beobachtet: `{"storage": {"class": "OCA\\Files_Trashbin\\Storage", "storage_numeric_id": 3, "mount_point": "/alice/", "internal_path": "files/spike/20261009T164822Z/A01/b/photo.jpg"}}`
- **NC 35: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 1301 ms
  - Beobachtet: `{"storage": {"class": "OCA\\Files_Trashbin\\Storage", "storage_numeric_id": 3, "mount_point": "/alice/", "internal_path": "files/spike/20261009T143510Z/A01/b/photo.jpg"}}`

### A02 – Ziel existiert bereits (Datei) mit gleichem Inhalt

- **Spike:** SP-A1 · **Dokumentbezug:** job-processing.md §6.3, DEC-ARC-010, OQ-069
- **Geprüfte Annahme:** Move auf einen vorhandenen Zielpfad scheitert mit Ausnahme und lässt Ziel und Quelle unverändert (Voraussetzung für move_no_overwrite).
- **NC 34: FAIL** · Evidenz: Gegenbeispiel (Annahme widerlegt) · Versuche: 1 · Dauer 2384 ms
  - ✗ Move scheitert mit Ausnahme — `{"label": "Node::move(target.jpg)", "ok": true, "ret": {"_node": "OC\\Files\\Node\\File", "path": "/alice/files/spike/20261009T143510Z/A02/target.jpg", "id": 2054}, "exc": null, "raw": {"_node": "OC\\Files\\Node\\File", "path": "/alice/files/spike/20261009T143510Z/A02/target.jpg", "id": 2054}, "ms"…`
  - ✗ Quelle unverändert vorhanden — `null`
  - ✗ Ziel unverändert (ID und Inhalt) — `null`
  - Beobachtet: `{"overwritten_target_in_trash": [], "target_after": {"id": 2054, "type": "file", "size": 3000, "mtime": 1791556514, "etag": "01f467160fcad09ffee7a60521cf253d", "internal_path": "files/spike/20261009T143510Z/A02/target.jpg", "sha256": "27cc185c5fb71940919e3354a3579bbadc8b9c2c5fe8b9759d4fff81475f83d5"}, "source_present_after": false, "old_target_file_id_still_exists": false}`
  - Hinweis: Das vorhandene Ziel wurde ersetzt (alte File-ID existiert nicht mehr, kein Papierkorbeintrag, siehe overwritten_target_in_trash); bei gleichem Inhalt ist dennoch die Identität des alten Ziels verloren.
- **NC 35: FAIL** · Evidenz: Gegenbeispiel (Annahme widerlegt) · Versuche: 1 · Dauer 2351 ms
  - ✗ Move scheitert mit Ausnahme — `{"label": "Node::move(target.jpg)", "ok": true, "ret": {"_node": "OC\\Files\\Node\\File", "path": "/alice/files/spike/20261009T143510Z/A02/target.jpg", "id": 662}, "exc": null, "raw": {"_node": "OC\\Files\\Node\\File", "path": "/alice/files/spike/20261009T143510Z/A02/target.jpg", "id": 662}, "ms": …`
  - ✗ Quelle unverändert vorhanden — `null`
  - ✗ Ziel unverändert (ID und Inhalt) — `null`
  - Beobachtet: `{"overwritten_target_in_trash": [], "target_after": {"id": 662, "type": "file", "size": 3000, "mtime": 1791556514, "etag": "786540d248cf93de1d682ff9fed0c202", "internal_path": "files/spike/20261009T143510Z/A02/target.jpg", "sha256": "27cc185c5fb71940919e3354a3579bbadc8b9c2c5fe8b9759d4fff81475f83d5"}, "source_present_after": false, "old_target_file_id_still_exists": false}`
  - Hinweis: Das vorhandene Ziel wurde ersetzt (alte File-ID existiert nicht mehr, kein Papierkorbeintrag, siehe overwritten_target_in_trash); bei gleichem Inhalt ist dennoch die Identität des alten Ziels verloren.

### A03 – Ziel existiert bereits (Datei) mit anderem Inhalt

- **Spike:** SP-A1 · **Dokumentbezug:** job-processing.md §6.3, DEC-ARC-010, OQ-069
- **Geprüfte Annahme:** Move auf einen vorhandenen Zielpfad scheitert mit Ausnahme und lässt Ziel und Quelle unverändert (Voraussetzung für move_no_overwrite).
- **NC 34: FAIL** · Evidenz: Gegenbeispiel (Annahme widerlegt) · Versuche: 1 · Dauer 1001 ms
  - ✗ Move scheitert mit Ausnahme — `{"label": "Node::move(target.jpg)", "ok": true, "ret": {"_node": "OC\\Files\\Node\\File", "path": "/alice/files/spike/20261009T143510Z/A03/target.jpg", "id": 2057}, "exc": null, "raw": {"_node": "OC\\Files\\Node\\File", "path": "/alice/files/spike/20261009T143510Z/A03/target.jpg", "id": 2057}, "ms"…`
  - ✗ Quelle unverändert vorhanden — `null`
  - ✗ Ziel unverändert (ID und Inhalt) — `null`
  - Beobachtet: `{"overwritten_target_in_trash": [], "target_after": {"id": 2057, "type": "file", "size": 3000, "mtime": 1791556516, "etag": "e235babdfe63f95df0998afd8a798ea3", "internal_path": "files/spike/20261009T143510Z/A03/target.jpg", "sha256": "df8ba5ab0e7f84c3fb9cfc393feb565bbcc445bd0837ff265b484bcc1cd4e390"}, "source_present_after": false, "old_target_file_id_still_exists": false}`
  - Hinweis: Das vorhandene Ziel wurde ersetzt (alte File-ID existiert nicht mehr, kein Papierkorbeintrag, siehe overwritten_target_in_trash); der Inhalt des alten Ziels ist nicht mehr auffindbar.
- **NC 35: FAIL** · Evidenz: Gegenbeispiel (Annahme widerlegt) · Versuche: 1 · Dauer 984 ms
  - ✗ Move scheitert mit Ausnahme — `{"label": "Node::move(target.jpg)", "ok": true, "ret": {"_node": "OC\\Files\\Node\\File", "path": "/alice/files/spike/20261009T143510Z/A03/target.jpg", "id": 665}, "exc": null, "raw": {"_node": "OC\\Files\\Node\\File", "path": "/alice/files/spike/20261009T143510Z/A03/target.jpg", "id": 665}, "ms": …`
  - ✗ Quelle unverändert vorhanden — `null`
  - ✗ Ziel unverändert (ID und Inhalt) — `null`
  - Beobachtet: `{"overwritten_target_in_trash": [], "target_after": {"id": 665, "type": "file", "size": 3000, "mtime": 1791556516, "etag": "26201510c948a6c6a1f9b7658c427e5c", "internal_path": "files/spike/20261009T143510Z/A03/target.jpg", "sha256": "df8ba5ab0e7f84c3fb9cfc393feb565bbcc445bd0837ff265b484bcc1cd4e390"}, "source_present_after": false, "old_target_file_id_still_exists": false}`
  - Hinweis: Das vorhandene Ziel wurde ersetzt (alte File-ID existiert nicht mehr, kein Papierkorbeintrag, siehe overwritten_target_in_trash); der Inhalt des alten Ziels ist nicht mehr auffindbar.

### A04 – Ziel existiert als Ordner (nicht leer) / Ordner auf Datei

- **Spike:** SP-A2 · **Dokumentbezug:** job-processing.md §6.3, DEC-ARC-010, OQ-069
- **Geprüfte Annahme:** Move einer Datei auf einen vorhandenen Ordnerpfad scheitert und lässt den Ordner samt Inhalt unverändert; ebenso Ordner auf Datei.
- **NC 34: FAIL** · Evidenz: Gegenbeispiel (Annahme widerlegt) · Versuche: 1 · Dauer 1663 ms
  - ✗ Datei→Ordnerpfad scheitert — `{"_node": "OC\\Files\\Node\\File", "path": "/alice/files/spike/20261009T143510Z/A04/target.jpg", "id": 2060}`
  - ✗ Inhalt des Zielordners erhalten — `["file_target.txt", "file_target.txt/x.bin", "target.jpg"]`
  - ✗ Ordner→Dateipfad scheitert — `{"_node": "OC\\Files\\Node\\Folder", "path": "/alice/files/spike/20261009T143510Z/A04/file_target.txt", "id": 2064}`
  - ✗ Quellordner und Zieldatei erhalten — `null`
  - Beobachtet: `{"snapshot_after_first_call": ["file_target.txt", "folder_src", "folder_src/x.bin", "target.jpg"], "snapshot_after_second_call": ["file_target.txt", "file_target.txt/x.bin", "target.jpg"]}`
- **NC 35: FAIL** · Evidenz: Gegenbeispiel (Annahme widerlegt) · Versuche: 1 · Dauer 1561 ms
  - ✗ Datei→Ordnerpfad scheitert — `{"_node": "OC\\Files\\Node\\File", "path": "/alice/files/spike/20261009T143510Z/A04/target.jpg", "id": 668}`
  - ✗ Inhalt des Zielordners erhalten — `["file_target.txt", "file_target.txt/x.bin", "target.jpg"]`
  - ✗ Ordner→Dateipfad scheitert — `{"_node": "OC\\Files\\Node\\Folder", "path": "/alice/files/spike/20261009T143510Z/A04/file_target.txt", "id": 672}`
  - ✗ Quellordner und Zieldatei erhalten — `null`
  - Beobachtet: `{"snapshot_after_first_call": ["file_target.txt", "folder_src", "folder_src/x.bin", "target.jpg"], "snapshot_after_second_call": ["file_target.txt", "file_target.txt/x.bin", "target.jpg"]}`

### A05 – Quelle = Ziel, Groß-/Kleinschreibung, Unicode-Normalisierung

- **Spike:** SP-A4 · **Dokumentbezug:** job-processing.md §6.3, DEC-ARC-010, OQ-069
- **Geprüfte Annahme:** Move auf den eigenen Pfad ist wirkungslos oder scheitert, ohne den Inhalt zu zerstören; Namensvarianten (Groß-/Kleinschreibung, NFC/NFD) werden nicht stillschweigend zusammengeführt.
- **NC 34: FAIL** · Evidenz: Gegenbeispiel (Annahme widerlegt) · Versuche: 1 · Dauer 1325 ms
  - ✗ NFC- und NFD-Name bleiben zwei Dateien oder die Operation scheitert (keine stille Zusammenführung) — `["é.jpg"]`
  - Beobachtet: `{"same_path_exists_after": true, "same_path_sha_after": "24f3c50fb029ecc90d4153e4d140f84c2bbf126fd09e47e6065b690a9f868086", "case_rename_names": ["case.jpg"], "unicode_names_after": ["c3a92e6a7067"], "unicode_move_result": "ok"}`
  - Hinweis: Der NFD-Zielname wurde auf den NFC-Namen normalisiert und hat die vorhandene Datei ersetzt (stille Zusammenführung zweier Namensvarianten).
- **NC 35: FAIL** · Evidenz: Gegenbeispiel (Annahme widerlegt) · Versuche: 1 · Dauer 1245 ms
  - ✗ NFC- und NFD-Name bleiben zwei Dateien oder die Operation scheitert (keine stille Zusammenführung) — `["é.jpg"]`
  - Beobachtet: `{"same_path_exists_after": true, "same_path_sha_after": "24f3c50fb029ecc90d4153e4d140f84c2bbf126fd09e47e6065b690a9f868086", "case_rename_names": ["case.jpg"], "unicode_names_after": ["c3a92e6a7067"], "unicode_move_result": "ok"}`
  - Hinweis: Der NFD-Zielname wurde auf den NFC-Namen normalisiert und hat die vorhandene Datei ersetzt (stille Zusammenführung zweier Namensvarianten).

### A06 – Verspäteter zweiter Aufruf mit veraltetem Knoten

- **Spike:** SP-A5 · **Dokumentbezug:** job-processing.md §6.3, DEC-ARC-010, OQ-069
- **Geprüfte Annahme:** Ein zweiter Worker mit einem vor dem ersten Move geladenen Knoten kann nichts mehr verschieben, auch keine andere Datei, die inzwischen am alten Pfad liegt.
- **NC 34: FAIL** · Evidenz: Gegenbeispiel (Annahme widerlegt) · Versuche: 1 · Dauer 946 ms
  - ✗ veralteter Worker verschiebt NICHT die inzwischen am alten Pfad liegende fremde Datei — `{"_node": "OC\\Files\\Node\\File", "path": "/alice/files/spike/20261009T143510Z/A06/w3.jpg", "id": 2074}`
  - Beobachtet: `{"file_id_of_w1_is": 2073, "intruder_file_id": 2074, "after_paths": ["w1.jpg", "w3.jpg"], "stale_worker_moved_foreign_file": true}`
  - Hinweis: Node::move arbeitet auf dem gespeicherten Pfad, nicht auf der File-ID: ein veralteter Knoten verschiebt, was jetzt am alten Pfad liegt. move_source_by_path ist nur durch Prüfung von Pfad UND File-ID unmittelbar vor dem Aufruf herstellbar (Restfenster, siehe A21/A22).
- **NC 35: FAIL** · Evidenz: Gegenbeispiel (Annahme widerlegt) · Versuche: 1 · Dauer 916 ms
  - ✗ veralteter Worker verschiebt NICHT die inzwischen am alten Pfad liegende fremde Datei — `{"_node": "OC\\Files\\Node\\File", "path": "/alice/files/spike/20261009T143510Z/A06/w3.jpg", "id": 682}`
  - Beobachtet: `{"file_id_of_w1_is": 681, "intruder_file_id": 682, "after_paths": ["w1.jpg", "w3.jpg"], "stale_worker_moved_foreign_file": true}`
  - Hinweis: Node::move arbeitet auf dem gespeicherten Pfad, nicht auf der File-ID: ein veralteter Knoten verschiebt, was jetzt am alten Pfad liegt. move_source_by_path ist nur durch Prüfung von Pfad UND File-ID unmittelbar vor dem Aufruf herstellbar (Restfenster, siehe A21/A22).

### A07 – Fehlerfälle: Zielordner fehlt, ungültiger Name, Quelle fehlt

- **Spike:** SP-A6 · **Dokumentbezug:** job-processing.md §6.3, DEC-ARC-010, OQ-069
- **Geprüfte Annahme:** Jeder dieser Fehlerfälle endet mit einer Ausnahme und lässt den Bestand unverändert.
- **NC 34: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 1019 ms
  - Beobachtet: `{"name_mit_leerzeichen_am_ende": "akzeptiert"}`
- **NC 35: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 1042 ms
  - Beobachtet: `{"name_mit_leerzeichen_am_ende": "akzeptiert"}`

### A08 – Move eines zuvor geladenen, inzwischen umbenannten Knotens (folgt er der File-ID?)

- **Spike:** SP-I · **Dokumentbezug:** job-processing.md §6.3, DEC-ARC-010, OQ-069
- **Geprüfte Annahme:** Ein vor einer fremden Umbenennung geladener Knoten scheitert beim Move oder folgt der File-ID – er verschiebt nie eine andere Datei.
- **NC 34: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 1071 ms
  - Beobachtet: `{"webdav_rename": {"method": "MOVE", "status": 201, "curl_error": null, "etag": "\"be52e59fa46df85d3fcbfbf42e2a18f9\"", "oc_fileid": "00002078ochnb0ghvtzc", "ms": 685.5, "body_head": null}, "stale_node_getPath_after_external_rename": "/alice/files/spike/20261009T143510Z/A08/before.jpg", "stale_node_getId": 2078, "result": "failed", "getFirstNodeById_current_path": "/alice/files/spike/20261009T143510Z/A08/renamed_by_user.jpg"}`
- **NC 35: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 1030 ms
  - Beobachtet: `{"webdav_rename": {"method": "MOVE", "status": 201, "curl_error": null, "etag": "\"ca16930d330ed10376cf749a2dca93f8\"", "oc_fileid": "00000690ocii2pti8bwt", "ms": 678.7, "body_head": null}, "stale_node_getPath_after_external_rename": "/alice/files/spike/20261009T143510Z/A08/before.jpg", "stale_node_getId": 690, "result": "failed", "getFirstNodeById_current_path": "/alice/files/spike/20261009T143510Z/A08/renamed_by_user.jpg"}`

### A09 – Ziel entsteht zwischen Existenzprüfung und Move (Kindprozess legt das Ziel an)

- **Spike:** SP-A3 · **Dokumentbezug:** job-processing.md §6.3, DEC-ARC-010, OQ-069
- **Geprüfte Annahme:** Ein nodeExists()-Check vor dem Move schützt nicht: Entsteht das Ziel danach durch einen anderen Prozess, überschreibt Node::move es nicht (sonst ist ein Vorab-Check unzureichend).
- **NC 34: FAIL** · Evidenz: Gegenbeispiel (Annahme widerlegt) · Versuche: 1 · Dauer 1877 ms
  - ✗ vom anderen Prozess angelegtes Ziel unverändert, Move scheitert — `{"move": true, "target_is": "moved_source"}`
  - Beobachtet: `{"exists_check": false, "creator": {"finished": true, "results": [{"i": 0, "planned": 1791556524.7127, "started": 1791556524.71271, "lag_ms": 0.01, "res": {"op": "newfile", "ok": true, "ret": {"path": "/alice/files/spike/20261009T143510Z/A09/target.jpg", "id": 2081, "sha256": "d9d1c8c6cad059dd426194405b5ca84eed01a3261a49d390a26a4a861e5af4c3"}, "exc": null, "pid": 2822, "t0": 1791556524.713427, "t1": 1791556525.043791}}], "stderr": ""}, "target_sha_after": "2099c7b3158b618d2505de5a381f35723ba47a3af66d7836c382c3a9d5dcba38"}`
- **NC 35: FAIL** · Evidenz: Gegenbeispiel (Annahme widerlegt) · Versuche: 1 · Dauer 1880 ms
  - ✗ vom anderen Prozess angelegtes Ziel unverändert, Move scheitert — `{"move": true, "target_is": "moved_source"}`
  - Beobachtet: `{"exists_check": false, "creator": {"finished": true, "results": [{"i": 0, "planned": 1791556524.6021, "started": 1791556524.602108, "lag_ms": 0.008, "res": {"op": "newfile", "ok": true, "ret": {"path": "/alice/files/spike/20261009T143510Z/A09/target.jpg", "id": 693, "sha256": "d9d1c8c6cad059dd426194405b5ca84eed01a3261a49d390a26a4a861e5af4c3"}, "exc": null, "pid": 932, "t0": 1791556524.602784, "t1": 1791556524.919268}}], "stderr": ""}, "target_sha_after": "2099c7b3158b618d2505de5a381f35723ba47a3af66d7836c382c3a9d5dcba38"}`

### A10 – Node::newFile auf vorhandenem Pfad (Neuanlage-API als mögliche Reservierung)

- **Spike:** SP-A1 · **Dokumentbezug:** job-processing.md §6.3, DEC-ARC-010, OQ-069
- **Geprüfte Annahme:** Folder::newFile() scheitert, wenn der Pfad bereits existiert (wäre eine atomare „Reservierungs“-Primitive).
- **NC 34: FAIL** · Evidenz: Gegenbeispiel (Annahme widerlegt) · Versuche: 1 · Dauer 628 ms
  - ✗ newFile mit Inhalt scheitert, Inhalt unverändert — `{"_node": "OC\\Files\\Node\\File", "path": "/alice/files/spike/20261009T164052Z/A10/exists.jpg", "id": 11493}`
  - ✗ newFile ohne Inhalt scheitert — `{"_node": "OC\\Files\\Node\\File", "path": "/alice/files/spike/20261009T164052Z/A10/exists.jpg", "id": 11493}`
  - Beobachtet: `{"content_replaced": true}`
- **NC 35: FAIL** · Evidenz: Gegenbeispiel (Annahme widerlegt) · Versuche: 1 · Dauer 615 ms
  - ✗ newFile mit Inhalt scheitert, Inhalt unverändert — `{"_node": "OC\\Files\\Node\\File", "path": "/alice/files/spike/20261009T164058Z/A10/exists.jpg", "id": 10048}`
  - ✗ newFile ohne Inhalt scheitert — `{"_node": "OC\\Files\\Node\\File", "path": "/alice/files/spike/20261009T164058Z/A10/exists.jpg", "id": 10048}`
  - Beobachtet: `{"content_replaced": true}`

### A11 – WebDAV MOVE mit Overwrite: F auf vorhandenes Ziel (Vergleichspunkt)

- **Spike:** SP-A1 · **Dokumentbezug:** job-processing.md §6.3, DEC-ARC-010, OQ-069
- **Geprüfte Annahme:** WebDAV MOVE mit „Overwrite: F“ antwortet 412 und lässt Ziel und Quelle unverändert.
- **NC 34: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 1368 ms
  - Beobachtet: `{"dav_move_overwrite_F": {"method": "MOVE", "status": 412, "curl_error": null, "etag": null, "oc_fileid": null, "ms": 102.6, "body_head": "\n\n  Sabre\\DAV\\Exception\\PreconditionFailed\n  The destination node already exists, and the overwrite header is set to false\n  Overwrite\n\n"}, "dav_move_overwrite_T": {"method": "MOVE", "status": 204, "curl_error": null, "etag": "\"d3ed2f533917615c1ce264ad9197f445\"", "oc_fileid": "00002088ochnb0ghvtzc", "ms": 514.4, "body_head": null}, "after_overwrite_F_unchanged": true, "overwrite_T_old_target_in_trash": ["/remote.php/dav/trashbin/alice/trash/target.jpg.d1791556526"]}`
  - Hinweis: Der zweite Aufruf (Overwrite: T) ist nur eine Kontrolle: er zeigt, dass WebDAV MOVE ohne F das Ziel ersetzt.
- **NC 35: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 1325 ms
  - Beobachtet: `{"dav_move_overwrite_F": {"method": "MOVE", "status": 412, "curl_error": null, "etag": null, "oc_fileid": null, "ms": 128.6, "body_head": "\n\n  Sabre\\DAV\\Exception\\PreconditionFailed\n  The destination node already exists, and the overwrite header is set to false\n  Overwrite\n\n"}, "dav_move_overwrite_T": {"method": "MOVE", "status": 204, "curl_error": null, "etag": "\"e26e2aaf134bfa72ff41a7a9332eea20\"", "oc_fileid": "00000700ocii2pti8bwt", "ms": 493.5, "body_head": null}, "after_overwrite_F_unchanged": true, "overwrite_T_old_target_in_trash": ["/remote.php/dav/trashbin/alice/trash/target.jpg.d1791556526"]}`
  - Hinweis: Der zweite Aufruf (Overwrite: T) ist nur eine Kontrolle: er zeigt, dass WebDAV MOVE ohne F das Ziel ersetzt.

### A12 – Während eines (künstlich angehaltenen) Moves: kann ein Konkurrent das Ziel anlegen?

- **Spike:** SP-A3/SP-E · **Dokumentbezug:** job-processing.md §6.3, DEC-ARC-010, OQ-069
- **Geprüfte Annahme:** Solange Node::move innerhalb von View::rename läuft, sind Zielpfad-Anlage und Zielpfad-Move durch Dateisperren ausgeschlossen (Sperre = gegenseitiger Ausschluss, noch kein No-Overwrite).
- **NC 34: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 4836 ms
  - Beobachtet: `{"locking_off_move_frozen_inside_storage_rename": true, "locking_off_node_newFile_during_move": "success", "locking_off_dav_put_during_move": {"method": "PUT", "status": 204, "curl_error": null, "etag": "\"6a14d3313cea451835d82aca1eb4ef2a\"", "oc_fileid": "00002093ochnb0ghvtzc", "ms": 417.5, "body_head": null}, "locking_off_dav_move_during_move": {"method": "MOVE", "status": 204, "curl_error": null, "etag": "\"b0e8072cdad9233a5e5a4a2812817c2f\"", "oc_fileid": "00002092ochnb0ghvtzc", "ms": 518.9, "body_head": null}, "locking_off_mover_result": {"op": "move", "ok": true, "ret": {"path": "/alice/files/spike/20261009T143510Z/A12locking_off/target.jpg", "id": 2091, "id_before": 2091}, "exc": null, "pid": 2823, "t0": 1791556528.596269, "t1": 1791556530.063553}, "locking_on_move_frozen_inside_storage_rename": true, "locking_on_node_newFile_during_move": {"class": "OCP\\Lock\\LockedException", …`
  - Hinweis: ohne Dateisperren: Ergebnis rein beschreibend (kein Ausschluss erwartet): newFile ok, PUT 204, MOVE 204
- **NC 35: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 4770 ms
  - Beobachtet: `{"locking_off_move_frozen_inside_storage_rename": true, "locking_off_node_newFile_during_move": "success", "locking_off_dav_put_during_move": {"method": "PUT", "status": 204, "curl_error": null, "etag": "\"442b80bd5d0a7f483365048c49b5257b\"", "oc_fileid": "00000705ocii2pti8bwt", "ms": 394.1, "body_head": null}, "locking_off_dav_move_during_move": {"method": "MOVE", "status": 204, "curl_error": null, "etag": "\"623634d8a77f90d1e2840a9d622832d9\"", "oc_fileid": "00000704ocii2pti8bwt", "ms": 546.6, "body_head": null}, "locking_off_mover_result": {"op": "move", "ok": true, "ret": {"path": "/alice/files/spike/20261009T143510Z/A12locking_off/target.jpg", "id": 703, "id_before": 703}, "exc": null, "pid": 933, "t0": 1791556528.341403, "t1": 1791556529.777106}, "locking_on_move_frozen_inside_storage_rename": true, "locking_on_node_newFile_during_move": {"class": "OCP\\Lock\\LockedException", "me…`
  - Hinweis: ohne Dateisperren: Ergebnis rein beschreibend (kein Ausschluss erwartet): newFile ok, PUT 204, MOVE 204

### A13 – WebDAV MOVE Overwrite: F – Ziel entsteht NACH dem Overwrite-Check (Sabre beforeMove angehalten)

- **Spike:** SP-A3 · **Dokumentbezug:** job-processing.md §6.3, DEC-ARC-010, OQ-069
- **Geprüfte Annahme:** Auch „Overwrite: F“ bleibt sicher: Entsteht das Ziel nach dem Check von Sabre, wird es nicht ersetzt (412/Fehler).
- **NC 34: FAIL** · Evidenz: Gegenbeispiel (Annahme widerlegt) · Versuche: 1 · Dauer 1792 ms
  - ✗ das nach dem Check angelegte Ziel wurde nicht ersetzt — `{"move_ok": true, "final": "moved_source"}`
  - Beobachtet: `{"dav_move_frozen_after_overwrite_check": true, "creator_succeeded": true, "dav_move_result_after_release": {"op": "dav", "ok": true, "ret": {"method": "MOVE", "status": 201, "curl_error": null, "etag": "\"245ed5f1121ac0b680863c69a1730d21\"", "oc_fileid": "00002100ochnb0ghvtzc", "ms": 563.4, "body_head": null}, "exc": null, "pid": 2833, "t0": 1791556533.180392, "t1": 1791556533.743808}, "creator_content_in_trash": [], "final_target_is": "moved_source"}`
  - Hinweis: Overwrite: F wird von Sabre einmal vor der Ausführung geprüft; ein danach entstandenes Ziel wird vom anschließenden Rename ersetzt (kein Papierkorbeintrag, siehe creator_content_in_trash).
- **NC 35: FAIL** · Evidenz: Gegenbeispiel (Annahme widerlegt) · Versuche: 1 · Dauer 1776 ms
  - ✗ das nach dem Check angelegte Ziel wurde nicht ersetzt — `{"move_ok": true, "final": "moved_source"}`
  - Beobachtet: `{"dav_move_frozen_after_overwrite_check": true, "creator_succeeded": true, "dav_move_result_after_release": {"op": "dav", "ok": true, "ret": {"method": "MOVE", "status": 201, "curl_error": null, "etag": "\"9b7c57d6eeccbdf22b2362416637166a\"", "oc_fileid": "00000712ocii2pti8bwt", "ms": 474.8, "body_head": null}, "exc": null, "pid": 942, "t0": 1791556532.927968, "t1": 1791556533.402734}, "creator_content_in_trash": [], "final_target_is": "moved_source"}`
  - Hinweis: Overwrite: F wird von Sabre einmal vor der Ausführung geprüft; ein danach entstandenes Ziel wird vom anschließenden Rename ersetzt (kein Papierkorbeintrag, siehe creator_content_in_trash).

### A14 – Prototyp „geschützter Move“ (Storage-Wrapper, interne API) mit Dateisperren

- **Spike:** SP-A1/A3 · **Dokumentbezug:** job-processing.md §6.3, DEC-ARC-010, OQ-069
- **Geprüfte Annahme:** Ein Node::move mit Überschreibschutz INNERHALB des gesperrten Bereichs (Storage-Wrapper-Prüfung) ersetzt weder ein vorhandenes noch ein während des Moves angelegtes Ziel.
- **NC 34: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 3065 ms
  - Beobachtet: `{"existing_target_guarded_move": "OCP\\Files\\NotPermittedException", "creator_node_newFile": "OCP\\Lock\\LockedException", "creator_dav_put": {"method": "PUT", "status": 423, "curl_error": null, "etag": null, "oc_fileid": null, "ms": 103.2, "body_head": "\n\n  OCA\\DAV\\Connector\\Sabre\\Exception\\FileLocked\n  \"/spike/20261009T143510Z/A14/target.bin\" is locked, existing lock on file: exclusive\n\n"}, "guarded_mover_result": "ok", "final_target_is": "moved_source"}`
- **NC 35: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 3081 ms
  - Beobachtet: `{"existing_target_guarded_move": "OCP\\Files\\NotPermittedException", "creator_node_newFile": "OCP\\Lock\\LockedException", "creator_dav_put": {"method": "PUT", "status": 423, "curl_error": null, "etag": null, "oc_fileid": null, "ms": 93.7, "body_head": "\n\n  OCA\\DAV\\Connector\\Sabre\\Exception\\FileLocked\n  \"/spike/20261009T143510Z/A14/target.bin\" is locked, existing lock on file: exclusive\n\n"}, "guarded_mover_result": "ok", "final_target_is": "moved_source"}`

### A15 – Prototyp „geschützter Move“ (Storage-Wrapper, interne API) ohne Dateisperren

- **Spike:** SP-A1/A3 · **Dokumentbezug:** job-processing.md §6.3, DEC-ARC-010, OQ-069
- **Geprüfte Annahme:** Der geschützte Move bleibt auch ohne aktivierte Dateisperren sicher (Erwartung der Architektur: nein – die Sicherheit hängt an den Sperren).
- **NC 34: FAIL** · Evidenz: Gegenbeispiel (Annahme widerlegt) · Versuche: 1 · Dauer 3620 ms
  - ✗ ein während des Moves erfolgreich angelegter Inhalt wurde nicht ersetzt — `{"creator_succeeded": true, "mover_ok": true}`
  - Beobachtet: `{"existing_target_guarded_move": "OCP\\Files\\NotPermittedException", "creator_node_newFile": "success", "creator_dav_put": {"method": "PUT", "status": 204, "curl_error": null, "etag": "\"1238129526ed0bf663b22e671bb44963\"", "oc_fileid": "00002110ochnb0ghvtzc", "ms": 330, "body_head": null}, "guarded_mover_result": "ok", "final_target_is": "moved_source"}`
- **NC 35: FAIL** · Evidenz: Gegenbeispiel (Annahme widerlegt) · Versuche: 1 · Dauer 3523 ms
  - ✗ ein während des Moves erfolgreich angelegter Inhalt wurde nicht ersetzt — `{"creator_succeeded": true, "mover_ok": true}`
  - Beobachtet: `{"existing_target_guarded_move": "OCP\\Files\\NotPermittedException", "creator_node_newFile": "success", "creator_dav_put": {"method": "PUT", "status": 204, "curl_error": null, "etag": "\"94b094224da2da2615e08c4c123c0a4d\"", "oc_fileid": "00000722ocii2pti8bwt", "ms": 323.1, "body_head": null}, "guarded_mover_result": "ok", "final_target_is": "moved_source"}`

### A16 – Fehler und Ausnahmen während des Moves: Listener bricht vor dem Rename ab / scheitert nach dem Rename

- **Spike:** SP-A · **Dokumentbezug:** job-processing.md §6.3, DEC-ARC-010, OQ-069
- **Geprüfte Annahme:** Ein Fehler eines Event-Listeners vor dem Rename bricht den Move sauber ab (Ausnahme, Datei unverändert); ein Listener-Fehler nach dem Rename erreicht den Aufrufer nicht (der Move gilt als erfolgreich, die Wirkung ist eingetreten).
- **NC 34: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 904 ms
  - Beobachtet: `{"before_listener_abort": {"exception": {"class": "OCP\\Files\\NotPermittedException", "message": "Could not move /alice/files/spike/20261009T153748Z/A16/a.bin to /alice/files/spike/20261009T153748Z/A16/a-target.bin", "code": 0, "prev": null}, "source_still_there": true, "target_exists": false}, "after_listener_failure": {"exception_reaches_caller": false, "exception": null, "source_still_there": false, "target_exists": true}}`
  - Hinweis: Fehler in After-Listenern fremder Apps bleiben für den Aufrufer unsichtbar (nur Protokoll). Umgekehrt zeigt A21/A24/A25/A28, dass ein als gescheitert gemeldeter DAV-PUT seinen Inhalt dennoch hinterlassen kann („Fehler gemeldet, Wirkung vorhanden“).
- **NC 35: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 901 ms
  - Beobachtet: `{"before_listener_abort": {"exception": {"class": "OCP\\Files\\NotPermittedException", "message": "Could not move /alice/files/spike/20261009T153748Z/A16/a.bin to /alice/files/spike/20261009T153748Z/A16/a-target.bin", "code": 0, "prev": null}, "source_still_there": true, "target_exists": false}, "after_listener_failure": {"exception_reaches_caller": false, "exception": null, "source_still_there": false, "target_exists": true}}`
  - Hinweis: Fehler in After-Listenern fremder Apps bleiben für den Aufrufer unsichtbar (nur Protokoll). Umgekehrt zeigt A21/A24/A25/A28, dass ein als gescheitert gemeldeter DAV-PUT seinen Inhalt dennoch hinterlassen kann („Fehler gemeldet, Wirkung vorhanden“).

### A20 – Konkurrenz am Ziel: node_move gegen node_newfile

- **Spike:** SP-A3 · **Dokumentbezug:** job-processing.md §6.3, DEC-ARC-010, OQ-069
- **Geprüfte Annahme:** Unter Konkurrenz (zwei Prozesse, node_move und node_newfile auf denselben Zielpfad, Startversatz −150…+150 ms, davon ein Drittel fein −5…+5 ms) wird nie ein erfolgreich geschriebener Inhalt durch einen erfolgreichen Move ersetzt.
- **NC 34: FAIL** · Evidenz: Gegenbeispiel (Annahme widerlegt) · Versuche: 150 · Dauer 224628 ms
  - Tally: `{"M_fail/S_fail/no_target": 57, "both_failed": 57, "M_fail/S_ok/final_S": 20, "M_ok/S_fail/final_M": 15, "M_ok/S_ok/final_S": 31, "second_writer_replaced_move_result": 31, "content_of_successful_mover_not_present_anywhere": 31, "M_ok/S_ok/final_M": 27, "VIOLATION_move_replaced_successful_write": 27}`
  - ✗ keine Verletzung in 150 ausgewerteten Versuchen (ausgerichtet ≤2 ms: 150) — `27`
  - Beobachtet: `{"iterations": 150, "aligned_within_2ms": 150, "completed_iterations": 150}`
- **NC 35: FAIL** · Evidenz: Gegenbeispiel (Annahme widerlegt) · Versuche: 150 · Dauer 223933 ms
  - Tally: `{"M_fail/S_fail/no_target": 55, "both_failed": 55, "M_fail/S_ok/final_S": 19, "M_ok/S_fail/final_M": 14, "M_ok/S_ok/final_S": 33, "second_writer_replaced_move_result": 33, "content_of_successful_mover_not_present_anywhere": 33, "M_ok/S_ok/final_M": 29, "VIOLATION_move_replaced_successful_write": 29}`
  - ✗ keine Verletzung in 150 ausgewerteten Versuchen (ausgerichtet ≤2 ms: 150) — `29`
  - Beobachtet: `{"iterations": 150, "aligned_within_2ms": 150, "completed_iterations": 150}`

### A21 – Konkurrenz am Ziel: node_move gegen dav_put

- **Spike:** SP-A3 · **Dokumentbezug:** job-processing.md §6.3, DEC-ARC-010, OQ-069
- **Geprüfte Annahme:** Unter Konkurrenz (zwei Prozesse, node_move und dav_put auf denselben Zielpfad, Startversatz −150…+150 ms, davon ein Drittel fein −5…+5 ms) wird nie ein erfolgreich geschriebener Inhalt durch einen erfolgreichen Move ersetzt.
- **NC 34: FAIL** · Evidenz: Gegenbeispiel (Annahme widerlegt) · Versuche: 150 · Dauer 407449 ms
  - Tally: `{"M_ok/S_ok/final_S": 48, "second_writer_replaced_move_result": 48, "content_of_successful_mover_not_present_anywhere": 48, "M_fail/S_ok/final_S": 15, "M_ok/S_fail/final_M": 66, "M_ok/S_ok/final_M": 17, "VIOLATION_move_replaced_successful_write": 17, "M_fail/S_fail/final_S": 4, "FAIL_REPORTED_BUT_SECOND_EFFECT_PRESENT": 4, "both_failed": 4}`
  - ✗ keine Verletzung in 150 ausgewerteten Versuchen (ausgerichtet ≤2 ms: 150) — `17`
  - Beobachtet: `{"iterations": 150, "aligned_within_2ms": 150, "completed_iterations": 150}`
- **NC 35: FAIL** · Evidenz: Gegenbeispiel (Annahme widerlegt) · Versuche: 150 · Dauer 405613 ms
  - Tally: `{"M_ok/S_ok/final_S": 53, "second_writer_replaced_move_result": 53, "content_of_successful_mover_not_present_anywhere": 53, "M_fail/S_ok/final_S": 21, "M_ok/S_fail/final_M": 61, "M_ok/S_ok/final_M": 13, "VIOLATION_move_replaced_successful_write": 13, "M_fail/S_fail/final_S": 1, "FAIL_REPORTED_BUT_SECOND_EFFECT_PRESENT": 1, "both_failed": 2, "M_fail/S_fail/no_target": 1}`
  - ✗ keine Verletzung in 150 ausgewerteten Versuchen (ausgerichtet ≤2 ms: 150) — `13`
  - Beobachtet: `{"iterations": 150, "aligned_within_2ms": 150, "completed_iterations": 150}`

### A22 – Konkurrenz am Ziel: node_move gegen node_move

- **Spike:** SP-A3 · **Dokumentbezug:** job-processing.md §6.3, DEC-ARC-010, OQ-069
- **Geprüfte Annahme:** Unter Konkurrenz (zwei Prozesse, node_move und node_move auf denselben Zielpfad, Startversatz −150…+150 ms, davon ein Drittel fein −5…+5 ms) wird nie ein erfolgreich geschriebener Inhalt durch einen erfolgreichen Move ersetzt.
- **NC 34: FAIL** · Evidenz: Gegenbeispiel (Annahme widerlegt) · Versuche: 150 · Dauer 230844 ms
  - Tally: `{"M_fail/S_fail/no_target": 54, "both_failed": 54, "M_fail/S_ok/final_S": 20, "M_ok/S_fail/final_M": 19, "M_ok/S_ok/final_S": 30, "second_writer_replaced_move_result": 30, "content_of_successful_mover_not_present_anywhere": 30, "M_ok/S_ok/final_M": 27, "VIOLATION_move_replaced_successful_write": 27}`
  - ✗ keine Verletzung in 150 ausgewerteten Versuchen (ausgerichtet ≤2 ms: 150) — `27`
  - Beobachtet: `{"iterations": 150, "aligned_within_2ms": 150, "completed_iterations": 150}`
- **NC 35: FAIL** · Evidenz: Gegenbeispiel (Annahme widerlegt) · Versuche: 150 · Dauer 230260 ms
  - Tally: `{"M_fail/S_fail/no_target": 54, "both_failed": 54, "M_fail/S_ok/final_S": 18, "M_ok/S_fail/final_M": 18, "M_ok/S_ok/final_M": 29, "VIOLATION_move_replaced_successful_write": 29, "M_ok/S_ok/final_S": 31, "second_writer_replaced_move_result": 31, "content_of_successful_mover_not_present_anywhere": 31}`
  - ✗ keine Verletzung in 150 ausgewerteten Versuchen (ausgerichtet ≤2 ms: 150) — `29`
  - Beobachtet: `{"iterations": 150, "aligned_within_2ms": 150, "completed_iterations": 150}`

### A23 – Konkurrenz am Ziel: dav_move_F gegen node_newfile

- **Spike:** SP-A3 · **Dokumentbezug:** job-processing.md §6.3, DEC-ARC-010, OQ-069
- **Geprüfte Annahme:** Unter Konkurrenz (zwei Prozesse, dav_move_F und node_newfile auf denselben Zielpfad, Startversatz −150…+150 ms, davon ein Drittel fein −5…+5 ms) wird nie ein erfolgreich geschriebener Inhalt durch einen erfolgreichen Move ersetzt.
- **NC 34: PASS** · Evidenz: endliche Stichprobe (keine Garantie) · Versuche: 150 · Dauer 392082 ms
  - Tally: `{"M_fail/S_ok/final_S": 113, "M_ok/S_ok/final_S": 30, "second_writer_replaced_move_result": 30, "content_of_successful_mover_not_present_anywhere": 30, "M_ok/S_fail/final_M": 6, "M_fail/S_fail/no_target": 1, "both_failed": 1}`
  - Beobachtet: `{"iterations": 150, "aligned_within_2ms": 150, "completed_iterations": 150}`
  - Hinweis: Kein Verstoß in 150 Versuchen; das ist keine Garantie, sondern eine endliche Stichprobe (ausgerichtet ≤2 ms: 150).
- **NC 35: PASS** · Evidenz: endliche Stichprobe (keine Garantie) · Versuche: 150 · Dauer 391195 ms
  - Tally: `{"M_fail/S_ok/final_S": 111, "M_ok/S_fail/final_M": 10, "M_ok/S_ok/final_S": 27, "second_writer_replaced_move_result": 27, "content_of_successful_mover_not_present_anywhere": 27, "M_fail/S_fail/no_target": 2, "both_failed": 2}`
  - Beobachtet: `{"iterations": 150, "aligned_within_2ms": 150, "completed_iterations": 150}`
  - Hinweis: Kein Verstoß in 150 Versuchen; das ist keine Garantie, sondern eine endliche Stichprobe (ausgerichtet ≤2 ms: 150).

### A24 – Konkurrenz am Ziel: dav_move_F gegen dav_put

- **Spike:** SP-A3 · **Dokumentbezug:** job-processing.md §6.3, DEC-ARC-010, OQ-069
- **Geprüfte Annahme:** Unter Konkurrenz (zwei Prozesse, dav_move_F und dav_put auf denselben Zielpfad, Startversatz −150…+150 ms, davon ein Drittel fein −5…+5 ms) wird nie ein erfolgreich geschriebener Inhalt durch einen erfolgreichen Move ersetzt.
- **NC 34: PASS** · Evidenz: endliche Stichprobe (keine Garantie) · Versuche: 150 · Dauer 390906 ms
  - Tally: `{"M_fail/S_fail/final_S": 50, "FAIL_REPORTED_BUT_SECOND_EFFECT_PRESENT": 50, "both_failed": 50, "M_ok/S_ok/final_S": 39, "second_writer_replaced_move_result": 39, "content_of_successful_mover_not_present_anywhere": 39, "M_fail/S_ok/final_S": 53, "M_ok/S_fail/final_M": 8}`
  - Beobachtet: `{"iterations": 150, "aligned_within_2ms": 150, "completed_iterations": 150}`
  - Hinweis: Kein Verstoß in 150 Versuchen; das ist keine Garantie, sondern eine endliche Stichprobe (ausgerichtet ≤2 ms: 150).
- **NC 35: PASS** · Evidenz: endliche Stichprobe (keine Garantie) · Versuche: 150 · Dauer 391542 ms
  - Tally: `{"M_fail/S_fail/final_S": 50, "FAIL_REPORTED_BUT_SECOND_EFFECT_PRESENT": 50, "both_failed": 50, "M_fail/S_ok/final_S": 52, "M_ok/S_fail/final_M": 12, "M_ok/S_ok/final_S": 36, "second_writer_replaced_move_result": 36, "content_of_successful_mover_not_present_anywhere": 36}`
  - Beobachtet: `{"iterations": 150, "aligned_within_2ms": 150, "completed_iterations": 150}`
  - Hinweis: Kein Verstoß in 150 Versuchen; das ist keine Garantie, sondern eine endliche Stichprobe (ausgerichtet ≤2 ms: 150).

### A25 – Konkurrenz am Ziel: dav_move_F gegen dav_put_inm

- **Spike:** SP-A3 · **Dokumentbezug:** job-processing.md §6.3, DEC-ARC-010, OQ-069
- **Geprüfte Annahme:** Unter Konkurrenz (zwei Prozesse, dav_move_F und dav_put_inm auf denselben Zielpfad, Startversatz −150…+150 ms, davon ein Drittel fein −5…+5 ms) wird nie ein erfolgreich geschriebener Inhalt durch einen erfolgreichen Move ersetzt.
- **NC 34: PASS** · Evidenz: endliche Stichprobe (keine Garantie) · Versuche: 150 · Dauer 390713 ms
  - Tally: `{"M_fail/S_fail/final_S": 48, "FAIL_REPORTED_BUT_SECOND_EFFECT_PRESENT": 48, "both_failed": 48, "M_fail/S_ok/final_S": 55, "M_ok/S_fail/final_M": 47}`
  - Beobachtet: `{"iterations": 150, "aligned_within_2ms": 150, "completed_iterations": 150}`
  - Hinweis: Kein Verstoß in 150 Versuchen; das ist keine Garantie, sondern eine endliche Stichprobe (ausgerichtet ≤2 ms: 150).
- **NC 35: PASS** · Evidenz: endliche Stichprobe (keine Garantie) · Versuche: 150 · Dauer 390356 ms
  - Tally: `{"M_fail/S_fail/final_S": 48, "FAIL_REPORTED_BUT_SECOND_EFFECT_PRESENT": 48, "both_failed": 48, "M_fail/S_ok/final_S": 54, "M_ok/S_fail/final_M": 47, "M_ok/S_ok/final_S": 1, "second_writer_replaced_move_result": 1, "content_of_successful_mover_not_present_anywhere": 1}`
  - Beobachtet: `{"iterations": 150, "aligned_within_2ms": 150, "completed_iterations": 150}`
  - Hinweis: Kein Verstoß in 150 Versuchen; das ist keine Garantie, sondern eine endliche Stichprobe (ausgerichtet ≤2 ms: 150).

### A26 – Konkurrenz am Ziel: dav_move_F gegen dav_move_F

- **Spike:** SP-A3 · **Dokumentbezug:** job-processing.md §6.3, DEC-ARC-010, OQ-069
- **Geprüfte Annahme:** Unter Konkurrenz (zwei Prozesse, dav_move_F und dav_move_F auf denselben Zielpfad, Startversatz −150…+150 ms, davon ein Drittel fein −5…+5 ms) wird nie ein erfolgreich geschriebener Inhalt durch einen erfolgreichen Move ersetzt.
- **NC 34: PASS** · Evidenz: endliche Stichprobe (keine Garantie) · Versuche: 150 · Dauer 409731 ms
  - Tally: `{"M_fail/S_fail/no_target": 53, "both_failed": 53, "M_fail/S_ok/final_S": 48, "M_ok/S_fail/final_M": 49}`
  - Beobachtet: `{"iterations": 150, "aligned_within_2ms": 150, "completed_iterations": 150}`
  - Hinweis: Kein Verstoß in 150 Versuchen; das ist keine Garantie, sondern eine endliche Stichprobe (ausgerichtet ≤2 ms: 150).
- **NC 35: PASS** · Evidenz: endliche Stichprobe (keine Garantie) · Versuche: 150 · Dauer 409882 ms
  - Tally: `{"M_fail/S_fail/no_target": 55, "both_failed": 55, "M_fail/S_ok/final_S": 46, "M_ok/S_fail/final_M": 49}`
  - Beobachtet: `{"iterations": 150, "aligned_within_2ms": 150, "completed_iterations": 150}`
  - Hinweis: Kein Verstoß in 150 Versuchen; das ist keine Garantie, sondern eine endliche Stichprobe (ausgerichtet ≤2 ms: 150).

### A27 – Konkurrenz am Ziel: node_move_guarded gegen node_newfile

- **Spike:** SP-A3 · **Dokumentbezug:** job-processing.md §6.3, DEC-ARC-010, OQ-069
- **Geprüfte Annahme:** Unter Konkurrenz (zwei Prozesse, node_move_guarded und node_newfile auf denselben Zielpfad, Startversatz −150…+150 ms, davon ein Drittel fein −5…+5 ms) wird nie ein erfolgreich geschriebener Inhalt durch einen erfolgreichen Move ersetzt.
- **NC 34: PASS** · Evidenz: endliche Stichprobe (keine Garantie) · Versuche: 150 · Dauer 212433 ms
  - Tally: `{"M_fail/S_fail/no_target": 49, "both_failed": 49, "M_fail/S_ok/final_S": 51, "M_ok/S_ok/final_S": 41, "second_writer_replaced_move_result": 41, "content_of_successful_mover_not_present_anywhere": 41, "M_ok/S_fail/final_M": 9}`
  - Beobachtet: `{"iterations": 150, "aligned_within_2ms": 150, "completed_iterations": 150}`
  - Hinweis: Kein Verstoß in 150 Versuchen; das ist keine Garantie, sondern eine endliche Stichprobe (ausgerichtet ≤2 ms: 150).
- **NC 35: PASS** · Evidenz: endliche Stichprobe (keine Garantie) · Versuche: 150 · Dauer 211032 ms
  - Tally: `{"M_fail/S_fail/no_target": 49, "both_failed": 49, "M_fail/S_ok/final_S": 52, "M_ok/S_ok/final_S": 40, "second_writer_replaced_move_result": 40, "content_of_successful_mover_not_present_anywhere": 40, "M_ok/S_fail/final_M": 9}`
  - Beobachtet: `{"iterations": 150, "aligned_within_2ms": 150, "completed_iterations": 150}`
  - Hinweis: Kein Verstoß in 150 Versuchen; das ist keine Garantie, sondern eine endliche Stichprobe (ausgerichtet ≤2 ms: 150).

### A28 – Konkurrenz am Ziel: node_move_guarded gegen dav_put

- **Spike:** SP-A3 · **Dokumentbezug:** job-processing.md §6.3, DEC-ARC-010, OQ-069
- **Geprüfte Annahme:** Unter Konkurrenz (zwei Prozesse, node_move_guarded und dav_put auf denselben Zielpfad, Startversatz −150…+150 ms, davon ein Drittel fein −5…+5 ms) wird nie ein erfolgreich geschriebener Inhalt durch einen erfolgreichen Move ersetzt.
- **NC 34: PASS** · Evidenz: endliche Stichprobe (keine Garantie) · Versuche: 150 · Dauer 390871 ms
  - Tally: `{"M_ok/S_ok/final_S": 65, "second_writer_replaced_move_result": 65, "content_of_successful_mover_not_present_anywhere": 65, "M_fail/S_ok/final_S": 36, "M_ok/S_fail/final_M": 48, "M_fail/S_fail/final_S": 1, "FAIL_REPORTED_BUT_SECOND_EFFECT_PRESENT": 1, "both_failed": 1}`
  - Beobachtet: `{"iterations": 150, "aligned_within_2ms": 150, "completed_iterations": 150}`
  - Hinweis: Kein Verstoß in 150 Versuchen; das ist keine Garantie, sondern eine endliche Stichprobe (ausgerichtet ≤2 ms: 150).
- **NC 35: PASS** · Evidenz: endliche Stichprobe (keine Garantie) · Versuche: 150 · Dauer 389801 ms
  - Tally: `{"M_ok/S_fail/final_M": 47, "M_fail/S_ok/final_S": 36, "M_ok/S_ok/final_S": 64, "second_writer_replaced_move_result": 64, "content_of_successful_mover_not_present_anywhere": 64, "M_fail/S_fail/final_S": 3, "FAIL_REPORTED_BUT_SECOND_EFFECT_PRESENT": 3, "both_failed": 3}`
  - Beobachtet: `{"iterations": 150, "aligned_within_2ms": 150, "completed_iterations": 150}`
  - Hinweis: Kein Verstoß in 150 Versuchen; das ist keine Garantie, sondern eine endliche Stichprobe (ausgerichtet ≤2 ms: 150).

### A29 – Konkurrenz am Ziel: node_move_guarded gegen node_move_guarded

- **Spike:** SP-A3 · **Dokumentbezug:** job-processing.md §6.3, DEC-ARC-010, OQ-069
- **Geprüfte Annahme:** Unter Konkurrenz (zwei Prozesse, node_move_guarded und node_move_guarded auf denselben Zielpfad, Startversatz −150…+150 ms, davon ein Drittel fein −5…+5 ms) wird nie ein erfolgreich geschriebener Inhalt durch einen erfolgreichen Move ersetzt.
- **NC 34: PASS** · Evidenz: endliche Stichprobe (keine Garantie) · Versuche: 150 · Dauer 231976 ms
  - Tally: `{"M_fail/S_fail/no_target": 54, "both_failed": 54, "M_fail/S_ok/final_S": 47, "M_ok/S_fail/final_M": 49}`
  - Beobachtet: `{"iterations": 150, "aligned_within_2ms": 150, "completed_iterations": 150}`
  - Hinweis: Kein Verstoß in 150 Versuchen; das ist keine Garantie, sondern eine endliche Stichprobe (ausgerichtet ≤2 ms: 150).
- **NC 35: PASS** · Evidenz: endliche Stichprobe (keine Garantie) · Versuche: 150 · Dauer 229632 ms
  - Tally: `{"M_fail/S_fail/no_target": 55, "both_failed": 55, "M_fail/S_ok/final_S": 47, "M_ok/S_fail/final_M": 48}`
  - Beobachtet: `{"iterations": 150, "aligned_within_2ms": 150, "completed_iterations": 150}`
  - Hinweis: Kein Verstoß in 150 Versuchen; das ist keine Garantie, sondern eine endliche Stichprobe (ausgerichtet ≤2 ms: 150).

### A30 – Konkurrenz an der Quelle: App-Move gegen Client-Umbenennung derselben Datei

- **Spike:** SP-I · **Dokumentbezug:** job-processing.md §6.3, DEC-ARC-010, OQ-069
- **Geprüfte Annahme:** Bei gleichzeitiger Umbenennung der Quelle durch einen Client und App-Move geht nie Inhalt verloren und erscheint nie doppelt: die Datei liegt danach genau einmal entweder am App-Ziel oder am Client-Namen.
- **NC 34: PASS** · Evidenz: endliche Stichprobe (keine Garantie) · Versuche: 150 · Dauer 404942 ms
  - Tally: `{"app_ok/client_fail/at:app-target.bin": 119, "app_fail/client_fail/at:src.bin": 5, "app_fail/client_ok/at:client-renamed.bin": 26}`
  - Beobachtet: `{"iterations": 150, "aligned_within_2ms": 150}`
- **NC 35: PASS** · Evidenz: endliche Stichprobe (keine Garantie) · Versuche: 150 · Dauer 403950 ms
  - Tally: `{"app_ok/client_fail/at:app-target.bin": 116, "app_fail/client_ok/at:client-renamed.bin": 27, "app_fail/client_fail/at:src.bin": 7}`
  - Beobachtet: `{"iterations": 150, "aligned_within_2ms": 150}`

### B01 – Rename im selben Ordner: File-ID und Storage-Referenz

- **Spike:** SP-B · **Dokumentbezug:** nextcloud-integration.md §2.2, SP-B, SP-C, DEC-IMM / Identitätsmodell
- **Geprüfte Annahme:** Rename (Node::move im selben Ordner) erhält die File-ID; Storage und Mount bleiben gleich; nur Pfad/internal_path/ETag ändern sich.
- **NC 34: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 418 ms
  - Beobachtet: `{"before": {"id": 6566, "path": "/alice/files/spike/20261009T143510Z/B01/a.jpg", "etag": "28d3d059ab41995ab0000f958afa289e", "mtime": 1791560013, "size": 3000, "class": "OCA\\Files_Trashbin\\Storage", "storage_numeric_id": 3, "mount_point": "/alice/", "internal_path": "files/spike/20261009T143510Z/B01/a.jpg", "parent_id": 6565}, "after": {"id": 6566, "path": "/alice/files/spike/20261009T143510Z/B01/b.jpg", "etag": "28d3d059ab41995ab0000f958afa289e", "mtime": 1791560013, "size": 3000, "class": "OCA\\Files_Trashbin\\Storage", "storage_numeric_id": 3, "mount_point": "/alice/", "internal_path": "files/spike/20261009T143510Z/B01/b.jpg", "parent_id": 6565}, "etag_changed": false, "mtime_changed": false}`
- **NC 35: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 519 ms
  - Beobachtet: `{"before": {"id": 95, "path": "/alice/files/spike/20261009T164442Z/B01/a.jpg", "etag": "f91b71f27e369904ad6d7d1bd24ccc2d", "mtime": 1791564284, "size": 3000, "class": "OCA\\Files_Trashbin\\Storage", "storage_numeric_id": 3, "mount_point": "/alice/", "internal_path": "files/spike/20261009T164442Z/B01/a.jpg", "parent_id": 94}, "after": {"id": 95, "path": "/alice/files/spike/20261009T164442Z/B01/b.jpg", "etag": "f91b71f27e369904ad6d7d1bd24ccc2d", "mtime": 1791564284, "size": 3000, "class": "OCA\\Files_Trashbin\\Storage", "storage_numeric_id": 3, "mount_point": "/alice/", "internal_path": "files/spike/20261009T164442Z/B01/b.jpg", "parent_id": 94}, "etag_changed": false, "mtime_changed": false}`

### B02 – Move in anderen Ordner (inkl. Ordner-Move mit Kindern)

- **Spike:** SP-B · **Dokumentbezug:** nextcloud-integration.md §2.2, SP-B, SP-C, DEC-IMM / Identitätsmodell
- **Geprüfte Annahme:** Move in einen anderen Ordner desselben Storage erhält die File-ID; ein Ordner-Move erhält auch die IDs aller Kinder.
- **NC 34: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 1739 ms
- **NC 35: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 1035 ms

### B03 – Move durch WebDAV-Client (Umbenennen, Verschieben)

- **Spike:** SP-B · **Dokumentbezug:** nextcloud-integration.md §2.2, SP-B, SP-C, DEC-IMM / Identitätsmodell
- **Geprüfte Annahme:** WebDAV MOVE erhält die File-ID wie Node::move (Client-Sync-Verhalten).
- **NC 34: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 1097 ms
  - Beobachtet: `{"dav": [{"method": "MOVE", "status": 201, "curl_error": null, "etag": "\"d08b6f4ad22f869d552f6daf78f9276b\"", "oc_fileid": "00006576ochnb0ghvtzc", "ms": 268.1, "body_head": null}, {"method": "MOVE", "status": 201, "curl_error": null, "etag": "\"d08b6f4ad22f869d552f6daf78f9276b\"", "oc_fileid": "00006576ochnb0ghvtzc", "ms": 328.1, "body_head": null}], "oc_fileid_header_prefix_matches": true}`
- **NC 35: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 1642 ms
  - Beobachtet: `{"dav": [{"method": "MOVE", "status": 201, "curl_error": null, "etag": "\"35ce1a8ff6d8e0bd1240a2943ef4a182\"", "oc_fileid": "00000105ocvpw36zi0xw", "ms": 1080.3, "body_head": null}, {"method": "MOVE", "status": 201, "curl_error": null, "etag": "\"35ce1a8ff6d8e0bd1240a2943ef4a182\"", "oc_fileid": "00000105ocvpw36zi0xw", "ms": 307.3, "body_head": null}], "oc_fileid_header_prefix_matches": true}`

### B04 – Copy (Node::copy und WebDAV COPY)

- **Spike:** SP-B · **Dokumentbezug:** nextcloud-integration.md §2.2, SP-B, SP-C, DEC-IMM / Identitätsmodell
- **Geprüfte Annahme:** Copy erzeugt eine neue File-ID; die Quelle bleibt unverändert.
- **NC 34: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 886 ms
  - Beobachtet: `{"dav_copy": {"method": "COPY", "status": 201, "curl_error": null, "etag": null, "oc_fileid": "00006580ochnb0ghvtzc", "ms": 293.9, "body_head": null}}`
- **NC 35: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 613 ms
  - Beobachtet: `{"dav_copy": {"method": "COPY", "status": 201, "curl_error": null, "etag": null, "oc_fileid": "00000110ocvpw36zi0xw", "ms": 277.3, "body_head": null}}`

### B05 – Trash und Restore: File-ID, Pfad, Storage-Referenzen

- **Spike:** SP-B/SP-J · **Dokumentbezug:** nextcloud-integration.md §2.2, SP-B, SP-C, DEC-IMM / Identitätsmodell
- **Geprüfte Annahme:** Beim Löschen in den Papierkorb bleibt die File-ID erhalten (Papierkorbeintrag trägt dieselbe ID); die Wiederherstellung stellt Pfad und ID wieder her.
- **NC 34: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 936 ms
  - Beobachtet: `{"getById_after_delete": ["/alice/files_trashbin/files/gone.jpg.d1791560017"], "trash_entry": ["/remote.php/dav/trashbin/alice/trash/gone.jpg.d1791560017", {"getetag": "1791560017", "fileid": "6582", "size": "2200", "getcontentlength": "2200", "getlastmodified": "Fri, 09 Oct 2026 15:33:37 GMT", "permissions": "GD", "trashbin-filename": "gone.jpg", "trashbin-original-location": "/spike/20261009T143510Z/B05/gone.jpg", "trashbin-deletion-time": "1791560017"}], "trash_content_get": {"status": 200, "sha256": "690b40452ac87f2a415dff17f5ba08e51644b178b07b470fa22345dd07c58271"}, "restore_response": {"method": "MOVE", "status": 201, "curl_error": null, "etag": null, "oc_fileid": null, "ms": 224, "body_head": null}, "after_restore_ref": {"id": 6582, "path": "/alice/files/spike/20261009T143510Z/B05/gone.jpg", "etag": "547aa13ebde5cdfa2516a0e9d1d9f7f6", "mtime": 1791560017, "size": 2200, "class": "…`
- **NC 35: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 684 ms
  - Beobachtet: `{"getById_after_delete": ["/alice/files_trashbin/files/gone.jpg.d1791564288"], "trash_entry": ["/remote.php/dav/trashbin/alice/trash/gone.jpg.d1791564288", {"getetag": "", "fileid": "112", "size": "2200", "getcontentlength": "2200", "getlastmodified": "Fri, 09 Oct 2026 16:44:48 GMT", "permissions": "GD", "trashbin-filename": "gone.jpg", "trashbin-original-location": "/spike/20261009T164442Z/B05/gone.jpg", "trashbin-deletion-time": "1791564288"}], "trash_content_get": {"status": 200, "sha256": "690b40452ac87f2a415dff17f5ba08e51644b178b07b470fa22345dd07c58271"}, "restore_response": {"method": "MOVE", "status": 201, "curl_error": null, "etag": null, "oc_fileid": null, "ms": 162.6, "body_head": null}, "after_restore_ref": {"id": 112, "path": "/alice/files/spike/20261009T164442Z/B05/gone.jpg", "etag": "29461786457a0566ba7de37829e51080", "mtime": 1791564288, "size": 2200, "class": "OCA\\Files…`

### B06 – Inhaltsänderung (gleiche Größe): File-ID, ETag, MTime

- **Spike:** SP-B · **Dokumentbezug:** nextcloud-integration.md §2.2, SP-B, SP-C, DEC-IMM / Identitätsmodell
- **Geprüfte Annahme:** Eine Inhaltsänderung erhält die File-ID und ändert ETag (auch bei gleicher Größe), sodass eine Signatur aus ETag/MTime/Größe jede Änderung erkennt.
- **NC 34: FAIL** · Evidenz: Gegenbeispiel (Annahme widerlegt) · Versuche: 1 · Dauer 2776 ms
  - ✗ Signatur (Größe, MTime, ETag) ändert sich auch bei Änderung in derselben Sekunde mit gleicher Größe — `[[2000, 1791560018, "95e098b2d74e98c88a706ee853a57435"], [2000, 1791560018, "95e098b2d74e98c88a706ee853a57435"]]`
  - Beobachtet: `{"same_second_change": {"mtime_before_after": [1791560018, 1791560018], "signature_before": [2000, 1791560018, "95e098b2d74e98c88a706ee853a57435"], "signature_after": [2000, 1791560018, "95e098b2d74e98c88a706ee853a57435"], "content_changed": true}, "change_after_2s": {"signature_after": [2000, 1791560021, "2a814a2ab5f92e4e956b952f1a789d90"]}}`
  - Hinweis: Der Inhalt hat sich geändert, ETag/MTime/Größe nicht: ein Hash-Cache über diese Signatur würde die Änderung übersehen (Granularität Sekunde).
- **NC 35: FAIL** · Evidenz: Gegenbeispiel (Annahme widerlegt) · Versuche: 1 · Dauer 2527 ms
  - ✗ Signatur (Größe, MTime, ETag) ändert sich auch bei Änderung in derselben Sekunde mit gleicher Größe — `[[2000, 1791564289, "13743a03b9ba8928970ae9ab46345048"], [2000, 1791564289, "13743a03b9ba8928970ae9ab46345048"]]`
  - Beobachtet: `{"same_second_change": {"mtime_before_after": [1791564289, 1791564289], "signature_before": [2000, 1791564289, "13743a03b9ba8928970ae9ab46345048"], "signature_after": [2000, 1791564289, "13743a03b9ba8928970ae9ab46345048"], "content_changed": true}, "change_after_2s": {"signature_after": [2000, 1791564291, "93ed6137781d30fa9164e0e2be0b3aba"]}}`
  - Hinweis: Der Inhalt hat sich geändert, ETag/MTime/Größe nicht: ein Hash-Cache über diese Signatur würde die Änderung übersehen (Granularität Sekunde).

### B07 – Move über Storage-Grenzen (Heim-Storage → Freigabe-Storage eines anderen Benutzers)

- **Spike:** SP-B/SP-C · **Dokumentbezug:** nextcloud-integration.md §2.2, SP-B, SP-C, DEC-IMM / Identitätsmodell
- **Geprüfte Annahme:** Ein Move über Storage-Grenzen erhält die File-ID nicht (Kopie + Löschen); bestätigt die Beschränkung auf den Heim-Storage (REQ-SRC-06). Gilt nur für diese Storage-Kombination.
- **NC 34: FAIL** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 1770 ms
  - ✗ File-ID ändert sich über die Storage-Grenze (Erwartung der Architektur) — `[6591, 6591]`
  - Beobachtet: `{"share_target": "/share20261009T143510Z", "child_result": {"op": "move", "ok": true, "ret": {"path": "/alice/files/share20261009T143510Z/moveme.jpg", "id": 6591, "id_before": 6591}, "exc": null, "pid": 5272, "t0": 1791560022.639369, "t1": 1791560023.013089}, "id_before": 6591, "id_after": 6591, "bob_side": {"moveme.jpg": {"id": 6591, "sha256": "4264116bcbc9094498bb45fde02acc43adbd32c7e121367f1a35fd958d3f2f6a"}}}`
  - Hinweis: Storage-Klassen: Quelle OC\Files\Storage\Home → Ziel Freigabe-Storage (OCA\Files_Sharing\SharedStorage über Jail); nur diese Kombination geprüft.
- **NC 35: FAIL** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 1415 ms
  - ✗ File-ID ändert sich über die Storage-Grenze (Erwartung der Architektur) — `[131, 131]`
  - Beobachtet: `{"share_target": "/share20261009T164442Z", "child_result": {"op": "move", "ok": true, "ret": {"path": "/alice/files/share20261009T164442Z/moveme.jpg", "id": 131, "id_before": 131}, "exc": null, "pid": 460, "t0": 1791564292.669014, "t1": 1791564292.941252}, "id_before": 131, "id_after": 131, "bob_side": {"moveme.jpg": {"id": 131, "sha256": "4264116bcbc9094498bb45fde02acc43adbd32c7e121367f1a35fd958d3f2f6a"}}}`
  - Hinweis: Storage-Klassen: Quelle OC\Files\Storage\Home → Ziel Freigabe-Storage (OCA\Files_Sharing\SharedStorage über Jail); nur diese Kombination geprüft.

### C01 – Node::lock – Verträglichkeitsmatrix shared/exclusive zwischen zwei Prozessen

- **Spike:** SP-E · **Dokumentbezug:** job-processing.md §6.3.4, SP-E, OQ-069 (lock_target)
- **Geprüfte Annahme:** Die öffentliche Node::lock-API liefert gegenseitigen Ausschluss: shared+shared erlaubt, jede Kombination mit exclusive wird sofort mit LockedException abgewiesen (kein Warten).
- **NC 34: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 1668 ms
  - Beobachtet: `{"held_shared_want_shared": "acquired", "held_shared_want_exclusive": {"class": "OCP\\Lock\\LockedException", "message": "\"/alice/files/spike/20261009T143510Z/C01/a.bin\" is locked, existing lock on file: 1 shared locks", "code": 0, "prev": "OCP\\Lock\\LockedException"}, "held_exclusive_want_shared": {"class": "OCP\\Lock\\LockedException", "message": "\"/alice/files/spike/20261009T143510Z/C01/a.bin\" is locked, existing lock on file: exclusive", "code": 0, "prev": "OCP\\Lock\\LockedException"}, "held_exclusive_want_exclusive": {"class": "OCP\\Lock\\LockedException", "message": "\"/alice/files/spike/20261009T143510Z/C01/a.bin\" is locked, existing lock on file: exclusive", "code": 0, "prev": "OCP\\Lock\\LockedException"}, "unlock_without_lock": "kein Fehler"}`
  - Hinweis: Erwerb schlägt sofort fehl, es gibt kein Warten/Timeout (siehe LockedException-Meldungen).
- **NC 35: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 1971 ms
  - Beobachtet: `{"held_shared_want_shared": "acquired", "held_shared_want_exclusive": {"class": "OCP\\Lock\\LockedException", "message": "\"/alice/files/spike/20261009T143510Z/C01/a.bin\" is locked, existing lock on file: 1 shared locks", "code": 0, "prev": "OCP\\Lock\\LockedException"}, "held_exclusive_want_shared": {"class": "OCP\\Lock\\LockedException", "message": "\"/alice/files/spike/20261009T143510Z/C01/a.bin\" is locked, existing lock on file: exclusive", "code": 0, "prev": "OCP\\Lock\\LockedException"}, "held_exclusive_want_exclusive": {"class": "OCP\\Lock\\LockedException", "message": "\"/alice/files/spike/20261009T143510Z/C01/a.bin\" is locked, existing lock on file: exclusive", "code": 0, "prev": "OCP\\Lock\\LockedException"}, "unlock_without_lock": "kein Fehler"}`
  - Hinweis: Erwerb schlägt sofort fehl, es gibt kein Warten/Timeout (siehe LockedException-Meldungen).

### C02 – Konkurrenz durch WebDAV-Client und Node-API gegen eine gesperrte Quelldatei

- **Spike:** SP-E · **Dokumentbezug:** job-processing.md §6.3.4, SP-E, OQ-069 (lock_target)
- **Geprüfte Annahme:** Eine App-Sperre (Node::lock exclusive) auf der Quelle schützt vor Move/Überschreiben/Löschen/Lesen durch WebDAV-Clients und andere Prozesse, solange sie gehalten wird (Sperren wirken für alle Nextcloud-Codepfade, nicht für Zugriffe am Storage vorbei).
- **NC 34: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 2681 ms
  - Beobachtet: `{"while_exclusive": {"dav_get": 423, "dav_put_overwrite": 423, "dav_move": 500, "dav_delete": 423, "node_move": "OCP\\Lock\\LockedException", "node_delete": "OCP\\Lock\\LockedException"}, "dav_get_while_exclusive": 423, "while_shared": {"dav_get": 200, "dav_put_overwrite": 423, "dav_move": 500, "dav_delete": 423, "node_move": "OCP\\Lock\\LockedException", "node_delete": "OCP\\Lock\\LockedException"}}`
- **NC 35: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 2883 ms
  - Beobachtet: `{"while_exclusive": {"dav_get": 423, "dav_put_overwrite": 423, "dav_move": 500, "dav_delete": 423, "node_move": "OCP\\Lock\\LockedException", "node_delete": "OCP\\Lock\\LockedException"}, "dav_get_while_exclusive": 423, "while_shared": {"dav_get": 200, "dav_put_overwrite": 423, "dav_move": 500, "dav_delete": 423, "node_move": "OCP\\Lock\\LockedException", "node_delete": "OCP\\Lock\\LockedException"}}`

### C03 – Sperre auf Zielordner: wirkt sie auf Anlage/Move von Kindern?

- **Spike:** SP-E · **Dokumentbezug:** job-processing.md §6.3.4, SP-E, OQ-069 (lock_target)
- **Geprüfte Annahme:** Eine exklusive Sperre auf den Zielordner verhindert Anlage und Move von Dateien in diesen Ordner (hierarchischer Schutz des Zielordners).
- **NC 34: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 2785 ms
  - Beobachtet: `{"folder_lock_exclusive": {"node_newFile": "OCP\\Lock\\LockedException", "node_move_into": "OCP\\Lock\\LockedException", "dav_put_into": 423}, "folder_lock_shared": {"node_newFile": "OCP\\Lock\\LockedException", "node_move_into": "ok", "dav_put_into": 201}}`
  - Hinweis: shared-Ordner-Sperre: {"node_newFile":"OCP\\Lock\\LockedException","node_move_into":"ok","dav_put_into":201}
- **NC 35: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 2989 ms
  - Beobachtet: `{"folder_lock_exclusive": {"node_newFile": "OCP\\Lock\\LockedException", "node_move_into": "OCP\\Lock\\LockedException", "dav_put_into": 423}, "folder_lock_shared": {"node_newFile": "OCP\\Lock\\LockedException", "node_move_into": "ok", "dav_put_into": 201}}`
  - Hinweis: shared-Ordner-Sperre: {"node_newFile":"OCP\\Lock\\LockedException","node_move_into":"ok","dav_put_into":201}

### C04 – Nicht existierender Zielpfad: Sperrbarkeit über ILockingProvider (lock_target)

- **Spike:** SP-E · **Dokumentbezug:** job-processing.md §6.3.4, SP-E, OQ-069 (lock_target)
- **Geprüfte Annahme:** Ein noch nicht existierender Zielpfad kann gesperrt werden (pfadbasierter Schlüssel) und die Sperre hält fremde Anlage/Moves ab; unklar bleibt, ob der eigene Move trotz eigener Sperre möglich ist.
- **NC 34: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 1413 ms
  - Beobachtet: `{"node_lock_on_nonexisting": {"class": "OCP\\Files\\NotFoundException", "message": "/alice/files/spike/20261009T143510Z/C04/target.bin", "code": 0, "prev": null}, "provider_key_scheme": "files/md5(storageId::internalPath) – interne, nicht dokumentierte Namenskonvention (Nextcloud Common::acquireLock)", "while_target_key_locked_exclusive": {"dav_put_target": 423, "dav_move_to_target": 500, "own_node_move": "OCP\\Lock\\LockedException", "other_process_newFile": "OCP\\Lock\\LockedException"}, "own_move_blocked_by_own_lock": true}`
  - Hinweis: Der Schlüssel wird mit der internen Konvention von Nextcloud gebildet (nicht Teil der öffentlichen API-Verträge). Ob der eigene Move trotz eigener Sperre läuft, steht unter own_move_blocked_by_own_lock.
- **NC 35: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 1546 ms
  - Beobachtet: `{"node_lock_on_nonexisting": {"class": "OCP\\Files\\NotFoundException", "message": "/alice/files/spike/20261009T143510Z/C04/target.bin", "code": 0, "prev": null}, "provider_key_scheme": "files/md5(storageId::internalPath) – interne, nicht dokumentierte Namenskonvention (Nextcloud Common::acquireLock)", "while_target_key_locked_exclusive": {"dav_put_target": 423, "dav_move_to_target": 500, "own_node_move": "OCP\\Lock\\LockedException", "other_process_newFile": "OCP\\Lock\\LockedException"}, "own_move_blocked_by_own_lock": true}`
  - Hinweis: Der Schlüssel wird mit der internen Konvention von Nextcloud gebildet (nicht Teil der öffentlichen API-Verträge). Ob der eigene Move trotz eigener Sperre läuft, steht unter own_move_blocked_by_own_lock.

### C05 – Wiedereintritt: eigene Sperre auf der Quelle und anschließender Node::move

- **Spike:** SP-E · **Dokumentbezug:** job-processing.md §6.3.4, SP-E, OQ-069 (lock_target)
- **Geprüfte Annahme:** Node::move funktioniert, wenn der Aufrufer die Quelle bereits selbst (shared) gesperrt hat; mit eigener exklusiver Sperre scheitert er an der eigenen Sperre (kein Wiedereintritt).
- **NC 34: FAIL** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 285 ms
  - ✗ eigene shared-Sperre blockiert den eigenen Move nicht — `"OCP\\Lock\\LockedException: \"/alice/files/spike/20261009T143510Z/C05/a.bin\" is locked, existing lock on file: 2 shared locks"`
  - Beobachtet: `{"reentrancy": {"shared": "OCP\\Lock\\LockedException: \"/alice/files/spike/20261009T143510Z/C05/a.bin\" is locked, existing lock on file: 2 shared locks", "exclusive": "OCP\\Lock\\LockedException: \"/alice/files/spike/20261009T143510Z/C05/a.bin\" is locked, existing lock on file: exclusive"}}`
- **NC 35: FAIL** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 282 ms
  - ✗ eigene shared-Sperre blockiert den eigenen Move nicht — `"OCP\\Lock\\LockedException: \"/alice/files/spike/20261009T143510Z/C05/a.bin\" is locked, existing lock on file: 2 shared locks"`
  - Beobachtet: `{"reentrancy": {"shared": "OCP\\Lock\\LockedException: \"/alice/files/spike/20261009T143510Z/C05/a.bin\" is locked, existing lock on file: 2 shared locks", "exclusive": "OCP\\Lock\\LockedException: \"/alice/files/spike/20261009T143510Z/C05/a.bin\" is locked, existing lock on file: exclusive"}}`

### C06 – Zwei Worker erwerben gleichzeitig dieselbe exklusive Sperre (kooperativer Mutex)

- **Spike:** SP-E · **Dokumentbezug:** job-processing.md §6.3.4, SP-E, OQ-069 (lock_target)
- **Geprüfte Annahme:** Von zwei gleichzeitig startenden Prozessen erhält höchstens einer die exklusive Sperre; beide gleichzeitig ist nie möglich.
- **NC 34: PASS** · Evidenz: endliche Stichprobe (keine Garantie) · Versuche: 37 · Dauer 39087 ms
  - Tally: `{"a_lost/b_won": 22, "a_won/b_lost": 15}`
  - Hinweis: Die Messung der Überlappung beruht auf den Zeitstempeln innerhalb von Op.t0..t1 (Erwerb bis Freigabe).
- **NC 35: PASS** · Evidenz: endliche Stichprobe (keine Garantie) · Versuche: 37 · Dauer 39199 ms
  - Tally: `{"a_won/b_lost": 20, "a_lost/b_won": 17}`
  - Hinweis: Die Messung der Überlappung beruht auf den Zeitstempeln innerhalb von Op.t0..t1 (Erwerb bis Freigabe).

### C07 – Prozessabbruch (kill -9) mit gehaltener Sperre: bleibt die Sperre bestehen, wie lange?

- **Spike:** SP-E · **Dokumentbezug:** job-processing.md §6.3.4, SP-E, OQ-069 (lock_target)
- **Geprüfte Annahme:** Eine Sperre eines abgebrochenen Prozesses wird nicht sofort freigegeben; sie ist bis zum Ablauf der Sperrlebensdauer wirksam (temporärer Fehler mit Backoff, keine automatische Freigabe durch die App möglich).
- **NC 34: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 2920 ms
  - Beobachtet: `{"lock_while_holder_alive": "OCP\\Lock\\LockedException", "lock_2s_after_kill": "OCP\\Lock\\LockedException", "stale_lock_keys_ttl_seconds": [{"key": "files/91e11c6988e07778b719019604e565c9", "ttl": 3598}], "configured_filelocking_ttl": "nicht gesetzt (Standard: max(3600, max_execution_time))", "after_test_cleanup": "Schlüssel gelöscht, Sperre wieder erwerbbar"}`
- **NC 35: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 2967 ms
  - Beobachtet: `{"lock_while_holder_alive": "OCP\\Lock\\LockedException", "lock_2s_after_kill": "OCP\\Lock\\LockedException", "stale_lock_keys_ttl_seconds": [{"key": "files/91e11c6988e07778b719019604e565c9", "ttl": 3598}], "configured_filelocking_ttl": "nicht gesetzt (Standard: max(3600, max_execution_time))", "after_test_cleanup": "Schlüssel gelöscht, Sperre wieder erwerbbar"}`

### D01 – Node::delete() verwendet den Nextcloud-Papierkorb; Identität, Erreichbarkeit und Inhalt des gelöschten Knotens

- **Spike:** SP-J · **Dokumentbezug:** job-processing.md §6.4, DEC-DUP-010, SP-J, trash_verified
- **Geprüfte Annahme:** Node::delete() verschiebt in den Papierkorb (nicht endgültig). Der Eintrag ist auffindbar (WebDAV und IRootFolder per File-ID), trägt dieselbe File-ID und denselben Inhalt.
- **NC 34: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 613 ms
  - Beobachtet: `{"getById_paths_after_delete": ["/alice/files_trashbin/files/photo.jpg.d1791560074"], "hash_via_root_folder": "2fecdebe951a6a234741f265dfd98c1f6bfe5684341cd8c29114a045d5e3475b", "trash_entries": [["/remote.php/dav/trashbin/alice/trash/photo.jpg.d1791560074", {"getetag": "1791560074", "fileid": "6647", "size": "4096", "getcontentlength": "4096", "getlastmodified": "Fri, 09 Oct 2026 15:34:34 GMT", "permissions": "GD", "trashbin-filename": "photo.jpg", "trashbin-original-location": "/spike/20261009T143510Z/D01/photo.jpg", "trashbin-deletion-time": "1791560074"}]]}`
- **NC 35: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 451 ms
  - Beobachtet: `{"getById_paths_after_delete": ["/alice/files_trashbin/files/photo.jpg.d1791564293"], "hash_via_root_folder": "2fecdebe951a6a234741f265dfd98c1f6bfe5684341cd8c29114a045d5e3475b", "trash_entries": [["/remote.php/dav/trashbin/alice/trash/photo.jpg.d1791564293", {"getetag": "", "fileid": "133", "size": "4096", "getcontentlength": "4096", "getlastmodified": "Fri, 09 Oct 2026 16:44:53 GMT", "permissions": "GD", "trashbin-filename": "photo.jpg", "trashbin-original-location": "/spike/20261009T164442Z/D01/photo.jpg", "trashbin-deletion-time": "1791564293"}]]}`

### D02 – Nachweis einer Papierkorboperation (Pre-Delete-Hash beider Exemplare, Post-Beobachtung)

- **Spike:** SP-J · **Dokumentbezug:** job-processing.md §6.4, DEC-DUP-010, SP-J, trash_verified
- **Geprüfte Annahme:** Nach dem Löschen lässt sich allein über öffentliche APIs belegen: Duplikat nicht mehr am Ort, Papierkorbeintrag mit derselben File-ID und gleichem Hash vorhanden, kanonische Datei unverändert.
- **NC 34: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 691 ms
  - Beobachtet: `{"post_checks": {"duplicate_gone": true, "canonical_unchanged": true, "trash_node_by_id_hash_equal": true, "webdav_trash_entries": 1}}`
  - Hinweis: Der Nachweis beruht auf der Identität über die File-ID und dem Hash des Papierkorb-Knotens; er belegt, dass der Inhalt erhalten ist, nicht dass er unbegrenzt erhalten bleibt (Retention).
- **NC 35: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 532 ms
  - Beobachtet: `{"post_checks": {"duplicate_gone": true, "canonical_unchanged": true, "trash_node_by_id_hash_equal": true, "webdav_trash_entries": 1}}`
  - Hinweis: Der Nachweis beruht auf der Identität über die File-ID und dem Hash des Papierkorb-Knotens; er belegt, dass der Inhalt erhalten ist, nicht dass er unbegrenzt erhalten bleibt (Retention).

### D03 – Zwei Löschungen gleichnamiger Dateien am selben Pfad kurz nacheinander (gleiche Sekunde) und mit 2 s Abstand

- **Spike:** SP-J · **Dokumentbezug:** job-processing.md §6.4, DEC-DUP-010, SP-J, trash_verified
- **Geprüfte Annahme:** Zwei Papierkorbeinträge zum gleichen Originalpfad bleiben unterscheidbar und beide Inhalte erhalten – auch wenn sie in derselben Sekunde entstehen (Eintragsname trägt nur einen Sekundenzeitstempel).
- **NC 34: FAIL** · Evidenz: Gegenbeispiel (Annahme widerlegt) · Versuche: 1 · Dauer 3964 ms
  - ✗ same_second: zwei getrennte Papierkorbeinträge, beide Inhalte über ihre File-ID erhalten — `{"entries": 1, "hashes": {"11481": "27d11b6ed393b4a45f5ea1307bdfafe6f8e16b97b7fdf3548533a6abaa4b92f6"}}`
  - Beobachtet: `{"same_second": {"ids": {"A": 11480, "B": 11481}, "delete_A_second": [1791563958, 1791563958], "delete_B_second": [1791563958, 1791563958], "trash_entries": [["same.jpg.d1791563958", "11481", "1791563958"]], "content_A_reachable_by_id": [], "content_B_reachable_by_id": ["/alice/files_trashbin/files/same.jpg.d1791563958"], "hashes_via_webdav": {"11481": "27d11b6ed393b4a45f5ea1307bdfafe6f8e16b97b7fdf3548533a6abaa4b92f6"}}, "two_seconds_apart": {"ids": {"A": 11483, "B": 11484}, "delete_A_second": [1791563959, 1791563959], "delete_B_second": [1791563961, 1791563961], "trash_entries": [["same.jpg.d1791563959", "11483", "1791563959"], ["same.jpg.d1791563961", "11484", "1791563961"]], "content_A_reachable_by_id": ["/alice/files_trashbin/files/same.jpg.d1791563959"], "content_B_reachable_by_id": ["/alice/files_trashbin/files/same.jpg.d1791563961"], "hashes_via_webdav": {"11483": "57b00c5f565f93…`
  - Hinweis: same_second: ein Papierkorbeintrag fehlt – der später gelöschte Inhalt hat den früheren ersetzt (Eintragsname = Name + Sekundenzeitstempel).
- **NC 35: FAIL** · Evidenz: Gegenbeispiel (Annahme widerlegt) · Versuche: 1 · Dauer 3482 ms
  - ✗ same_second: zwei getrennte Papierkorbeinträge, beide Inhalte über ihre File-ID erhalten — `{"entries": 1, "hashes": {"139": "27d11b6ed393b4a45f5ea1307bdfafe6f8e16b97b7fdf3548533a6abaa4b92f6"}}`
  - Beobachtet: `{"same_second": {"ids": {"A": 138, "B": 139}, "delete_A_second": [1791564294, 1791564294], "delete_B_second": [1791564294, 1791564294], "trash_entries": [["same.jpg.d1791564294", "139", "1791564294"]], "content_A_reachable_by_id": [], "content_B_reachable_by_id": ["/alice/files_trashbin/files/same.jpg.d1791564294"], "hashes_via_webdav": {"139": "27d11b6ed393b4a45f5ea1307bdfafe6f8e16b97b7fdf3548533a6abaa4b92f6"}}, "two_seconds_apart": {"ids": {"A": 141, "B": 142}, "delete_A_second": [1791564294, 1791564295], "delete_B_second": [1791564297, 1791564297], "trash_entries": [["same.jpg.d1791564294", "141", "1791564294"], ["same.jpg.d1791564297", "142", "1791564297"]], "content_A_reachable_by_id": ["/alice/files_trashbin/files/same.jpg.d1791564294"], "content_B_reachable_by_id": ["/alice/files_trashbin/files/same.jpg.d1791564297"], "hashes_via_webdav": {"141": "57b00c5f565f9392284ce519875c6d6c…`
  - Hinweis: same_second: ein Papierkorbeintrag fehlt – der später gelöschte Inhalt hat den früheren ersetzt (Eintragsname = Name + Sekundenzeitstempel).

### D04 – Restore bei inzwischen vorhandener Zieldatei

- **Spike:** SP-J · **Dokumentbezug:** job-processing.md §6.4, DEC-DUP-010, SP-J, trash_verified
- **Geprüfte Annahme:** Die Wiederherstellung überschreibt eine inzwischen am Originalpfad vorhandene Datei nicht.
- **NC 34: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 884 ms
  - Beobachtet: `{"restore_response": {"method": "MOVE", "status": 201, "curl_error": null, "etag": null, "oc_fileid": null, "ms": 192.3, "body_head": null}, "names_after": ["x (restored).jpg", "x.jpg"], "restored_under": ["x (restored).jpg"], "restored_id_equals_original": true}`
- **NC 35: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 686 ms
  - Beobachtet: `{"restore_response": {"method": "MOVE", "status": 201, "curl_error": null, "etag": null, "oc_fileid": null, "ms": 167.1, "body_head": null}, "names_after": ["x (restored).jpg", "x.jpg"], "restored_under": ["x (restored).jpg"], "restored_id_equals_original": true}`

### D05 – Löschen per WebDAV DELETE und Löschen eines Ordners

- **Spike:** SP-J · **Dokumentbezug:** job-processing.md §6.4, DEC-DUP-010, SP-J, trash_verified
- **Geprüfte Annahme:** WebDAV DELETE und Ordner-Löschung führen ebenfalls in den Papierkorb; File-IDs der Kinder bleiben erhalten.
- **NC 34: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 1263 ms
  - Beobachtet: `{"dav_delete": {"method": "DELETE", "status": 204, "curl_error": null, "etag": null, "oc_fileid": null, "ms": 233.8, "body_head": null}, "children_by_id_after_folder_delete": {"6660": ["/alice/files_trashbin/files/folder.d1791560078/k1.jpg"], "6661": ["/alice/files_trashbin/files/folder.d1791560078/k2.jpg"]}, "folder_trash_entries": 1}`
- **NC 35: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 820 ms
  - Beobachtet: `{"dav_delete": {"method": "DELETE", "status": 204, "curl_error": null, "etag": null, "oc_fileid": null, "ms": 162.1, "body_head": null}, "children_by_id_after_folder_delete": {"149": ["/alice/files_trashbin/files/folder.d1791564298/k1.jpg"], "150": ["/alice/files_trashbin/files/folder.d1791564298/k2.jpg"]}, "folder_trash_entries": 1}`

### D06 – Fähigkeitserkennung trash_verified (nicht destruktiv)

- **Spike:** SP-J · **Dokumentbezug:** job-processing.md §6.4, DEC-DUP-010, SP-J, trash_verified
- **Geprüfte Annahme:** Der Papierkorb-Betrieb ist ohne Löschversuch erkennbar: App files_trashbin aktiv und Papierkorb-Storage-Wrapper im Heim-Storage; die Prüfung allein beweist noch nicht, dass jede Löschung im Papierkorb landet.
- **NC 34: PASS** · Evidenz: Quelltextanalyse (nicht experimentell) · Versuche: 1 · Dauer 0 ms
  - Beobachtet: `{"files_trashbin_enabled": true, "storage_chain_contains_trashbin_wrapper": true, "trashbin_retention_obligation": "auto (Standard)"}`
  - Hinweis: Der Fall „Papierkorb deaktiviert → Node::delete löscht endgültig“ wurde bewusst NICHT experimentell geprüft (Auftrag 5: keine dauerhaft irreversible Löschung im Harness); er folgt nur aus dem Quelltext (Trash-Wrapper entfällt, Local::unlink löscht direkt).
- **NC 35: PASS** · Evidenz: Quelltextanalyse (nicht experimentell) · Versuche: 1 · Dauer 0 ms
  - Beobachtet: `{"files_trashbin_enabled": true, "storage_chain_contains_trashbin_wrapper": true, "trashbin_retention_obligation": "auto (Standard)"}`
  - Hinweis: Der Fall „Papierkorb deaktiviert → Node::delete löscht endgültig“ wurde bewusst NICHT experimentell geprüft (Auftrag 5: keine dauerhaft irreversible Löschung im Harness); er folgt nur aus dem Quelltext (Trash-Wrapper entfällt, Local::unlink löscht direkt).

### E01 – App-interne Dateioperationen über die Node-API (CLI-Prozess): Create, Write, Rename, Move, Copy, Delete

- **Spike:** Events · **Dokumentbezug:** job-processing.md §4 (Events), OQ-056, SP-E/Events-Spike, nextcloud-integration.md §2.3
- **Geprüfte Annahme:** Jede App-Operation löst genau ein Hauptereignis (NodeCreated / NodeRenamed / NodeCopied / NodeDeleted) aus; Mehrfachzustellung tritt nicht auf. (Naive Erwartung; Abweichungen zeigen den Idempotenzbedarf.)
- **NC 34: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 11735 ms
  - Beobachtet: `{"sequence:node_newFile": ["+10.4ms BeforeNodeCreatedEvent [cli pid=2805 -] node=/alice/files/spike/20261009T143510Z/E01/a.bin#ERR:OCP\\Files\\NotFoundException size=ERR:OCP\\Files\\NotFoundException", "+14.3ms BeforeNodeWrittenEvent [cli pid=2805 -] node=/alice/files/spike/20261009T143510Z/E01/a.bin#ERR:OCP\\Files\\NotFoundException size=ERR:OCP\\Files\\NotFoundException", "+45.7ms VersionCreatedEvent [cli pid=2805 -] node=/alice/files/spike/20261009T143510Z/E01/a.bin#6664 size=5000", "+45.8ms NodeCreatedEvent [cli pid=2805 -] node=/alice/files/spike/20261009T143510Z/E01/a.bin#6664 size=5000", "+94.9ms NodeWrittenEvent [cli pid=2805 -] node=/alice/files/spike/20261009T143510Z/E01/a.bin#6664 size=5000"], "counts:node_newFile": {"BeforeNodeCreatedEvent": 1, "BeforeNodeWrittenEvent": 1, "CacheEntryInsertedEvent": 2, "CacheEntryUpdatedEvent": 4, "VersionCreatedEvent": 1, "NodeCreatedEvent"…`
- **NC 35: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 7437 ms
  - Beobachtet: `{"sequence:node_newFile": ["+8.8ms BeforeNodeCreatedEvent [cli pid=442 -] node=/alice/files/spike/20261009T164442Z/E01/a.bin#ERR:OCP\\Files\\NotFoundException size=ERR:OCP\\Files\\NotFoundException", "+11.5ms BeforeNodeWrittenEvent [cli pid=442 -] node=/alice/files/spike/20261009T164442Z/E01/a.bin#ERR:OCP\\Files\\NotFoundException size=ERR:OCP\\Files\\NotFoundException", "+47.8ms VersionCreatedEvent [cli pid=442 -] node=/alice/files/spike/20261009T164442Z/E01/a.bin#153 size=5000", "+47.9ms NodeCreatedEvent [cli pid=442 -] node=/alice/files/spike/20261009T164442Z/E01/a.bin#153 size=5000", "+76.3ms NodeWrittenEvent [cli pid=442 -] node=/alice/files/spike/20261009T164442Z/E01/a.bin#153 size=5000"], "counts:node_newFile": {"BeforeNodeCreatedEvent": 1, "BeforeNodeWrittenEvent": 1, "CacheEntryInsertedEvent": 2, "CacheEntryUpdatedEvent": 2, "VersionCreatedEvent": 1, "NodeCreatedEvent": 1, "Nod…`

### E02 – WebDAV-Operationen eines Clients (Apache-Prozess): PUT neu/überschreiben, MOVE (Rename/Move), COPY, DELETE

- **Spike:** Events · **Dokumentbezug:** job-processing.md §4 (Events), OQ-056, SP-E/Events-Spike, nextcloud-integration.md §2.3
- **Geprüfte Annahme:** WebDAV-Aktionen lösen dieselben Ereignisklassen aus wie die Node-API, und zwar im Webserver-Prozess (nicht im Cron/CLI-Kontext des Organizers).
- **NC 34: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 12593 ms
  - Beobachtet: `{"sequence:dav_put_new": ["+97.5ms BeforeNodeCreatedEvent [apache2handler pid=2794 PUT] node=/alice/files/spike/20261009T143510Z/E02/a.bin#ERR:OCP\\Files\\NotFoundException size=ERR:OCP\\Files\\NotFoundException", "+99.2ms BeforeNodeWrittenEvent [apache2handler pid=2794 PUT] node=/alice/files/spike/20261009T143510Z/E02/a.bin#ERR:OCP\\Files\\NotFoundException size=ERR:OCP\\Files\\NotFoundException", "+161.6ms VersionCreatedEvent [apache2handler pid=2794 PUT] node=/alice/files/spike/20261009T143510Z/E02/a.bin#6670 size=200000", "+161.8ms NodeCreatedEvent [apache2handler pid=2794 PUT] node=/alice/files/spike/20261009T143510Z/E02/a.bin#6670 size=200000", "+217.9ms NodeWrittenEvent [apache2handler pid=2794 PUT] node=/alice/files/spike/20261009T143510Z/E02/a.bin#6670 size=200000"], "counts:dav_put_new": {"BeforeNodeCreatedEvent": 1, "BeforeNodeWrittenEvent": 1, "CacheEntryInsertedEvent": 2, "…`
- **NC 35: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 7818 ms
  - Beobachtet: `{"sequence:dav_put_new": ["+69ms BeforeNodeCreatedEvent [apache2handler pid=429 PUT] node=/alice/files/spike/20261009T164442Z/E02/a.bin#ERR:OCP\\Files\\NotFoundException size=ERR:OCP\\Files\\NotFoundException", "+70.7ms BeforeNodeWrittenEvent [apache2handler pid=429 PUT] node=/alice/files/spike/20261009T164442Z/E02/a.bin#ERR:OCP\\Files\\NotFoundException size=ERR:OCP\\Files\\NotFoundException", "+108.6ms VersionCreatedEvent [apache2handler pid=429 PUT] node=/alice/files/spike/20261009T164442Z/E02/a.bin#159 size=200000", "+108.8ms NodeCreatedEvent [apache2handler pid=429 PUT] node=/alice/files/spike/20261009T164442Z/E02/a.bin#159 size=200000", "+144.2ms NodeWrittenEvent [apache2handler pid=429 PUT] node=/alice/files/spike/20261009T164442Z/E02/a.bin#159 size=200000"], "counts:dav_put_new": {"BeforeNodeCreatedEvent": 1, "BeforeNodeWrittenEvent": 1, "CacheEntryInsertedEvent": 2, "CacheEntry…`

### E03 – Chunked Upload v2 (Nextcloud-/Mobil-/Desktop-Clients): wann und wie oft Ereignisse für die Zieldatei

- **Spike:** Events · **Dokumentbezug:** job-processing.md §4 (Events), OQ-056, SP-E/Events-Spike, nextcloud-integration.md §2.3
- **Geprüfte Annahme:** Beim Chunked Upload entsteht die Zieldatei erst beim abschließenden MOVE; Zwischenstände (Chunks) lösen keine Ereignisse für den Zielpfad aus, und die Zieldatei ist bei den Ereignissen vollständig.
- **NC 34: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 3354 ms
  - Beobachtet: `{"sequence:chunked_upload_v2": ["+971.6ms BeforeNodeCreatedEvent [apache2handler pid=8310 MOVE] node=/alice/files/spike/20261009T163948Z/E03/big.bin#ERR:OCP\\Files\\NotFoundException size=ERR:OCP\\Files\\NotFoundException", "+975.2ms BeforeNodeWrittenEvent [apache2handler pid=8310 MOVE] node=/alice/files/spike/20261009T163948Z/E03/big.bin#ERR:OCP\\Files\\NotFoundException size=ERR:OCP\\Files\\NotFoundException", "+1062.9ms VersionCreatedEvent [apache2handler pid=8310 MOVE] node=/alice/files/spike/20261009T163948Z/E03/big.bin#11490 size=300000", "+1063.1ms NodeCreatedEvent [apache2handler pid=8310 MOVE] node=/alice/files/spike/20261009T163948Z/E03/big.bin#11490 size=300000", "+1209.2ms NodeWrittenEvent [apache2handler pid=8310 MOVE] node=/alice/files/spike/20261009T163948Z/E03/big.bin#11490 size=300000"], "counts:chunked_upload_v2": {"CacheEntryInsertedEvent": 8, "CacheEntryUpdatedEvent"…`
- **NC 35: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 1357 ms
  - Beobachtet: `{"sequence:chunked_upload_v2": ["+295.9ms BeforeNodeCreatedEvent [apache2handler pid=428 MOVE] node=/alice/files/spike/20261009T164442Z/E03/big.bin#ERR:OCP\\Files\\NotFoundException size=ERR:OCP\\Files\\NotFoundException", "+297.1ms BeforeNodeWrittenEvent [apache2handler pid=428 MOVE] node=/alice/files/spike/20261009T164442Z/E03/big.bin#ERR:OCP\\Files\\NotFoundException size=ERR:OCP\\Files\\NotFoundException", "+335.6ms VersionCreatedEvent [apache2handler pid=428 MOVE] node=/alice/files/spike/20261009T164442Z/E03/big.bin#171 size=300000", "+335.8ms NodeCreatedEvent [apache2handler pid=428 MOVE] node=/alice/files/spike/20261009T164442Z/E03/big.bin#171 size=300000", "+365.7ms NodeWrittenEvent [apache2handler pid=428 MOVE] node=/alice/files/spike/20261009T164442Z/E03/big.bin#171 size=300000"], "counts:chunked_upload_v2": {"CacheEntryInsertedEvent": 10, "CacheEntryUpdatedEvent": 19, "Before…`

### E04 – Papierkorb: Delete und Restore (Node-API und WebDAV-Restore)

- **Spike:** Events · **Dokumentbezug:** job-processing.md §4 (Events), OQ-056, SP-E/Events-Spike, nextcloud-integration.md §2.3
- **Geprüfte Annahme:** Löschen löst NodeDeleted + MoveToTrash aus; die Wiederherstellung ist an eigenen Ereignissen (NodeRestored / NodeCreated) erkennbar, nicht als Rename.
- **NC 34: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 3922 ms
  - Beobachtet: `{"sequence:trash_by_node_delete": ["+23.9ms BeforeNodeDeletedEvent [cli pid=2805 -] node=/alice/files/spike/20261009T143510Z/E04/x.bin#6683 size=3000", "+62.1ms MoveToTrashEvent [cli pid=2805 -] node=/alice/files/spike/20261009T143510Z/E04/x.bin#6683 size=3000", "+218.6ms NodeDeletedEvent [cli pid=2805 -] node=/alice/files/spike/20261009T143510Z/E04/x.bin#6683 size=3000"], "counts:trash_by_node_delete": {"BeforeNodeDeletedEvent": 1, "MoveToTrashEvent": 1, "CacheEntryUpdatedEvent": 22, "NodeDeletedEvent": 1}, "sequence:restore_via_webdav": ["+90.5ms BeforeNodeRestoredEvent [apache2handler pid=2794 MOVE] source=/alice/files_trashbin/files/x.bin.d1791560105#6683 size=3000 target=/alice/files/spike/20261009T143510Z/E04/x.bin#ERR:OCP\\Files\\NotFoundException size=ERR:OCP\\Files\\NotFoundException", "+178.8ms BeforeNodeTouchedEvent [apache2handler pid=2794 MOVE] node=/alice/files/spike/20261…`
- **NC 35: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 2236 ms
  - Beobachtet: `{"sequence:trash_by_node_delete": ["+15.5ms BeforeNodeDeletedEvent [cli pid=442 -] node=/alice/files/spike/20261009T164442Z/E04/x.bin#173 size=3000", "+35.9ms MoveToTrashEvent [cli pid=442 -] node=/alice/files/spike/20261009T164442Z/E04/x.bin#173 size=3000", "+120.6ms NodeDeletedEvent [cli pid=442 -] node=/alice/files/spike/20261009T164442Z/E04/x.bin#173 size=3000"], "counts:trash_by_node_delete": {"BeforeNodeDeletedEvent": 1, "MoveToTrashEvent": 1, "CacheEntryUpdatedEvent": 11, "NodeDeletedEvent": 1}, "sequence:restore_via_webdav": ["+59.8ms BeforeNodeRestoredEvent [apache2handler pid=430 MOVE] source=/alice/files_trashbin/files/x.bin.d1791564316#173 size=3000 target=/alice/files/spike/20261009T164442Z/E04/x.bin#ERR:OCP\\Files\\NotFoundException size=ERR:OCP\\Files\\NotFoundException", "+101.5ms BeforeNodeTouchedEvent [apache2handler pid=430 MOVE] node=/alice/files/spike/20261009T16444…`

### E05 – Identitätsmerkmale der Ereignisse (Pfad, File-ID, Quelle/Ziel) über alle Szenarien

- **Spike:** Events · **Dokumentbezug:** job-processing.md §4 (Events), OQ-056, SP-E/Events-Spike, nextcloud-integration.md §2.3
- **Geprüfte Annahme:** Alle After-Ereignisse tragen File-ID und Pfad des betroffenen Knotens; NodeRenamedEvent trägt Quelle und Ziel mit derselben File-ID.
- **NC 34: FAIL** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 2066 ms
  - ✗ NodeRenamedEvent: auch das Quell-Node liefert die File-ID (Erwartung der bisherigen Dokumentation) — `["ERR:OCP\\Files\\NotFoundException", "cli"]`
  - ✗ NodeRenamedEvent: auch das Quell-Node liefert die File-ID (Erwartung der bisherigen Dokumentation) — `["ERR:OCP\\Files\\NotFoundException", "apache2handler"]`
  - Beobachtet: `{"renamed_events": ["+102.9ms NodeRenamedEvent [cli pid=2805 -] source=/alice/files/spike/20261009T143510Z/E05/a.bin#ERR:OCP\\Files\\NotFoundException size=ERR:OCP\\Files\\NotFoundException target=/alice/files/spike/20261009T143510Z/E05/b.bin#6685 size=2000", "+297.3ms NodeRenamedEvent [apache2handler pid=4010 MOVE] source=/alice/files/spike/20261009T143510Z/E05/b.bin#ERR:OCP\\Files\\NotFoundException size=ERR:OCP\\Files\\NotFoundException target=/alice/files/spike/20261009T143510Z/E05/c.bin#6685 size=2000"], "source_node_id_in_cli_event": "ERR:OCP\\Files\\NotFoundException", "source_node_id_in_apache2handler_event": "ERR:OCP\\Files\\NotFoundException", "after_events_with_node_ids": []}`
- **NC 35: FAIL** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 1278 ms
  - ✗ NodeRenamedEvent: auch das Quell-Node liefert die File-ID (Erwartung der bisherigen Dokumentation) — `["ERR:OCP\\Files\\NotFoundException", "cli"]`
  - ✗ NodeRenamedEvent: auch das Quell-Node liefert die File-ID (Erwartung der bisherigen Dokumentation) — `["ERR:OCP\\Files\\NotFoundException", "apache2handler"]`
  - Beobachtet: `{"renamed_events": ["+70.9ms NodeRenamedEvent [cli pid=442 -] source=/alice/files/spike/20261009T164442Z/E05/a.bin#ERR:OCP\\Files\\NotFoundException size=ERR:OCP\\Files\\NotFoundException target=/alice/files/spike/20261009T164442Z/E05/b.bin#175 size=2000", "+201.5ms NodeRenamedEvent [apache2handler pid=431 MOVE] source=/alice/files/spike/20261009T164442Z/E05/b.bin#ERR:OCP\\Files\\NotFoundException size=ERR:OCP\\Files\\NotFoundException target=/alice/files/spike/20261009T164442Z/E05/c.bin#175 size=2000"], "source_node_id_in_cli_event": "ERR:OCP\\Files\\NotFoundException", "source_node_id_in_apache2handler_event": "ERR:OCP\\Files\\NotFoundException", "after_events_with_node_ids": []}`

### E06 – Ordner-Move: Ereignisse für den Ordner und für enthaltene Dateien (group_follow-Erkennung)

- **Spike:** Events · **Dokumentbezug:** job-processing.md §4 (Events), OQ-056, SP-E/Events-Spike, nextcloud-integration.md §2.3
- **Geprüfte Annahme:** Beim Verschieben eines Ordners mit Dateien entsteht genau ein NodeRenamedEvent für den Ordner; für die enthaltenen Dateien gibt es keine eigenen NodeRenamedEvents (Pfadänderung der Kinder muss aus dem Ordner-Ereignis abgeleitet werden).
- **NC 34: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 2384 ms
  - Beobachtet: `{"sequence:dav_move_folder_with_children": ["+98.8ms BeforeNodeRenamedEvent [apache2handler pid=2793 MOVE] source=/alice/files/spike/20261009T143510Z/E06/album#6688 size=1300 target=/alice/files/spike/20261009T143510Z/E06/dst/album#ERR:OCP\\Files\\NotFoundException size=ERR:OCP\\Files\\NotFoundException", "+210.4ms NodeRenamedEvent [apache2handler pid=2793 MOVE] source=/alice/files/spike/20261009T143510Z/E06/album#ERR:OCP\\Files\\NotFoundException size=ERR:OCP\\Files\\NotFoundException target=/alice/files/spike/20261009T143510Z/E06/dst/album#6688 size=1300"], "counts:dav_move_folder_with_children": {"BeforeNodeRenamedEvent": 1, "CacheEntryUpdatedEvent": 26, "NodeRenamedEvent": 1}, "renamed_event_targets": ["/alice/files/spike/20261009T143510Z/E06/dst/album#6688"], "cache_updated_events_for_children": []}`
  - Hinweis: Ob CacheEntryUpdatedEvent für die Kinder verlässlich ausgeliefert wird, ist nur hier beobachtet; als Erkennungsquelle für Kinder-Pfadänderungen ist der Ordner-Rename plus ein Teilbaum-Abgleich (Listing nach file_id) vorzusehen.
- **NC 35: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 1426 ms
  - Beobachtet: `{"sequence:dav_move_folder_with_children": ["+67.3ms BeforeNodeRenamedEvent [apache2handler pid=452 MOVE] source=/alice/files/spike/20261009T164442Z/E06/album#178 size=1300 target=/alice/files/spike/20261009T164442Z/E06/dst/album#ERR:OCP\\Files\\NotFoundException size=ERR:OCP\\Files\\NotFoundException", "+132.1ms NodeRenamedEvent [apache2handler pid=452 MOVE] source=/alice/files/spike/20261009T164442Z/E06/album#ERR:OCP\\Files\\NotFoundException size=ERR:OCP\\Files\\NotFoundException target=/alice/files/spike/20261009T164442Z/E06/dst/album#178 size=1300"], "counts:dav_move_folder_with_children": {"BeforeNodeRenamedEvent": 1, "CacheEntryUpdatedEvent": 13, "NodeRenamedEvent": 1}, "renamed_event_targets": ["/alice/files/spike/20261009T164442Z/E06/dst/album#178"], "cache_updated_events_for_children": []}`
  - Hinweis: Ob CacheEntryUpdatedEvent für die Kinder verlässlich ausgeliefert wird, ist nur hier beobachtet; als Erkennungsquelle für Kinder-Pfadänderungen ist der Ordner-Rename plus ein Teilbaum-Abgleich (Listing nach file_id) vorzusehen.

### F01 – SHA-256-Kontext: Serialisieren und Fortsetzen an vielen Offsets (reines PHP)

- **Spike:** SP-H · **Dokumentbezug:** job-processing.md §3.6 (H1–H3), SP-H, hash_resume, DEC-IMM-008
- **Geprüfte Annahme:** Ein per serialize() gesicherter SHA-256-HashContext lässt sich fortsetzen und liefert exakt den Digest des Gesamtinhalts.
- **NC 34: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 130 ms
  - Beobachtet: `{"php_version": "8.5.11", "offsets_tested": 16, "serialized_state_bytes": 313, "state_sample_base64": "TzoxMToiSGFzaENvbnRleHQiOjU6e2k6MDtzOjY6InNoYTI1NiI7aToxO2k6MDtpOjI7YToxMTp7aTowO2k6LTUwNDU2NDQ0NztpOjE7aTotOTAzNzM1Njc0O2k6MjtpOi05Mjk1MDY4MTk7aTozO2k6MzIxMDY0MTI5O2k6NDtpOi0xMTc5MTczMTYxO2k6NTtpOjE3OTAxODI2NDtpOjY7aTotMTcxMDA1NzA2ODtpOjc7aTotMTI4NTA1NzE2NTtpOjg7aTo4MDA7aTo5O2k6MDtpOjEwO3M6NjQ6IvI1Qg48RdSl4pe3QUbJ+J2UtDxtPrKcDNcnoVES6YGnafum6zf+pAwZIekSW/byYI4dt5wowtQB/4wBGyzKNJgiO31pOjM7aToyO2k6NDthOjA6e319"}`
  - Hinweis: Gilt für SHA-256 unter dieser PHP-Version; keine Aussage über andere Algorithmen (xxh3 ist hier nicht Gegenstand).
- **NC 35: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 150 ms
  - Beobachtet: `{"php_version": "8.5.10", "offsets_tested": 16, "serialized_state_bytes": 313, "state_sample_base64": "TzoxMToiSGFzaENvbnRleHQiOjU6e2k6MDtzOjY6InNoYTI1NiI7aToxO2k6MDtpOjI7YToxMTp7aTowO2k6LTUwNDU2NDQ0NztpOjE7aTotOTAzNzM1Njc0O2k6MjtpOi05Mjk1MDY4MTk7aTozO2k6MzIxMDY0MTI5O2k6NDtpOi0xMTc5MTczMTYxO2k6NTtpOjE3OTAxODI2NDtpOjY7aTotMTcxMDA1NzA2ODtpOjc7aTotMTI4NTA1NzE2NTtpOjg7aTo4MDA7aTo5O2k6MDtpOjEwO3M6NjQ6IvI1Qg48RdSl4pe3QUbJ+J2UtDxtPrKcDNcnoVES6YGnafum6zf+pAwZIekSW/byYI4dt5wowtQB/4wBGyzKNJgiO31pOjM7aToyO2k6NDthOjA6e319"}`
  - Hinweis: Gilt für SHA-256 unter dieser PHP-Version; keine Aussage über andere Algorithmen (xxh3 ist hier nicht Gegenstand).

### F02 – Prozessneustart: Zustand in Datei, Fortsetzung in neuem PHP-Prozess; Datei aus der Nextcloud-Node-API

- **Spike:** SP-H · **Dokumentbezug:** job-processing.md §3.6 (H1–H3), SP-H, hash_resume, DEC-IMM-008
- **Geprüfte Annahme:** Der Zustand überlebt einen Prozessneustart; eine über die Nextcloud-Node-API gelesene Datei lässt sich in einem anderen Prozess ab dem gespeicherten Offset fortsetzen.
- **NC 34: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 882 ms
  - Beobachtet: `{"new_process_digest": "2f1bd996146ae0912663f5e96fdd2766e31065b2ee6e2caee489eecca1f2669b", "expected": "2f1bd996146ae0912663f5e96fdd2766e31065b2ee6e2caee489eecca1f2669b"}`
- **NC 35: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 933 ms
  - Beobachtet: `{"new_process_digest": "2f1bd996146ae0912663f5e96fdd2766e31065b2ee6e2caee489eecca1f2669b", "expected": "2f1bd996146ae0912663f5e96fdd2766e31065b2ee6e2caee489eecca1f2669b"}`

### F03 – Manipulierter Zwischenzustand: alle Einzelbitfehler im serialisierten Kontext

- **Spike:** SP-H · **Dokumentbezug:** job-processing.md §3.6 (H1–H3), SP-H, hash_resume, DEC-IMM-008
- **Geprüfte Annahme:** PHP erkennt veränderte serialisierte Hash-Zustände nicht zuverlässig: einzelne Bitfehler werden angenommen und führen zu einem falschen Digest (daher Prüfsumme/Umschlag und End-Gegenprüfung nötig).
- **NC 34: PASS** · Evidenz: endliche Stichprobe (keine Garantie) · Versuche: 1 · Dauer 39 ms
  - Beobachtet: `{"bit_flip_tally": {"bits_total": 2480, "rejected": 1704, "accepted_correct_digest": 385, "accepted_wrong_digest": 391, "examples_silent_wrong": [{"byte": 64, "bit": 0}, {"byte": 64, "bit": 1}, {"byte": 64, "bit": 2}], "state_bytes": 310}, "envelope_checksum_undetected_bit_flips": 0}`
  - Hinweis: Die Prüfsumme schützt nur gegen Veränderung der Nutzlast, nicht gegen einen zusammenpassend gefälschten Umschlag (nicht Bedrohungsmodell).
- **NC 35: PASS** · Evidenz: endliche Stichprobe (keine Garantie) · Versuche: 1 · Dauer 35 ms
  - Beobachtet: `{"bit_flip_tally": {"bits_total": 2480, "rejected": 1704, "accepted_correct_digest": 385, "accepted_wrong_digest": 391, "examples_silent_wrong": [{"byte": 64, "bit": 0}, {"byte": 64, "bit": 1}, {"byte": 64, "bit": 2}], "state_bytes": 310}, "envelope_checksum_undetected_bit_flips": 0}`
  - Hinweis: Die Prüfsumme schützt nur gegen Veränderung der Nutzlast, nicht gegen einen zusammenpassend gefälschten Umschlag (nicht Bedrohungsmodell).

### F04 – Ungültiger Offset beim Fortsetzen

- **Spike:** SP-H · **Dokumentbezug:** job-processing.md §3.6 (H1–H3), SP-H, hash_resume, DEC-IMM-008
- **Geprüfte Annahme:** Ein falscher Fortsetzungs-Offset wird von PHP nicht bemerkt: kein Fehler, aber falscher Digest – die Erkennung muss über den im Umschlag gespeicherten Offset und die Endprüfung erfolgen.
- **NC 34: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 0 ms
  - Beobachtet: `{"result_by_offset": {"3999": "silent_wrong_digest", "4001": "silent_wrong_digest", "0": "silent_wrong_digest", "10000": "silent_wrong_digest", "10050": "silent_wrong_digest", "4000": "correct"}}`
- **NC 35: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 0 ms
  - Beobachtet: `{"result_by_offset": {"3999": "silent_wrong_digest", "4001": "silent_wrong_digest", "0": "silent_wrong_digest", "10000": "silent_wrong_digest", "10050": "silent_wrong_digest", "4000": "correct"}}`

### F05 – Dateiänderung während der Unterbrechung: Erkennung über Signatur (Größe, MTime, ETag)

- **Spike:** SP-H · **Dokumentbezug:** job-processing.md §3.6 (H1–H3), SP-H, hash_resume, DEC-IMM-008
- **Geprüfte Annahme:** Wird die Datei zwischen Unterbrechung und Fortsetzung verändert, erkennt der Vergleich der Signatur (Größe, MTime, ETag) die Änderung; ohne Erkennung ergibt die Fortsetzung einen falschen Digest.
- **NC 34: FAIL** · Evidenz: Gegenbeispiel (Annahme widerlegt) · Versuche: 1 · Dauer 3181 ms
  - ✗ gleiche Größe, Änderung vor dem Offset, gleiche Sekunde: eine falsche Fortsetzung wird durch die Signatur erkannt — `{"sig0": [300000, 1791560115, "396c28144f139d433b127d0c8e67ea21"], "sig1": [300000, 1791560115, "396c28144f139d433b127d0c8e67ea21"]}`
  - Beobachtet: `{"append": {"label": "Anhängen (Größe ändert sich)", "signature_before": [300000, 1791560114, "888b524c42c060b578af7b209f3c2194"], "signature_after": [300050, 1791560114, "888b524c42c060b578af7b209f3c2194"], "signature_detects_change": true, "resumed_digest_equals_true_digest_of_new_content": true}, "same_size_same_second": {"label": "gleiche Größe, Änderung vor dem Offset, gleiche Sekunde", "signature_before": [300000, 1791560115, "396c28144f139d433b127d0c8e67ea21"], "signature_after": [300000, 1791560115, "396c28144f139d433b127d0c8e67ea21"], "signature_detects_change": false, "resumed_digest_equals_true_digest_of_new_content": false}, "same_size_after_2s": {"label": "gleiche Größe, Änderung vor dem Offset, nach 2 s", "signature_before": [300000, 1791560115, "61eecd3b8f6c40032b095a8dc6f105a9"], "signature_after": [300000, 1791560117, "2cab7ed0a3d0c37fde94c832681042df"], "signature_dete…`
  - Hinweis: gleiche Größe, Änderung vor dem Offset, gleiche Sekunde: Änderung nicht an der Signatur erkennbar, Fortsetzung ergibt falschen Digest. Gegenmaßnahme: Signatur nach dem Hash erneut vergleichen genügt hier nicht; Neuberechnung bei Zweifel oder Mindest-Ruhezeit seit MTime.
- **NC 35: FAIL** · Evidenz: Gegenbeispiel (Annahme widerlegt) · Versuche: 1 · Dauer 3236 ms
  - ✗ gleiche Größe, Änderung vor dem Offset, gleiche Sekunde: eine falsche Fortsetzung wird durch die Signatur erkannt — `{"sig0": [300000, 1791559944, "b56903a4dbe4d505c1a05750f54a933a"], "sig1": [300000, 1791559944, "b56903a4dbe4d505c1a05750f54a933a"]}`
  - Beobachtet: `{"append": {"label": "Anhängen (Größe ändert sich)", "signature_before": [300000, 1791559944, "2196e271a4c949676054b8d257904a10"], "signature_after": [300050, 1791559944, "2196e271a4c949676054b8d257904a10"], "signature_detects_change": true, "resumed_digest_equals_true_digest_of_new_content": true}, "same_size_same_second": {"label": "gleiche Größe, Änderung vor dem Offset, gleiche Sekunde", "signature_before": [300000, 1791559944, "b56903a4dbe4d505c1a05750f54a933a"], "signature_after": [300000, 1791559944, "b56903a4dbe4d505c1a05750f54a933a"], "signature_detects_change": false, "resumed_digest_equals_true_digest_of_new_content": false}, "same_size_after_2s": {"label": "gleiche Größe, Änderung vor dem Offset, nach 2 s", "signature_before": [300000, 1791559945, "852567096e8b384ae0f9b4c41b7a1aaa"], "signature_after": [300000, 1791559947, "63a5f9ef688217c409392873b018526d"], "signature_dete…`
  - Hinweis: gleiche Größe, Änderung vor dem Offset, gleiche Sekunde: Änderung nicht an der Signatur erkennbar, Fortsetzung ergibt falschen Digest. Gegenmaßnahme: Signatur nach dem Hash erneut vergleichen genügt hier nicht; Neuberechnung bei Zweifel oder Mindest-Ruhezeit seit MTime.

### F06 – Deserialisierung nur mit allowed_classes und Algorithmus-Fremdobjekte

- **Spike:** SP-H · **Dokumentbezug:** job-processing.md §3.6 (H1–H3), SP-H, hash_resume, DEC-IMM-008
- **Geprüfte Annahme:** unserialize() mit allowed_classes=[HashContext] weist fremde Klassen ab (kein Objekt-Injection-Risiko aus der Datenbank); ein Kontext eines anderen Algorithmus wird nicht als SHA-256 ausgegeben.
- **NC 34: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 0 ms
  - Beobachtet: `{"md5_state_resumed_digest_length_hex": 32, "foreign_class_unserialized_as": "__PHP_Incomplete_Class"}`
- **NC 35: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 0 ms
  - Beobachtet: `{"md5_state_resumed_digest_length_hex": 32, "foreign_class_unserialized_as": "__PHP_Incomplete_Class"}`

### F07 – Durchsatz SHA-256 über die Node-API (Messung, Referenz für Pre-/Post-Hash-Kosten)

- **Spike:** SP-F · **Dokumentbezug:** job-processing.md §3.6 (H1–H3), SP-H, hash_resume, DEC-IMM-008
- **Geprüfte Annahme:** Messung: wie lange dauert ein vollständiger SHA-256 über eine 48-MiB-Datei per Node::fopen()-Stream in diesem Container (nur Größenordnung, Hostlast ungeprüft).
- **NC 34: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 631 ms
  - Beobachtet: `{"mib_per_second": 390.5, "seconds": 0.123}`
  - Hinweis: Reine Größenordnung auf einem ausgelasteten Entwicklungshost; keine Leistungszusage.
- **NC 35: PASS** · Evidenz: Einzelbeobachtung · Versuche: 1 · Dauer 1215 ms
  - Beobachtet: `{"mib_per_second": 414, "seconds": 0.116}`
  - Hinweis: Reine Größenordnung auf einem ausgelasteten Entwicklungshost; keine Leistungszusage.

### F10 – HashContext-Matrix

- PHP-Versionen: `{"php82": "8.2.34", "php8314": "8.3.14", "php83": "8.3.35", "php84": "8.4.26", "php85": "8.5.11", "nc34": "8.5.11", "nc35": "8.5.10"}`
- Ergebnis PASS: 147/147 Fortsetzungen korrekt

| Erzeuger \ Verbraucher | php82 | php8314 | php83 | php84 | php85 | nc34 | nc35 |
|---|---|---|---|---|---|---|---|
| php82 | ok | ok | ok | ok | ok | ok | ok |
| php8314 | ok | ok | ok | ok | ok | ok | ok |
| php83 | ok | ok | ok | ok | ok | ok | ok |
| php84 | ok | ok | ok | ok | ok | ok | ok |
| php85 | ok | ok | ok | ok | ok | ok | ok |
| nc34 | ok | ok | ok | ok | ok | ok | ok |
| nc35 | ok | ok | ok | ok | ok | ok | ok |

## 5a. Konkurrenztests am Ziel (A20–A29) – Zählwerte

| Fall | Mover | Zweiter Akteur | NC | ausgewertet (≤2 ms ausgerichtet) | **Move ersetzt erfolgreich geschriebenen Inhalt** | Zweiter ersetzt Ergebnis des Movers | Fehler gemeldet, Wirkung vorhanden (Mover / Zweiter) | beide gescheitert | Ergebnis |
|---|---|---|---|---|---|---|---|---|---|
| A20 | Node::move | Folder::newFile | 34 | 150 (150) | **27** | 31 | 0 / 0 | 57 | FAIL |
| A20 | Node::move | Folder::newFile | 35 | 150 (150) | **29** | 33 | 0 / 0 | 55 | FAIL |
| A21 | Node::move | WebDAV PUT | 34 | 150 (150) | **17** | 48 | 0 / 4 | 4 | FAIL |
| A21 | Node::move | WebDAV PUT | 35 | 150 (150) | **13** | 53 | 0 / 1 | 2 | FAIL |
| A22 | Node::move | Node::move (anderer Quelle) | 34 | 150 (150) | **27** | 30 | 0 / 0 | 54 | FAIL |
| A22 | Node::move | Node::move (anderer Quelle) | 35 | 150 (150) | **29** | 31 | 0 / 0 | 54 | FAIL |
| A23 | WebDAV MOVE Overwrite:F | Folder::newFile | 34 | 150 (150) | **0** | 30 | 0 / 0 | 1 | PASS |
| A23 | WebDAV MOVE Overwrite:F | Folder::newFile | 35 | 150 (150) | **0** | 27 | 0 / 0 | 2 | PASS |
| A24 | WebDAV MOVE Overwrite:F | WebDAV PUT | 34 | 150 (150) | **0** | 39 | 0 / 50 | 50 | PASS |
| A24 | WebDAV MOVE Overwrite:F | WebDAV PUT | 35 | 150 (150) | **0** | 36 | 0 / 50 | 50 | PASS |
| A25 | WebDAV MOVE Overwrite:F | WebDAV PUT If-None-Match:* | 34 | 150 (150) | **0** | 0 | 0 / 48 | 48 | PASS |
| A25 | WebDAV MOVE Overwrite:F | WebDAV PUT If-None-Match:* | 35 | 150 (150) | **0** | 1 | 0 / 48 | 48 | PASS |
| A26 | WebDAV MOVE Overwrite:F | WebDAV MOVE Overwrite:F (anderer Quelle) | 34 | 150 (150) | **0** | 0 | 0 / 0 | 53 | PASS |
| A26 | WebDAV MOVE Overwrite:F | WebDAV MOVE Overwrite:F (anderer Quelle) | 35 | 150 (150) | **0** | 0 | 0 / 0 | 55 | PASS |
| A27 | geschützter Move (Prototyp) | Folder::newFile | 34 | 150 (150) | **0** | 41 | 0 / 0 | 49 | PASS |
| A27 | geschützter Move (Prototyp) | Folder::newFile | 35 | 150 (150) | **0** | 40 | 0 / 0 | 49 | PASS |
| A28 | geschützter Move (Prototyp) | WebDAV PUT | 34 | 150 (150) | **0** | 65 | 0 / 1 | 1 | PASS |
| A28 | geschützter Move (Prototyp) | WebDAV PUT | 35 | 150 (150) | **0** | 64 | 0 / 3 | 3 | PASS |
| A29 | geschützter Move (Prototyp) | geschützter Move (anderer Quelle) | 34 | 150 (150) | **0** | 0 | 0 / 0 | 54 | PASS |
| A29 | geschützter Move (Prototyp) | geschützter Move (anderer Quelle) | 35 | 150 (150) | **0** | 0 | 0 / 0 | 55 | PASS |

## 5. Abgeleitete Fähigkeiten (Vorschlag, nicht verbindlich)

Abgeleitet nach festen Regeln aus den Testergebnissen **je Nextcloud-Version** für das getestete Tupel (lokaler Heim-Storage mit der oben genannten Wrapperkette, MariaDB, Redis-Locking). „Kandidat“ heißt nur: kein getesteter Fall widerlegt die Fähigkeit; es ist **keine** Freigabe und gilt für kein anderes Tupel. Die Zeile „Gegenprobe“ muss FAIL zeigen (bestätigt die Abhängigkeit von den Dateisperren).

| Fähigkeit | Bedeutung | NC 34 | NC 35 | Beteiligte Fälle (NC 34 / NC 35) |
|---|---|---|---|---|
| `move_no_overwrite[Node::move]` | Node::move lehnt ein vorhandenes Ziel ab | false (widerlegt) | false (widerlegt) | 34: A02:FAIL A03:FAIL A04:FAIL A05:FAIL A09:FAIL A20:FAIL A21:FAIL A22:FAIL<br>35: A02:FAIL A03:FAIL A04:FAIL A05:FAIL A09:FAIL A20:FAIL A21:FAIL A22:FAIL |
| `move_no_overwrite[WebDAV MOVE Overwrite:F]` | WebDAV MOVE mit Overwrite: F ist auch unter Konkurrenz sicher | false (widerlegt) | false (widerlegt) | 34: A11:PASS A13:FAIL A23:PASS A24:PASS A25:PASS A26:PASS<br>35: A11:PASS A13:FAIL A23:PASS A24:PASS A25:PASS A26:PASS |
| `move_no_overwrite[geschützter Move, interne API]` | Prototyp: Überschreibschutz im gesperrten Storage-Bereich (setzt wirksame Dateisperren voraus) | Kandidat (alle Fälle PASS) | Kandidat (alle Fälle PASS) | 34: A14:PASS A27:PASS A28:PASS A29:PASS<br>35: A14:PASS A27:PASS A28:PASS A29:PASS |
| `Gegenprobe: geschützter Move ohne Dateisperren` | muss ohne Sperren scheitern (A15 = FAIL ist hier ERWARTET und bestätigt die Abhängigkeit) | false (widerlegt) | false (widerlegt) | 34: A15:FAIL<br>35: A15:FAIL |
| `move_source_by_path` | Move eines veralteten Knotens trifft nie eine andere Datei | false (widerlegt) | false (widerlegt) | 34: A06:FAIL A08:PASS A30:PASS<br>35: A06:FAIL A08:PASS A30:PASS |
| `lock_target (öffentliche API)` | Sperre auf nicht existierenden Zielpfad, kompatibel mit eigenem Move | false (widerlegt) | false (widerlegt) | 34: C04:PASS C05:FAIL<br>35: C04:PASS C05:FAIL |
| `trash_verified` | Löschen landet im Papierkorb; Inhalt/Identität über die File-ID belegbar | Kandidat (alle Fälle PASS) | Kandidat (alle Fälle PASS) | 34: D01:PASS D02:PASS D05:PASS D06:PASS B05:PASS<br>35: D01:PASS D02:PASS D05:PASS D06:PASS B05:PASS |
| `trash_entry_unique_per_second` | zwei Löschungen desselben Pfades in einer Sekunde behalten beide Papierkorbeinträge | false (widerlegt) | false (widerlegt) | 34: D03:FAIL<br>35: D03:FAIL |
| `trash_restore_no_overwrite` | Restore überschreibt eine inzwischen belegte Zieldatei nicht | Kandidat (alle Fälle PASS) | Kandidat (alle Fälle PASS) | 34: D04:PASS<br>35: D04:PASS |
| `hash_resume (SHA-256)` | Fortsetzbarer SHA-256-Kontext mit Umschlag (inkl. PHP-Versionsmatrix F10) | Kandidat (alle Fälle PASS) | Kandidat (alle Fälle PASS) | 34: F01:PASS F02:PASS F03:PASS F04:PASS F06:PASS F10:PASS<br>35: F01:PASS F02:PASS F03:PASS F04:PASS F06:PASS F10:PASS |
| `signature_change_detection` | Signatur aus Größe/MTime/ETag erkennt jede Inhaltsänderung | false (widerlegt) | false (widerlegt) | 34: B06:FAIL F05:FAIL<br>35: B06:FAIL F05:FAIL |

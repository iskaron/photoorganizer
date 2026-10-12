# Plattform-Spikes: experimentelle Prüfung der Nextcloud-Annahmen

Stand: 2026-10-09 · Runde 5 (Auftrag 5) · Status: **Befunde experimentell auf Nextcloud 34.0.4 und 35.0.0 (lokaler Heim-Storage, PHP 8.5, MariaDB 11.4, Redis-Locking); keine Aussage über andere Storage-Klassen** [T]

Dieses Dokument berichtet die Ergebnisse der Spikes SP-A1…A6, SP-B, SP-C, SP-E, SP-H, SP-I und SP-J (`nextcloud-integration.md`, `job-processing.md` §6.3.5) sowie der Events- und HashContext-Fragen. Es ändert **keine** bestehende Anforderung und keine Entscheidung. Korrekturbedarf ist in §10 gesammelt und wird dem Product Owner zur Entscheidung vorgelegt (`open-questions.md`, OQ-073 ff., CONFLICT-018 ff.). Testinfrastruktur und Bedienung: `tests/integration/nextcloud-spikes/README.md`; Umgebung: `environment.md` §6; Rohdaten: `tests/integration/nextcloud-spikes/results/` (je Lauf `results.json`; Lesebericht `results/REPORT.md`; Methoden-Fingerprints `results/nc3x/method-fingerprints.json`).

## 0. Methodik, Evidenzarten und Grenzen

**Evidenzarten** (jede Aussage trägt genau eine):

| Kürzel | Bedeutung |
|---|---|
| **E** | **experimentell nachgewiesen** in der Testumgebung (Testfall-ID genannt), für genau diese Version und dieses Storage-Tupel |
| **Q** | **aus dem Quelltext** des jeweiligen Release-Images abgeleitet (Datei/Zeile genannt), nicht für sich experimentell bestätigt |
| **V** | aus dem **veröffentlichten API-Vertrag** (PHPDoc der OCP-Schnittstelle) abgeleitet |
| **U** | unverifiziert / nicht geprüft |

**Ergebnisklassen.** Jeder Testfall nennt die geprüfte Architekturannahme (`hypothesis`). **PASS** = Annahme bestätigt, **FAIL** = widerlegt (oft mit Gegenbeispiel), **INCONCLUSIVE** = nicht aussagekräftig (Harness-Fehler, Instrumentierung nicht gegriffen, zu schwache Stichprobe). FAIL und INCONCLUSIVE werden nirgends als PASS gewertet. Ein PASS in einem Konkurrenztest ist eine **endliche Stichprobe** (Versuchszahl genannt) und beweist keine Atomizität; ein nicht reproduzierter Fehler gilt nicht als ausgeschlossen. Ein **FAIL mit Gegenbeispiel** ist dagegen ein endgültiger Beleg gegen die Annahme für das getestete Tupel.

**Das einzige getestete Tupel:** Nextcloud 34.0.4 bzw. 35.0.0 · Heim-Storage `OC\Files\Storage\Home` (Local) im Docker-Named-Volume auf ext4 · Wrapperkette `Files_Trashbin\Storage → FilesLock\Storage\LockWrapper → (Probe-Wrapper, passiv) → Quota → Home` · MariaDB 11.4.13 · Redis-Locking (`MemcacheLockingProvider`, `filelocking.enabled=true`) · PHP 8.5.10/8.5.11 · Papierkorb und Versionen aktiv, Verschlüsselung aus. **Keine** Aussage gilt für Objektspeicher, externe Speicher, Freigabe-Storages als Zielstorage (außer wo genannt), Gruppenordner, Verschlüsselung, DB-basiertes Locking, MySQL oder PHP 8.3 im Nextcloud-Betrieb (§11). Die Referenzumgebung des PO (PHP 8.3.14, MySQL) weicht ab (`environment.md` §6.3).

**Instrumentierung.** Zwei Testhilfen nutzen *interne* Nextcloud-APIs (Storage-Wrapper `OC\Files\Filesystem::addStorageWrapper`, Sabre-Hook über `SabrePluginAddEvent`). Sie sind passiv, solange keine Steuerdatei bzw. kein Flag gesetzt ist, und in den Fallbeschreibungen als „instrumentiert“ gekennzeichnet. Sie vermessen Zeitfenster deterministisch und sind kein Produktdesign. Der „geschützte Move“ (A14/A15/A27–A29) ist ein **Prototyp zur Bewertung einer Option**, keine Empfehlung.

**Identische Implementierung in 34 und 35.** Die Token-Fingerprints (Hash über den Tokenstrom ohne Leerraum und Kommentare) aller 16 für die Analyse relevanten Methoden sind in Nextcloud 34.0.4 und 35.0.0 **identisch** (§3.1, Tabelle). Das erklärt, warum alle Testfälle in beiden Versionen dasselbe Ergebnis liefern; es bedeutet nicht, dass künftige Versionen dasselbe tun.

## 1. Umfang

Es wurden **60 Testfälle** je Nextcloud-Version ausgeführt (Gruppe A: Move/Kollisionen/Konkurrenz, B: File-IDs, C: Locking, D: Papierkorb, E: Events, F: HashContext) plus die versionsübergreifende HashContext-Matrix F10. Konkurrenztests liefen mit **150 Wiederholungen** je Fall (zwei Prozesse, gemeinsamer Start auf Mikrosekunden genau, Startversatz fein ±5 ms, mittel ±150 ms, grob ±600 ms; „ausgerichtet“ = beide Aufrufe starteten ≤ 2 ms nach dem Plan).

| Spike (`nextcloud-integration.md`, `job-processing.md` §6.3.5) | Testfälle |
|---|---|
| SP-A1 Ziel existiert als Datei | A02, A03, A10, A11 |
| SP-A2 Ziel existiert als Ordner | A04 |
| SP-A3 Ziel entsteht nebenläufig / zwischen Prüfung und Move | A09, A12, A13, A14, A15, A20–A29 |
| SP-A4 Quelle = Ziel, Groß-/Kleinschreibung, Unicode | A05 |
| SP-A5 verspäteter zweiter Aufruf | A06, A16 |
| SP-A6 Zielverzeichnis fehlt / ungültiger Name / Quelle fehlt | A07 |
| SP-I Move eines veralteten Knotens | A06, A08, A30 |
| SP-B / SP-C File-IDs, Storage-Referenzen, Storage-Wechsel | B01–B07 |
| SP-E Sperren | C01–C07, A12 |
| SP-J Papierkorb | D01–D06, B05 |
| Events | E01–E06 |
| SP-H HashContext | F01–F07, F10 |

## 2. Gesamtergebnis

| ID | Titel | NC 34 | NC 35 | Evidenz |
|---|---|---|---|---|
| A01 | Einfacher Move/Rename auf demselben Storage | PASS | PASS | Einzelbeobachtung |
| A02 | Ziel existiert bereits (Datei) mit gleichem Inhalt | FAIL | FAIL | Gegenbeispiel (Annahme widerlegt) |
| A03 | Ziel existiert bereits (Datei) mit anderem Inhalt | FAIL | FAIL | Gegenbeispiel (Annahme widerlegt) |
| A04 | Ziel existiert als Ordner (nicht leer) / Ordner auf Datei | FAIL | FAIL | Gegenbeispiel (Annahme widerlegt) |
| A05 | Quelle = Ziel, Groß-/Kleinschreibung, Unicode-Normalisierung | FAIL | FAIL | Gegenbeispiel (Annahme widerlegt) |
| A06 | Verspäteter zweiter Aufruf mit veraltetem Knoten | FAIL | FAIL | Gegenbeispiel (Annahme widerlegt) |
| A07 | Fehlerfälle: Zielordner fehlt, ungültiger Name, Quelle fehlt | PASS | PASS | Einzelbeobachtung |
| A08 | Move eines zuvor geladenen, inzwischen umbenannten Knotens (folgt er der File-ID?) | PASS | PASS | Einzelbeobachtung |
| A09 | Ziel entsteht zwischen Existenzprüfung und Move (Kindprozess legt das Ziel an) | FAIL | FAIL | Gegenbeispiel (Annahme widerlegt) |
| A10 | Node::newFile auf vorhandenem Pfad (Neuanlage-API als mögliche Reservierung) | FAIL | FAIL | Gegenbeispiel (Annahme widerlegt) |
| A11 | WebDAV MOVE mit Overwrite: F auf vorhandenes Ziel (Vergleichspunkt) | PASS | PASS | Einzelbeobachtung |
| A12 | Während eines (künstlich angehaltenen) Moves: kann ein Konkurrent das Ziel anlegen? | PASS | PASS | Einzelbeobachtung |
| A13 | WebDAV MOVE Overwrite: F – Ziel entsteht NACH dem Overwrite-Check (Sabre beforeMove angehalten) | FAIL | FAIL | Gegenbeispiel (Annahme widerlegt) |
| A14 | Prototyp „geschützter Move“ (Storage-Wrapper, interne API) mit Dateisperren | PASS | PASS | Einzelbeobachtung |
| A15 | Prototyp „geschützter Move“ (Storage-Wrapper, interne API) ohne Dateisperren | FAIL | FAIL | Gegenbeispiel (Annahme widerlegt) |
| A16 | Fehler und Ausnahmen während des Moves: Listener bricht vor dem Rename ab / scheitert nach dem Rename | PASS | PASS | Einzelbeobachtung |
| A20 | Konkurrenz am Ziel: node_move gegen node_newfile | FAIL (150×) | FAIL (150×) | Gegenbeispiel (Annahme widerlegt) |
| A21 | Konkurrenz am Ziel: node_move gegen dav_put | FAIL (150×) | FAIL (150×) | Gegenbeispiel (Annahme widerlegt) |
| A22 | Konkurrenz am Ziel: node_move gegen node_move | FAIL (150×) | FAIL (150×) | Gegenbeispiel (Annahme widerlegt) |
| A23 | Konkurrenz am Ziel: dav_move_F gegen node_newfile | PASS (150×) | PASS (150×) | endliche Stichprobe (keine Garantie) |
| A24 | Konkurrenz am Ziel: dav_move_F gegen dav_put | PASS (150×) | PASS (150×) | endliche Stichprobe (keine Garantie) |
| A25 | Konkurrenz am Ziel: dav_move_F gegen dav_put_inm | PASS (150×) | PASS (150×) | endliche Stichprobe (keine Garantie) |
| A26 | Konkurrenz am Ziel: dav_move_F gegen dav_move_F | PASS (150×) | PASS (150×) | endliche Stichprobe (keine Garantie) |
| A27 | Konkurrenz am Ziel: node_move_guarded gegen node_newfile | PASS (150×) | PASS (150×) | endliche Stichprobe (keine Garantie) |
| A28 | Konkurrenz am Ziel: node_move_guarded gegen dav_put | PASS (150×) | PASS (150×) | endliche Stichprobe (keine Garantie) |
| A29 | Konkurrenz am Ziel: node_move_guarded gegen node_move_guarded | PASS (150×) | PASS (150×) | endliche Stichprobe (keine Garantie) |
| A30 | Konkurrenz an der Quelle: App-Move gegen Client-Umbenennung derselben Datei | PASS (150×) | PASS (150×) | endliche Stichprobe (keine Garantie) |
| B01 | Rename im selben Ordner: File-ID und Storage-Referenz | PASS | PASS | Einzelbeobachtung |
| B02 | Move in anderen Ordner (inkl. Ordner-Move mit Kindern) | PASS | PASS | Einzelbeobachtung |
| B03 | Move durch WebDAV-Client (Umbenennen, Verschieben) | PASS | PASS | Einzelbeobachtung |
| B04 | Copy (Node::copy und WebDAV COPY) | PASS | PASS | Einzelbeobachtung |
| B05 | Trash und Restore: File-ID, Pfad, Storage-Referenzen | PASS | PASS | Einzelbeobachtung |
| B06 | Inhaltsänderung (gleiche Größe): File-ID, ETag, MTime | FAIL | FAIL | Gegenbeispiel (Annahme widerlegt) |
| B07 | Move über Storage-Grenzen (Heim-Storage → Freigabe-Storage eines anderen Benutzers) | FAIL | FAIL | Einzelbeobachtung |
| C01 | Node::lock – Verträglichkeitsmatrix shared/exclusive zwischen zwei Prozessen | PASS | PASS | Einzelbeobachtung |
| C02 | Konkurrenz durch WebDAV-Client und Node-API gegen eine gesperrte Quelldatei | PASS | PASS | Einzelbeobachtung |
| C03 | Sperre auf Zielordner: wirkt sie auf Anlage/Move von Kindern? | PASS | PASS | Einzelbeobachtung |
| C04 | Nicht existierender Zielpfad: Sperrbarkeit über ILockingProvider (lock_target) | PASS | PASS | Einzelbeobachtung |
| C05 | Wiedereintritt: eigene Sperre auf der Quelle und anschließender Node::move | FAIL | FAIL | Einzelbeobachtung |
| C06 | Zwei Worker erwerben gleichzeitig dieselbe exklusive Sperre (kooperativer Mutex) | PASS (37×) | PASS (37×) | endliche Stichprobe (keine Garantie) |
| C07 | Prozessabbruch (kill -9) mit gehaltener Sperre: bleibt die Sperre bestehen, wie lange? | PASS | PASS | Einzelbeobachtung |
| D01 | Node::delete() verwendet den Nextcloud-Papierkorb; Identität, Erreichbarkeit und Inhalt des gelöschten Knotens | PASS | PASS | Einzelbeobachtung |
| D02 | Nachweis einer Papierkorboperation (Pre-Delete-Hash beider Exemplare, Post-Beobachtung) | PASS | PASS | Einzelbeobachtung |
| D03 | Zwei Löschungen gleichnamiger Dateien am selben Pfad kurz nacheinander (gleiche Sekunde) und mit 2 s Abstand | FAIL | FAIL | Gegenbeispiel (Annahme widerlegt) |
| D04 | Restore bei inzwischen vorhandener Zieldatei | PASS | PASS | Einzelbeobachtung |
| D05 | Löschen per WebDAV DELETE und Löschen eines Ordners | PASS | PASS | Einzelbeobachtung |
| D06 | Fähigkeitserkennung trash_verified (nicht destruktiv) | PASS | PASS | Quelltextanalyse (nicht experimentell) |
| E01 | App-interne Dateioperationen über die Node-API (CLI-Prozess): Create, Write, Rename, Move, Copy, Delete | PASS | PASS | Einzelbeobachtung |
| E02 | WebDAV-Operationen eines Clients (Apache-Prozess): PUT neu/überschreiben, MOVE (Rename/Move), COPY, DELETE | PASS | PASS | Einzelbeobachtung |
| E03 | Chunked Upload v2 (Nextcloud-/Mobil-/Desktop-Clients): wann und wie oft Ereignisse für die Zieldatei | PASS | PASS | Einzelbeobachtung |
| E04 | Papierkorb: Delete und Restore (Node-API und WebDAV-Restore) | PASS | PASS | Einzelbeobachtung |
| E05 | Identitätsmerkmale der Ereignisse (Pfad, File-ID, Quelle/Ziel) über alle Szenarien | FAIL | FAIL | Einzelbeobachtung |
| E06 | Ordner-Move: Ereignisse für den Ordner und für enthaltene Dateien (group_follow-Erkennung) | PASS | PASS | Einzelbeobachtung |
| F01 | SHA-256-Kontext: Serialisieren und Fortsetzen an vielen Offsets (reines PHP) | PASS | PASS | Einzelbeobachtung |
| F02 | Prozessneustart: Zustand in Datei, Fortsetzung in neuem PHP-Prozess; Datei aus der Nextcloud-Node-API | PASS | PASS | Einzelbeobachtung |
| F03 | Manipulierter Zwischenzustand: alle Einzelbitfehler im serialisierten Kontext | PASS | PASS | endliche Stichprobe (keine Garantie) |
| F04 | Ungültiger Offset beim Fortsetzen | PASS | PASS | Einzelbeobachtung |
| F05 | Dateiänderung während der Unterbrechung: Erkennung über Signatur (Größe, MTime, ETag) | FAIL | FAIL | Gegenbeispiel (Annahme widerlegt) |
| F06 | Deserialisierung nur mit allowed_classes und Algorithmus-Fremdobjekte | PASS | PASS | Einzelbeobachtung |
| F07 | Durchsatz SHA-256 über die Node-API (Messung, Referenz für Pre-/Post-Hash-Kosten) | PASS | PASS | Einzelbeobachtung |
| F10 | HashContext-Zustand zwischen PHP-Versionen und Laufzeitumgebungen übertragen | PASS (147×) | (gleiche Matrix) | endliche Stichprobe (keine Garantie) |

**Zählung:** NC 34: PASS 42 · FAIL 18 · INCONCLUSIVE 0 (von 60); NC 35: PASS 42 · FAIL 18 · INCONCLUSIVE 0 (von 60); F10 (gemeinsam): PASS. **FAIL heißt hier meist „Architekturannahme widerlegt“ und ist das eigentliche Ergebnis der Spikes; INCONCLUSIVE ist nie als PASS zu lesen.**

Alle Fälle liefern in Nextcloud 34.0.4 und 35.0.0 dasselbe Ergebnis (nur Instanz-IDs, ETags und Zeiten unterscheiden sich).

## 3. Spike A – Move und Zielkollisionen (SP-A1…A6, SP-I)

### 3.1 API-Vertrag und Implementierungsanalyse

**Vertrag (V).** `OCP\Files\Node::move($targetPath)` (`@since 6.0.0`): „Move the file or folder to a new location“; Ausnahmen `NotFoundException`, `NotPermittedException` („if move not allowed or failed“), `LockedException`, `InvalidPathException`. **Der Vertrag sagt nichts darüber, was bei vorhandenem Ziel geschieht** – weder Überschreiben noch Ablehnung. `Folder::newFile($path, $content)` (`@since 6.0.0`) nennt nur `NotPermittedException`; zu vorhandenen Pfaden schweigt der Vertrag ebenfalls. `Node::lock()` (`@since 9.1.0`) dokumentiert „mandatory locking … blocks *all* other operations for that file, even within the same php process“ und: „Acquiring any lock on a file will also create a shared lock on all parent folders“ (das erklärt C03, C05). `Node::delete()` nennt den Papierkorb nicht.

**Implementierung (Q), identisch in 34.0.4 und 35.0.0** (Zeilen in 34 / 35):

| Schicht | Datei | Verhalten bei vorhandenem Ziel | Fingerprint (24 Hex) |
|---|---|---|---|
| `Node::move` | `lib/private/Files/Node/Node.php` 460 / 461 | arbeitet auf dem im Objekt gespeicherten Pfad `$this->path` (nicht auf der File-ID); prüft nur Elternordner und Erstellrecht; ruft `View::rename($this->path, $targetPath)`; setzt danach `$this->path` | `7d8738995b84ade8522caf68` |
| `View::rename` | `lib/private/Files/View.php` 732 / 731 | lehnt nur „Ordner in eigenen Unterordner“ ab; `$exists = file_exists($target)` (755 / 754) dient **nur der Hook-Auslösung**; erwirbt shared-Sperren auf Quelle und Ziel (samt Eltern), löst `preRename`-Hooks aus, wandelt in **exklusive** Sperren, ruft dann `$storage1->rename(...)` (825 / 824) bzw. `moveFromStorage` (834 / 833) | `aa5e979cf0a473ef3a1f3e78` |
| `Local::rename` | `lib/private/Files/Storage/Local.php` 354 / 355 | **`if ($this->file_exists($target)) { if is_dir → rmdir($target); elseif is_file → unlink($target); }`** (375–380 / 376–381), danach PHP-`rename()` – das Überschreiben ist beabsichtigt | `2e3879210acb41d5c195da79` |
| `Common::rename` (Basisklasse anderer Storages) | `Storage/Common.php` 210 | `$this->remove($target)`, dann Kopie + Löschen der Quelle | `f7c75c33cbaa593f1da2f698` |
| `Files_Trashbin\Storage` | `apps/files_trashbin/lib/Storage.php` | überschreibt nur `unlink`/`rmdir`/`moveFromStorage`, **nicht `rename`**: ein beim Move ersetztes Ziel läuft am Papierkorb vorbei | `7b17d521ab298e80fccfad62` (`unlink`) |
| `Folder::newFile` → `View::file_put_contents` | `Node/Folder.php` 171 / 172, `View.php` 620 / 619 | schreibt über `fopen(…, 'w')` – ein vorhandener Pfad wird überschrieben | `3b49482d06e20746a9e48ba4`, `5ca309678102cba46c3de54e` |
| WebDAV `MOVE` | `3rdparty/sabre/dav/.../CorePlugin.php::httpMove` 586–629; `apps/dav/.../Directory.php::moveInto` 397 / 398 | `getCopyAndMoveInfo` prüft **einmal** den `Overwrite`-Header (412, wenn das Ziel *jetzt* existiert und `Overwrite: F`); existiert das Ziel und ist Überschreiben erlaubt, löscht Sabre es zuerst per `tree->delete` (**Papierkorb**), dann `tree->move`. Zwischen Prüfung und `View::rename` wird keine Sperre gehalten | `f5b5cb145de1e43ee5bbb223`, `6a0629ff7102d815ba18ba71` |
| Sperren | `Lock/MemcacheLockingProvider.php::acquireLock/changeLock` | shared = Zähler `inc`, exklusiv = `cas(0 → 'exclusive')`, shared→exklusiv nur bei **genau einem** Halter; sofortige `LockedException`, kein Warten; TTL ≈ 1 h | `400892e39e65698a69423433`, `6efdd3868f5915c603dae822` |
| Ereignisquelle | `Node/HookConnector.php` (`postRename` u. a.) | Node-Events entstehen aus den `OC_Filesystem`-Hooks der `View`-Ebene | `408f5da6a6423055f83aafc0` |

Alle 16 Fingerprints sind in 34.0.4 und 35.0.0 gleich (Datei `results/nc3x/method-fingerprints.json`, erzeugt mit `occ po-spike:fingerprint`). Daraus folgt: **Der Overwrite im Move-Pfad ist Absicht der Implementierung, kein Zufallsverhalten.**

### 3.2 Deterministische Befunde (E)

| Fall | Beobachtung | Ergebnis |
|---|---|---|
| A01 | Move/Rename im selben Storage ohne Ziel: Inhalt unverändert, Quelle weg, genau eine Datei | PASS |
| A02 | Ziel existiert, **gleicher** Inhalt: `Node::move` wirft keine Ausnahme; die File-ID des alten Ziels existiert danach nicht mehr; kein Papierkorbeintrag | FAIL (Gegenbeispiel) |
| A03 | Ziel existiert, **anderer** Inhalt: Ziel wird still ersetzt, alter Inhalt unwiederbringlich (kein Papierkorbeintrag, keine Version) | FAIL (Gegenbeispiel) |
| A04 | Datei auf Pfad eines **nicht leeren Ordners**: der Ordner samt zwei Dateien verschwindet, die Datei steht an seiner Stelle; Ordner auf vorhandene Datei: die Datei wird durch den Ordner ersetzt | FAIL (Gegenbeispiel) |
| A09 | `nodeExists()` = false, dann legt ein anderer Prozess das Ziel an, dann `Node::move`: Move gelingt, Inhalt des anderen Prozesses ist ersetzt – ein Vorab-Check schützt nicht | FAIL (Gegenbeispiel) |
| A10 | `Folder::newFile()` auf vorhandenem Pfad (mit und ohne Inhalt): ersetzt den Inhalt, keine Ausnahme – als Reservierungsprimitive ungeeignet | FAIL (Gegenbeispiel) |
| A11 | WebDAV `MOVE` mit `Overwrite: F` auf vorhandenes Ziel: **412**, Ziel und Quelle unverändert. Kontrolle: ohne `F` **204**, das alte Ziel liegt danach im **Papierkorb** | PASS |
| A07 | Zielordner fehlt → `NotFoundException`; Name mit `\` wird als Pfadtrenner gelesen (→ `NotFoundException`); Name > 250 Zeichen und `.htaccess` → `NotPermittedException`; gelöschte Quelle → `NotPermittedException`; Name mit Leerzeichen am Ende wird **akzeptiert**. Bestand jeweils unverändert | PASS |
| A16 | Fehler in einem **Before**-Listener (`BeforeNodeRenamedEvent`, `AbortedEventException`): Move bricht ab, Datei unverändert. Fehler in einem **After**-Listener (`NodeRenamedEvent`): wird **nicht** an den Aufrufer gemeldet, Move gilt als erfolgreich, Datei liegt am Ziel | PASS |

Zu A16: Ein Fehler in einem After-Listener einer fremden App bleibt für den Aufrufer unsichtbar; umgekehrt kann ein **als gescheitert gemeldeter** Aufruf seine Wirkung hinterlassen (§3.3). Beide Richtungen stützen den Grundsatz, dass Fehlerrückgaben nie als „nicht ausgeführt“ gelesen werden dürfen (`job-processing.md` §2 T4/T5, §6.3.3).

### 3.3 Konkurrenz am Zielpfad (E, statistisch; je zwei Prozesse, Startversatz ±600 ms)

Kriterium (zeitunabhängig): *Mover erfolgreich ∧ zweiter Akteur erfolgreich ∧ Endinhalt am Ziel = Inhalt des Movers* ⇒ der Move hat einen zuvor erfolgreich geschriebenen Inhalt ersetzt (Spalte „Move ersetzt erfolgreich geschriebenen Inhalt“). „Zweiter ersetzt Ergebnis des Movers“ ist die umgekehrte Reihenfolge (der zweite Akteur hat später geschrieben) und zählt nicht als Verletzung des Movers.

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

Lesehilfe und Schlüsse:

* **`Node::move` (A20–A22):** Verletzungen treten in beiden Versionen in jeder Gegenspieler-Konstellation auf. Das ist erwartungsgemäß, denn ein Move gegen ein vorhandenes Ziel überschreibt immer (§3.2); die Race-Tests fanden **keine** Konstellation, in der Nextcloud ein bereits fertig geschriebenes Ziel schützt.
* **WebDAV `MOVE` mit `Overwrite: F` (A23–A26):** Die Stichproben zeigen kein erzwungenes Fenster; die **deterministische** Instrumentierung A13 (Sabre-`beforeMove` angehalten, Ziel danach angelegt) zeigt jedoch: Das nach dem Overwrite-Check entstehende Ziel wird ersetzt (MOVE antwortet 201), ohne Papierkorbeintrag. Die statistischen Tests treffen dieses nur Millisekunden große Fenster nicht zuverlässig – **ein nicht reproduzierter Fehler ist hier nicht ausgeschlossen, sondern durch A13 widerlegt**.
* **„Fehler gemeldet, Wirkung vorhanden“:** Ein WebDAV-`PUT`, der mit **423** (`FileLocked`, „existing lock on file: none“) antwortete, hat dennoch seinen Inhalt am Ziel hinterlassen (A21, A24, A25, A28; Spalte „Zweiter“). Beobachtet bei gleichzeitigem Sperrkonflikt mit einem scheiternden Move (in A24/A25 in **jedem** Versuch, in dem beide Akteure Fehler meldeten, 50 bzw. 48 von 150). Auffällig ist die Meldung „existing lock on file: none“: Der Konflikt war beim Auslesen schon wieder aufgelöst; die Ursache wurde nicht weiter verfolgt (**Vermutung**, nicht belegt: ein Sperrfehler tritt erst nach dem Schreiben auf). **Jede Fehlerantwort muss deshalb als „Ausgang unbekannt“ behandelt werden.**
* **Sperren stoppen gleichzeitige Zugriffe meist, entscheiden aber nichts:** Bei zeitgleichem Start scheitern oft **beide** Akteure an der Sperre (Spalte „beide gescheitert“); es gewinnt nicht der erste, sondern es scheitern häufig beide (Livelock-ähnlich, kein Datenverlust).

### 3.4 Instrumentierte Fensteruntersuchung und Prototyp „geschützter Move“ (E, interne API)

* **A12 (instrumentiert):** Hält man `Node::move` innerhalb des gesperrten Bereichs von `View::rename` an (unmittelbar vor `Local::rename`), werden mit aktivem Dateisperr-Dienst alle Konkurrenten abgewiesen: `Folder::newFile` → `LockedException`, WebDAV `PUT` → **423**, WebDAV `MOVE` → **500** (`LockedException` wird nicht auf 423 abgebildet). **Mit `filelocking.enabled=false`** gelingen alle drei Konkurrenzzugriffe, und der angehaltene Move läuft danach durch (Ergebnis ok); dass er dabei das zwischenzeitlich Angelegte ersetzt, ist hier nicht einzeln geprüft, folgt aber aus §3.2 und A15. Das Fenster *innerhalb* von `View::rename` ist somit durch die Sperren geschlossen, das Fenster *davor* (App-Prüfung, Sabre-Check) nicht.
* **Prototyp „geschützter Move“ (A14, A15, A27–A29):** Ein Storage-Wrapper (`OC\Files\Storage\Wrapper\Wrapper`, interne API) prüft **innerhalb** des von `View::rename` gesperrten Bereichs `file_exists($target)` und lehnt ab, wenn das Flag `NoOverwriteGuard::$active` (prozesslokal, nur für App-Moves gesetzt) aktiv ist. Ergebnis: vorhandenes Ziel → `NotPermittedException`, Ziel und Quelle unverändert (A14 PASS); während des angehaltenen Moves entstehende Ziele werden durch die Sperren abgewiesen (A14 PASS); **ohne** aktive Dateisperren entsteht das Fenster wieder und das Ziel wird ersetzt (A15 FAIL, erwartet – bestätigt die Abhängigkeit von den Sperren); in den Konkurrenztests A27–A29 trat keine Verletzung auf (endliche Stichprobe, siehe Tabelle).
* **Bewertung:** Das ist **kein** Nachweis einer allgemeinen Garantie und **keine öffentliche API**: Der Wrapper hängt an `OC\…`-Klassen, an der Wrapper-Reihenfolge (hier `files_lock` und `files_trashbin` sind ebenfalls Wrapper), an wirksamen Dateisperren (Redis-Provider getestet, DB-Provider ungetestet) und er wirkt nur für Moves aus dem Prozess, der das Flag setzt. Als Option siehe OQ-073.

### 3.5 Verspätete Worker, veraltete Knoten (SP-A5, SP-I) (E)

* **A06:** Zwei Worker laden denselben Knoten. Worker 1 verschiebt. Worker 2 (veralteter Knoten, Quellpfad leer) scheitert mit `NotPermittedException` – gut. **Liegt am alten Pfad inzwischen eine andere Datei, verschiebt Worker 2 diese fremde Datei** (FAIL, Gegenbeispiel). Ursache (Q): `Node::move` verwendet den gespeicherten Pfad, nicht die File-ID.
* **A08 (SP-I):** Wurde der Knoten zwischenzeitlich per WebDAV umbenannt und der alte Pfad ist frei, scheitert `Node::move` mit `NotPermittedException`; `Folder::getFirstNodeById()` liefert den aktuellen Pfad (Grundlage der Revalidierung). PASS.
* **A30 (Konkurrenz an der Quelle, 150/150 Versuche je Version):** App-`Node::move` gegen gleichzeitige Client-Umbenennung (`Overwrite: F`) derselben Datei: NC 34 – App-Move gewann 119×, Client-Rename gewann 26×, beide scheiterten an Sperren 5× (Datei blieb an der Quelle); NC 35 – 116× / 27× / 7×. **Inhaltsverlust 0/0, Verdopplung 0/0, doppelter Erfolgsbericht 0/0** – die Datei lag stets genau einmal entweder am App-Ziel oder am Client-Namen (endliche Stichprobe, kein Beweis).
* **Folgerung:** `move_source_by_path` ist **keine** Eigenschaft von `Node::move`. Die App muss unmittelbar vor dem Aufruf über `getFirstNodeById(file_id)` auflösen und Pfad **und** File-ID mit der Erwartung vergleichen. Das schließt nicht das Restfenster zwischen Auflösung und Aufruf (A30 quantifiziert es als Stichprobe).

### 3.6 Antwort auf die zentrale Sicherheitsfrage

> *Kann für die getestete öffentliche Nextcloud-API zuverlässig nachgewiesen werden, dass kein existierender Zielinhalt überschrieben wird?*

**Nein – und für `Node::move()` ist das Gegenteil nachgewiesen.**

1. **`Node::move()`** überschreibt ein vorhandenes Ziel immer, auch Ordner mit Inhalt, ohne Papierkorb (E: A02–A04, A09, A20–A22; Q: `Local::rename` 375–380). Der Vertrag (V) macht keine Zusage.
2. **WebDAV `MOVE` mit `Overwrite: F`** lehnt ein *zum Prüfzeitpunkt* vorhandenes Ziel ab (E: A11), prüft aber nicht atomar mit dem Rename (E: A13 deterministisch; Q: `httpMove`). Es ist als Sicherheitsmechanismus **unzureichend**, auch wenn die statistischen Tests es nicht treffen.
3. **`Folder::newFile()`** überschreibt ebenfalls (E: A10) und taugt nicht zur Reservierung.
4. **Dateisperren** schließen das Fenster *innerhalb* von `View::rename`, lassen sich aber nicht mit dem eigenen Move kombinieren (§5, C05) und existieren für nicht angelegte Pfade nur über einen internen Schlüssel (C04).
5. **Belegt ist nur** eine Option über **interne** API (Wrapper-Prototyp, §3.4), mit Abhängigkeit von wirksamen Dateisperren; sie ist weder öffentlich noch bewiesen.

Folge nach dem Fail-closed-Grundsatz (`DEC-ARC-010`): Für **jedes getestete Tupel gilt `move_no_overwrite = false`** für die öffentlichen Mechanismen; ohne Entscheidung zu OQ-073 verbleibt die App im Modus „Nur Indexierung und Analyse“. Ein Race-Test, der in der Stichprobe bestanden wird, ändert das nicht.

### 3.7 Namensvarianten (SP-A4) (E)

* **Unicode:** Ein Move auf den **NFD**-Namen `e` + U+0301 (`é.jpg`) ersetzte die vorhandene **NFC**-benannte Datei `é.jpg` (U+00E9): Der Zielname wurde offenbar auf NFC normalisiert (im Ergebnis existiert nur noch der NFC-Name, `other.jpg` ist weg); die zwei Namensvarianten fallen zusammen (A05, FAIL, Gegenbeispiel; Mechanismus nicht im Quelltext nachverfolgt). Reservierungs- und Kollisionsschlüssel müssen normalisieren (OQ-079).
* **Groß-/Kleinschreibung:** `Case.JPG` → `case.jpg` gelingt, danach existiert genau eine Variante (A05); das Dateisystem war case-sensitiv. Ob `a.jpg` und `A.jpg` nebeneinander existieren dürfen und wie ein Move auf die jeweils andere Schreibweise reagiert, wurde **nicht** geprüft; ebenso das Verhalten auf case-insensitiven Dateisystemen (**U**).
* **Quelle = Ziel:** `Node::move` auf den eigenen Pfad scheitert mit `LockedException` („2 shared locks“) – am eigenen Sperrverhalten, nicht an einer Prüfung (A05); es geht nichts verloren.

## 4. Spike B – File-IDs und Storage-Referenzen (SP-B, SP-C) (E)

### 4.1 Wann bleibt die File-ID stabil?

| Operation | File-ID | Fall |
|---|---|---|
| Rename im selben Ordner (`Node::move`) | **bleibt** | B01 |
| Move in anderen Ordner; Ordner-Move samt Kindern | **bleibt** (auch alle Kind-IDs) | B02 |
| WebDAV `MOVE` (Rename, anderer Ordner) | **bleibt** | B03, A08 |
| Copy (`Node::copy`, WebDAV `COPY`) | **neu** (Quelle unverändert) | B04 |
| Löschen in den Papierkorb | **bleibt** (Papierkorbeintrag trägt dieselbe ID; per `IRootFolder::getById` unter `/<uid>/files_trashbin/files/<name>.d<ts>` erreichbar) | B05, D01 |
| Wiederherstellung aus dem Papierkorb | **bleibt**, Pfad wie zuvor | B05, E04 |
| Inhaltsänderung (`putContent`, `PUT`) | **bleibt**; ETag/MTime ändern sich bei Änderung in derselben Sekunde nicht (§4.3) | B06 |
| Move Heim-Storage → beschreibbarer Freigabe-Storage eines anderen Benutzers | **bleibt** (Q: `Updater::renameFromStorage` → `Cache::moveFromCache` ändert nur Storage/Pfad der Cache-Zeile; die Datei ist danach im Ordner des Eigentümers bob sichtbar) | B07 |
| Überschreibender Move auf ein Ziel | ID des **alten Ziels erlischt** | A02 |

### 4.2 Storage-Referenzen (nur Heim-Storage)

`storage_numeric_id` und `mount_point` (`/alice/`, `HomeMountPoint`) bleiben bei Rename/Move/Trash/Restore gleich (B01, B05); `internal_path` (`files/spike/…`) und `parent_id` ändern sich; der **ETag und die MTime der Datei selbst ändern sich bei Rename/Move nicht** (B01; die ETags der Ordner wurden nicht geprüft). Die erste Klasse der Wrapperkette ist `OCA\Files_Trashbin\Storage`; die zugrunde liegende Storage-Klasse ist `OC\Files\Storage\Home` – beides muss als **Profil** erfasst werden (§9.3), nicht nur ein Klassenname.

### 4.3 Signaturlücke (B06, F05)

Eine Inhaltsänderung innerhalb **derselben Sekunde** mit **gleicher Größe** lässt Größe, MTime und ETag unverändert (B06, FAIL, Gegenbeispiel; Inhalt hatte sich nachweislich geändert). Nach 2 s ändert sich die Signatur. Ein Hash-Cache über (Größe, MTime, ETag) (`DEC-JOB-005`) und die Fortsetzung eines Hashes (F05) können so eine Änderung übersehen (OQ-075).

### 4.4 Storage-Wechsel (SP-C)

B07 widerlegt die Annahme aus `nextcloud-integration.md` §2.2 („Move über Storage-Grenzen: Kopie + Löschen, neue ID“) **für diese Kombination** (Home-Storage → Freigabe-Storage): die ID bleibt. Das ändert die Beschränkung auf den Heim-Storage (REQ-SRC-06) nicht (Eigentum, Berechtigungen, Papierkorbzuständigkeit wechseln mit). Andere Kombinationen (Objektspeicher, externe Speicher, verschlüsselte Storages) sind **U**.

## 5. Spike C – File-Locking (SP-E) (E)

| Frage | Befund | Fall |
|---|---|---|
| Verträglichkeit | shared+shared erlaubt; jede Kombination mit exklusiv wird **sofort** mit `LockedException` abgewiesen („existing lock on file: exclusive“ / „N shared locks“); **kein Warten, kein Timeout** | C01 |
| Wirkung auf WebDAV-Clients | exklusive Sperre: GET/PUT/DELETE → **423**, `MOVE` → **500** (`LockedException`, nicht 423); shared: GET 200, PUT/DELETE 423, MOVE 500. Auch `Node::move`/`Node::delete` aus anderen Prozessen scheitern | C02 |
| Zielordner sperren | exklusive Ordnersperre blockiert Anlage und Move von Kindern (Node-API und PUT); shared-Ordnersperre blockiert `newFile`, nicht aber Move/PUT hinein | C03 |
| Nicht existierender Zielpfad | `Node::lock` ist nicht möglich (`NotFoundException`, kein Knoten). Über `ILockingProvider` mit dem internen Schlüssel `files/md5(storageId::internalPath)` lässt sich ein nicht existierender Pfad sperren; PUT → 423, MOVE → 500, `newFile` aus anderem Prozess → `LockedException` | C04 |
| **Eigener Move trotz eigener Sperre** | **nicht möglich:** auch eine eigene *shared*-Sperre auf der Quelle lässt `Node::move` scheitern (`LockedException`, „2 shared locks“), weil `View::rename` shared→exklusiv nur bei genau einem Halter wandelt (Q: `MemcacheLockingProvider::changeLock`). Eine eigene exklusive Sperre blockiert ebenso. Es gibt keinen Wiedereintritt | C05 (FAIL) |
| Kooperativer Mutex zwischen zwei Workern | von zwei gleichzeitigen Erwerbern gewinnt höchstens einer (endliche Stichprobe, 37 Versuche) | C06 |
| Prozessabbruch (`kill -9`) | die Sperre bleibt bestehen; **TTL 3598 s** (≈ 1 h, Standard `filelocking.ttl` nicht gesetzt: `max(3600, max_execution_time)`); ein späterer Erwerb scheitert mit `LockedException` bis zum Ablauf | C07 |
| Freigabe | `unlock` ohne gehaltene Sperre erzeugt keinen Fehler (V: „will not give any sort of error“) | C01 |

**Kooperative Anwendungssperre vs. plattformweite Dateisperre.** Die Nextcloud-Sperren sind *obligatorisch für alle Nextcloud-Codepfade* (Node-API, WebDAV, Apps), aber **kooperativ im Sinne des Betriebssystems**: Sie schützen nicht gegen Zugriffe, die an Nextcloud vorbeigehen (direkter Dateizugriff im Datadir, `occ files:scan`-Effekte, andere Prozesse ohne Nextcloud-Locking), und sie hängen von der Konfiguration ab (`filelocking.enabled`, Provider; ohne Sperren: A12/A15). Sie sind **nicht** komposierbar mit `Node::move` (C05); eine App kann sie deshalb nicht als „Sperre, dann Move“ einsetzen. Als reiner Mutex zwischen eigenen Workern sind sie funktionsfähig (C06), aber nicht nötig, wenn die Datenbank-Lease den Anspruch trägt.

## 6. Spike D – Papierkorb (SP-J) (E)

* **`Node::delete()`** verschiebt bei aktivem `files_trashbin` in den Papierkorb – im CLI-Prozess wie bei WebDAV `DELETE` (D01, D05, E04). Der Papierkorbknoten liegt unter `/<uid>/files_trashbin/files/<name>.d<Löschzeit>`, behält die **File-ID** und ist per `IRootFolder::getById` (öffentliche Methode, interne Pfadkonvention) und per WebDAV (`remote.php/dav/trashbin/<uid>/trash`) auffindbar; der Inhalt ist per `fopen` bzw. GET lesbar und hashgleich (D01).
* **Nachweis einer Papierkorboperation** (D02): Pre-Delete-Hash beider Exemplare (neu gehasht) → `Node::delete()` → Beobachtung: (1) Duplikat nicht mehr am Originalpfad, (2) File-ID des Duplikats löst auf einen Knoten unter `files_trashbin/files/` auf, **SHA-256 gleich dem Pre-Delete-Hash**, (3) genau ein WebDAV-Papierkorbeintrag mit derselben File-ID, (4) kanonische Datei unverändert (ID, SHA-256, ETag). Alles PASS in beiden Versionen.
* **Restore** (D04, B05): per WebDAV `MOVE` nach `trashbin/<uid>/restore/<Eintrag>`; File-ID und Inhalt bleiben. **Ist der Originalpfad inzwischen belegt, wird die belegende Datei nicht überschrieben**: der Restore legt `x (restored).jpg` an (ID des Wiederhergestellten = ursprüngliche ID).
* **Ordner löschen** (D05): ein Papierkorbeintrag für den Ordner; die Kinder sind per File-ID unter `files_trashbin/files/<Ordner>.d<ts>/…` erreichbar.
* **Eintragsname und Kollision** (D03): Werden zwei Dateien **am selben Pfad innerhalb derselben Sekunde** gelöscht (A löschen, B am selben Pfad neu anlegen, B löschen), existiert danach nur **ein** Papierkorbeintrag (`same.jpg.d<Sekundenzeitstempel>`); er trägt die File-ID von B. Der Inhalt von A ist weder per File-ID (`getById` liefert nichts) noch über WebDAV auffindbar – **A ist unwiederbringlich verloren** (D03, FAIL, Gegenbeispiel, in 34 und 35 gleich; der Mechanismus wurde im Quelltext nicht weiter verfolgt). Mit 2 s Abstand entstehen zwei Einträge, beide Inhalte sind per File-ID erreichbar.
* **`trashbin-original-location`** kommt in zwei Schreibweisen vor: mit führendem `/` nach `Node::delete()`, **ohne** nach dem Löschen eines überschriebenen Ziels durch WebDAV `MOVE`/`PUT` – Vergleiche müssen normalisieren.
* **Überschriebene Ziele:** Ein von `Node::move` ersetztes Ziel erscheint **nicht** im Papierkorb (A02/A03); ein von WebDAV `MOVE` (Overwrite: T) ersetztes **schon** (A11). Die Wiederherstellbarkeit hängt also vom Weg ab.
* **Papierkorb deaktiviert** (D06): nur erkannt (App aktiv, Trash-Wrapper in der Kette), das endgültige Löschen bei deaktiviertem Papierkorb wurde **bewusst nicht ausgeführt** (Auftrag 5: keine dauerhaft irreversible Löschung im Harness); es folgt nur aus dem Quelltext (kein Trash-Wrapper, `Local::unlink` löscht direkt) – **Q, nicht E**.
* **Ereignisse beim Löschen** (E01/E02): `BeforeNodeDeletedEvent`, `MoveToTrashEvent`, `NodeDeletedEvent` je genau einmal; `NodeDeletedEvent` trägt ID und Größe.
* Nicht geprüft: Retention (`trashbin_retention_obligation`), Papierkorbgröße/Quota, Restore anderer Benutzer, `ITrashManager` (Klasse 3).

## 7. Spike E – Dateievents

Aufgezeichnet wurden alle Event-Klassen unter `OCP\Files\Events`, `OCP\Files\Cache`, `OCA\Files_Trashbin\Events`, `OCA\Files_Versions\Events` in **beiden Prozessarten** (CLI-Prozess der App-Operation, Apache-Prozess der WebDAV-Aktion). Die Reihenfolge (ohne `CacheEntry*`) in beiden Versionen identisch:

| Aktion | Ereignisfolge (Prozess) | Mehrfachzustellung |
|---|---|---|
| `Folder::newFile` (CLI) / WebDAV `PUT` neu (Apache) / **Chunked Upload v2** (Apache) | `BeforeNodeCreated` → `BeforeNodeWritten` → `VersionCreated` → `NodeCreated` → `NodeWritten` | je Klasse genau **1×**; `NodeCreated` und `NodeWritten` tragen die **vollständige Größe**; beim Chunked Upload entstehen für Chunk-Phase und Upload-Bereich **keine** Ereignisse, die Folge erscheint einmal beim abschließenden `MOVE` |
| `putContent` (CLI) / `PUT` überschreibend | `CreateVersion` → `BeforeNodeWritten` → `VersionCreated` → `NodeWritten` | kein `NodeCreated` |
| Rename / Move (CLI und WebDAV `MOVE`) | `BeforeNodeRenamed` → `NodeRenamed` | genau 1×; **Quellknoten im `NodeRenamedEvent`: `getId()`/`getSize()` werfen `NotFoundException`**, der Zielknoten trägt die File-ID; im `BeforeNodeRenamedEvent` trägt die Quelle die ID, das Ziel nicht |
| Ordner-Move | genau ein `NodeRenamed` für den **Ordner**; **kein Ereignis je Kind** (E06) | – |
| Copy | `BeforeNodeCopied` → `BeforeNodeCreated` → `BeforeNodeWritten` → `NodeCopied` (Quell- und Ziel-ID) → `VersionCreated` → `NodeCreated` → `NodeWritten` | 1× je Klasse |
| Delete (Papierkorb) | `BeforeNodeDeleted` → `MoveToTrash` → `NodeDeleted` | 1× |
| Restore (WebDAV) | `BeforeNodeRestored` → `BeforeNodeTouched` → `NodeTouched` → `NodeRestored` (Quelle: Papierkorbpfad, ID nicht lesbar; Ziel mit ID) | kein `NodeRenamed`, kein `NodeCreated` |
| `touch` | `BeforeNodeTouched` → `NodeTouched` | 1× |
| MKCOL | `BeforeNodeCreated` → `BeforeNodeWritten` → `NodeCreated` → `NodeWritten` (Größe 0) | 1× |

Weitere Befunde:

* **`CacheEntryUpdatedEvent` & Co. sind zahlreich und ungeeignet als Auslöser** (z. B. 12–24 Stück für ein einziges `putContent`, 28–56 für einen WebDAV-Move in einen anderen Ordner, je nach Version und Baumtiefe).
* **Identitätsmerkmale:** Zuverlässig sind Pfad und File-ID des **Zielknotens** der After-Ereignisse. Nach Rename/Move/Restore ist die Quell-ID nicht mehr lesbar; Rückschlüsse auf den alten Pfad laufen über den Quellpfad des Ereignisses.
* **Prozess:** Ereignisse feuern synchron im Prozess der Aktion; Listener einer App laufen daher im **Webserver-Prozess** (WebDAV) bzw. im CLI-Prozess (App-Operation, Cron). Eigene Operationen sind über Pfad/Ziel-ID und Journal von Benutzeraktionen zu unterscheiden (kein Prozessflag über Prozessgrenzen).
* **Mehrfachzustellung:** In keiner der getesteten Aktionen wurde dasselbe Hauptereignis mehrfach ausgeliefert. Idempotenz bleibt dennoch nötig (Wiederholung durch Clients, Retry, mehrere Aktionen); bei Fehlern in After-Listenern (A16) geht ein Ereignis ggf. nur dem fehlerhaften Listener verloren.
* **Ordner-Rename (E06) ist das Erkennungsproblem für `group_follow`:** Verschiebt der Benutzer einen Ordner, gibt es genau ein Ereignis; die Pfade der Kinder müssen per Teilbaum-Listing und File-ID abgeleitet werden (OQ-080).
* **Nicht getestet:** `occ files:scan` nach Änderungen außerhalb von Nextcloud (würde einen direkten Datadir-Eingriff voraussetzen, der nach den Isolationsregeln ausgeschlossen ist), Versions-Restore, Sharing-Ereignisse, Desktop-/Mobile-Clients (nur deren WebDAV-/Chunked-Protokoll nachgestellt).

## 8. Spike F – HashContext (SP-H)

### 8.1 Funktionsprüfung (E)

* **F01:** `serialize()` eines SHA-256-`HashContext` und Fortsetzung nach `unserialize()` liefert an 16 Offsets (u. a. 0, 1, 55, 56, 63, 64, 65, 4096, 1 MiB±1, Dateiende) den korrekten Digest; serialisierter Zustand ≈ 310–313 Bytes (PHP 8.5.10/8.5.11).
* **F02 (Prozessneustart):** Zustand in Datei, Fortsetzung in einem **neuen Prozess** (`occ`), Datei über die Node-API gestreamt: korrekter Digest.
* **F06:** `unserialize($s, ['allowed_classes' => ['HashContext']])` weist fremde Klassen ab (`__PHP_Incomplete_Class`); der **Algorithmus ist Teil des Zustands** (ein MD5-Zustand liefert einen MD5-Digest) und gehört in den Umschlag, damit nie ein Ersatz-Hash als SHA-256 ausgegeben wird.
* **Anmerkung zur Harness:** Ein eigenständiges PHP-Skript, das `lib/base.php` lädt und eine Datei über `Node::fopen` hasht, endete in PHP 8.5.11 mit „zend_mm_heap corrupted“ beim Beenden (Hash korrekt, Exit 134); unter `occ` tritt das nicht auf. Es betrifft nicht die Hash-Fragestellung, ist aber ein Hinweis, Hintergrundjobs ausschließlich über Nextcloud-Mechanismen laufen zu lassen.

### 8.2 Manipulierter Zustand, falscher Offset, geänderte Datei (E)

* **F03 (alle Einzelbitfehler, 310 Bytes = 2480 Bits):** 1704 abgelehnt, 385 angenommen mit korrektem Digest (wirkungslos), **391 angenommen mit stillschweigend falschem Digest**. PHP erkennt veränderte Zustände nicht zuverlässig. Ein Umschlag mit SHA-256-Prüfsumme der Nutzlast erkannte **alle** 2480 Fälle (Vorschlag aus `job-processing.md` §3.6 bestätigt; schützt nur gegen Verfälschung, nicht gegen gefälschte Umschläge).
* **F04:** Ein falscher Fortsetzungs-Offset (±1, 0, hinter dem Ende) liefert **keinen Fehler**, aber einen falschen Digest. Offset im Umschlag und End-Gegenprüfung sind nötig.
* **F05 (Datei ändert sich während der Unterbrechung):** Anhängen ändert die Größe → Signatur erkennt es (und der Digest ist hier sogar richtig, weil nur angehängt wurde). Gleiche Größe, Änderung **vor** dem Offset, **gleiche Sekunde** → Signatur **unverändert**, Fortsetzung ergibt einen **falschen** Digest ohne jedes Warnsignal (FAIL, Gegenbeispiel). Nach 2 s ändert sich die Signatur und wird erkannt. Gegenmaßnahme: MTime-Alter-Regel (OQ-075) oder Neuberechnung.

### 8.3 Portabilität zwischen PHP-Versionen (E, endliche Stichprobe)

**F10:** SHA-256-Zustände, erzeugt in 7 Umgebungen – PHP **8.2.34**, **8.3.14** (= PO-Referenz), 8.3.35, 8.4.26, 8.5.11 (alle `-cli-alpine`, musl) sowie den `app`-Containern von Nextcloud 34 (PHP 8.5.11, glibc) und 35 (PHP 8.5.10) –, wurden in **jeder** Umgebung fortgesetzt (3 Szenarien mit Offsets 0, 64, 333333): **147 von 147 Fortsetzungen korrekt**. Das ist eine Stichprobe über wenige Versionen und einen Algorithmus: **keine Portabilitätsgarantie**. Der Umschlag mit PHP-Version, Algorithmus und Offset sowie die Neuberechnung bei Abweichung (`job-processing.md` §3.6) bleiben Pflicht.

### 8.4 Größenordnung (E, nur Orientierung)

F07: ca. 390 (NC 34) / 414 (NC 35) MiB/s SHA-256 über `Node::fopen` auf dem Entwicklungshost (48 MiB, warmer Cache, ausgelasteter Host). Keine Leistungszusage; Referenzmessung auf der PO-Installation steht aus (SP-F).

## 9. Architekturbezug

### 9.1 `group_follow` (Auftrag 5 §8.1)

*Vorgabe:* Eine vom Benutzer in der Sammlung durchgeführte Verschiebung einer eindeutig zugeordneten Gruppenkomponente soll nach Debounce die restlichen Gruppenkomponenten nachziehen dürfen – auch adoptierte Dateien –, ohne eine allgemeine automatische Reorganisation freizugeben.

Prüfung gegen die Plattformbefunde:

| Aspekt | Befund | Folge |
|---|---|---|
| Erkennung der Benutzeraktion | Web-UI und Clients erzeugen WebDAV-`MOVE`; genau ein `NodeRenamedEvent` im Apache-Prozess; Zielknoten trägt die File-ID (E02, E05) | Auslöser technisch verfügbar; Instanz über File-ID finden (B01–B03 stabil) |
| Eigene vs. fremde Aktion | Ereignisse tragen keine Herkunft | Abgleich mit dem Operationsjournal (Zielpfad + Operations-ID) |
| Ordner-Move | ein Ereignis für den Ordner, keine für Kinder (E06) | Teilbaumabgleich per Listing und File-ID; Gruppenkomponenten in verschobenen Ordnern sind keine einzelnen Aktionen |
| Ausführung | jede Nachzieh-Verschiebung ist ein `Node::move` → **überschreibt vorhandene Ziele** (§3) | `group_follow` ist nur in einer Betriebsart mit verifiziertem `move_no_overwrite` zulässig; sonst Prüffall. Bei adoptierten Dateien besonders kritisch: Zielordner enthalten Benutzerdateien mit beliebigen Namen (Kollisionen wahrscheinlicher als bei App-eigenen Ordnern) |
| Aktualität der Quelle | `Node::move` folgt dem Pfad (A06); der Benutzer ist gerade in der Sammlung aktiv (A30: Client-Rename vs. App-Move) | Revalidierung per `getFirstNodeById` unmittelbar vor dem Aufruf; **Ruhezeit** nach der letzten Benutzeraktion an der Komponente (OQ-070/076) |
| Reichweite | Autorisierung folgt aus der Benutzeraktion, nicht aus einer Reorganisation | Ziel ausschließlich der Ordner der bewegten Komponente, nur gleicher Storage (`getStorage()->getId()` gleich), keine Umbenennung der Komponenten, keine anderen Gruppen |
| „eindeutig zugeordnet“ | Begriff nicht operationalisiert | Vorschlag: Gruppenmitgliedschaft Evidenz Stufe 1 oder 3, Gruppe `active`, kein `ambiguous`/`conflict` (OQ-067/OQ-076) |

Ergebnis: Das Zielverhalten ist **plattformseitig nicht ausgeschlossen**, aber nur unter den Bedingungen von OQ-073 (sichere Nichtüberschreibung) und OQ-076 (Ruhezeit, Definition, Reichweite) umsetzbar. Es steht im Spannungsfeld zu `DEC-IDX-001` (adoptierte Dateien geschützt); die Ausnahme wäre als eigene, eng begrenzte Entscheidung zu formulieren (CONFLICT-017). **Keine bestehende Entscheidung wurde geändert.**

### 9.2 `trash`: Nachweis von Integrität und korrekter Ausführung (Auftrag 5 §8.2)

Die Datei ist am bisherigen Pfad nicht mehr vorhanden, der Nachweis erfolgt daher über die **File-ID** (E: D01, D02, D05):

1. **Vor** der Mutation (Pre-Delete, `job-processing.md` §6.4): beide Exemplare frisch hashen, `file_id` des Duplikats und der kanonischen Datei sowie Signaturen festhalten.
2. `Node::delete()` über das `FileGateway`; jedes Ergebnis (auch Ausnahme) ist „unbekannt“, bis beobachtet wurde.
3. **Beobachtung:** `IRootFolder::getById(file_id_des_Duplikats)` → (a) **Treffer unter `/<uid>/files/…` am Originalpfad** ⇒ nicht ausgeführt; (b) **Treffer unter `/<uid>/files_trashbin/files/…`** ⇒ im Papierkorb; Inhalt (Stream) **neu hashen und mit dem Pre-Delete-Hash vergleichen** ⇒ Integrität belegt; (c) **kein Treffer** ⇒ endgültig gelöscht, bereinigt oder anderer Weg ⇒ `needs_review`, nie Wiederholung. Zusätzlich als zweiter Beleg: WebDAV-Papierkorbliste (`fileid`, `trashbin-original-location`, `trashbin-deletion-time`).
4. Kanonische Datei: ID, SHA-256 (Neuhash) und ETag unverändert.
5. Der Papierkorbeintrag ist die Wiederherstellungsgrundlage: Restore überschreibt nichts (D04).

Grenzen: Die Pfadkonvention `files_trashbin/files/<name>.d<ts>` ist **intern** (kein OCP-Vertrag) und gehört hinter das `FileGateway` samt Versionstest; `ITrashManager` ist nicht nötig. Der Nachweis belegt den Zustand zum Beobachtungszeitpunkt, nicht die Aufbewahrungsdauer (Retention ungetestet); gleichzeitige Löschungen am selben Pfad innerhalb einer Sekunde haben Namenskollisionspotenzial (ein früherer Papierkorbeintrag desselben Pfades wird bei Löschung in derselben Sekunde ersetzt, D03). Bei deaktiviertem Papierkorb ist `trash_verified` false (D06), und eine Löschaktion wird verweigert (OQ-005).

### 9.3 Befüllung der Fail-closed-Fähigkeitsmatrix (Auftrag 5 §8.3) [Z]

**Problem:** Begrenzte Experimente dürfen keine allgemeine Garantie ableiten. **Vorschlag:**

1. **Profil statt Version.** Eine Fähigkeit gilt für ein *Profil*: (Nextcloud-Version, **Wrapperkette des Heim-Storages** in Reihenfolge, Basis-Storage-Klasse, Datenbank-Provider, Locking-Provider und `filelocking.enabled`, PHP-Hauptversion). Beispiel hier: `34.0.4 | Trashbin→FilesLock→Quota→Home | mysql | Memcache(Redis)+an | 8.5`. Ein anderes Profil erbt nichts. (Die Wrapperkette ist in `environment.json` erfasst; sie war in beiden getesteten Versionen gleich.)
2. **Fähigkeit nur als Konjunktion dreier Belege:**
   * **(a) Allowlist-Eintrag** aus einem Lauf der Spike-Harness (`ncspike test`) gegen genau dieses Profil: alle der Fähigkeit zugeordneten Fälle **PASS**, kein FAIL/INCONCLUSIVE (Zuordnung in `harness/report.py`, Tabelle „Abgeleitete Fähigkeiten“ im Report). Jeder FAIL widerlegt, INCONCLUSIVE oder fehlende Fälle ergeben „unbekannt = false“.
   * **(b) Implementierungsnachweis:** die **Methoden-Fingerprints** (Token-Hash ohne Leerraum/Kommentare, `occ po-spike:fingerprint`, hier 16 Methoden) der Laufzeitumgebung müssen mit den analysierten übereinstimmen. Das erlaubt es, ein Patch-Release mit identischen Methoden zu übernehmen und jede Abweichung als „neu zu prüfen“ zu erkennen – ohne Race-Tests als Beweis zu missbrauchen. Race-Tests sind nur Gegenprobe, nie Begründung.
   * **(c) Deterministischer Laufzeit-Selbsttest** in einem Scratch-Ordner im Heim-Storage des Benutzers (gleiche Storage-Klasse!), ausschließlich mit synthetischen Dateien: Teilmenge der deterministischen Fälle (z. B. Move auf vorhandenes Ziel, veralteter Knoten, Papierkorb-Rundlauf). Er erkennt Konfigurationsabweichungen (z. B. abgeschaltete Sperren, zusätzliche Wrapper), die kein CI-Lauf kennt. Nebenwirkungen und Auslöser (Admin, Erstlauf, Versionswechsel) sind zu klären (OQ-078).
3. **Verfall:** Änderung von Nextcloud-/App-Version, Wrapperkette, Locking-Konfiguration, PHP-Hauptversion oder eines Fingerprints ⇒ Fähigkeiten des Profils zurück auf false, bis (a)–(c) erneut bestehen.
4. **Keine Extrapolation:** Fähigkeiten werden je Profil geführt; „lokaler Heim-Storage“ ist ein Profilmerkmal, nicht eine Fähigkeitsklasse. Für nicht getestete Speicherarten (Objektspeicher, externe Speicher, Verschlüsselung) existiert kein Eintrag ⇒ Modus „Nur Indexierung und Analyse“.
5. **Mindestzahlen** (Wiederholungen der Race-Harness, Fenstergrößen) legt der PO fest (OQ-069/OQ-078); sie sind Gegenprobe, nicht Beweis.

**Anwendung auf die getesteten Profile:** siehe Report-Tabelle „Abgeleitete Fähigkeiten“ (`results/REPORT.md` §5). Zusammenfassung dieses Laufs für beide Profile:

| Fähigkeit | Bedeutung | NC 34 | NC 35 |
|---|---|---|---|
| `move_no_overwrite[Node::move]` | Node::move lehnt ein vorhandenes Ziel ab | false (widerlegt) | false (widerlegt) |
| `move_no_overwrite[WebDAV MOVE Overwrite:F]` | WebDAV MOVE mit Overwrite: F ist auch unter Konkurrenz sicher | false (widerlegt) | false (widerlegt) |
| `move_no_overwrite[geschützter Move, interne API]` | Prototyp: Überschreibschutz im gesperrten Storage-Bereich (setzt wirksame Dateisperren voraus) | Kandidat (alle Fälle PASS) | Kandidat (alle Fälle PASS) |
| *Gegenprobe:* geschützter Move ohne Dateisperren | **erwarteter** FAIL (A15) – bestätigt, dass die Sicherheit des Prototyps an wirksamen Dateisperren hängt | false (widerlegt) | false (widerlegt) |
| `move_source_by_path` | Move eines veralteten Knotens trifft nie eine andere Datei | false (widerlegt) | false (widerlegt) |
| `lock_target (öffentliche API)` | Sperre auf nicht existierenden Zielpfad, kompatibel mit eigenem Move | false (widerlegt) | false (widerlegt) |
| `trash_verified` | Löschen landet im Papierkorb; Inhalt/Identität über die File-ID belegbar | Kandidat (alle Fälle PASS) | Kandidat (alle Fälle PASS) |
| `trash_entry_unique_per_second` | zwei Löschungen desselben Pfades in einer Sekunde behalten beide Papierkorbeinträge | false (widerlegt) | false (widerlegt) |
| `trash_restore_no_overwrite` | Restore überschreibt eine inzwischen belegte Zieldatei nicht | Kandidat (alle Fälle PASS) | Kandidat (alle Fälle PASS) |
| `hash_resume (SHA-256)` | Fortsetzbarer SHA-256-Kontext mit Umschlag (inkl. PHP-Versionsmatrix F10) | Kandidat (alle Fälle PASS) | Kandidat (alle Fälle PASS) |
| `signature_change_detection` | Signatur aus Größe/MTime/ETag erkennt jede Inhaltsänderung | false (widerlegt) | false (widerlegt) |

## 10. Notwendige Korrekturen der Architekturannahmen (Vorschläge; nichts wurde geändert)

| # | Annahme (Dokument) | Befund | Vorschlag | Bezug |
|---|---|---|---|---|
| K1 | „`Node::move()` nach Existenzprüfung allein unzureichend; erst Spikes zeigen, ob `Node::move` selbst ein vorhandenes Ziel ablehnt“ (`job-processing.md` §6.3.2) | `Node::move` **überschreibt immer**, auch Ordner mit Inhalt, ohne Papierkorb; WebDAV `Overwrite: F` nicht atomar; `newFile` überschreibt | Tabelle in §6.3.2 mit E/Q-Belegen versehen; `move_no_overwrite = false` für alle öffentlichen Verfahren; Entscheidung OQ-073 | CONFLICT-018 |
| K2 | „P5: Sperren (Quellknoten und Zielpfad), dann Storage-Aufruf“ (`job-processing.md` §6.1) | Eigene Sperren blockieren den eigenen Move; Zielpfad nur über internen Schlüssel sperrbar | P5 streichen; Sperren nicht als Schutz der eigenen Mutation einplanen | CONFLICT-019, OQ-074 |
| K3 | „`lock_target`: Sperre auf noch nicht existierenden Zielpfad möglich und von Move respektiert“ (`job-processing.md` §6.3.1) | möglich nur über internen Schlüssel; wirkt auf Fremde, blockiert aber den eigenen Move | Fähigkeit streichen oder umformulieren („fremde Zugriffe abweisen“); nie Teil des Move-Schutzes | OQ-074 |
| K4 | „`move_source_by_path`: der Aufruf scheitert, wenn unter dem Quellpfad nicht mehr dieselbe Datei liegt“ | `Node::move` folgt dem Pfad: eine am alten Pfad liegende Fremddatei wird verschoben (A06) | Vor jedem Move per `getFirstNodeById` auflösen, Pfad und File-ID prüfen; Restfenster dokumentieren (A30); `move_source_by_path = false` | OQ-070, OQ-076 |
| K5 | „Move über Storage-Grenzen: Kopie und Löschen, neue ID“ (`nextcloud-integration.md` §2.2) | ID bleibt beim Move Heim→Freigabe (B07) | Aussage korrigieren; Beschränkung auf den Heim-Storage aus anderen Gründen beibehalten | CONFLICT-020 |
| K6 | „Hash-Cache über Signatur (Größe, MTime, ETag)“ (`DEC-JOB-005`, `job-processing.md` §3.6/§8) | Änderung in derselben Sekunde mit gleicher Größe bleibt unsichtbar (B06, F05) | Signaturgültigkeit an MTime-Alter koppeln oder neu hashen | CONFLICT-021, OQ-075 |
| K7 | „Ausnahme/Fehler ⇒ Mutation nicht ausgeführt“ (naive Lesart) | Fehler in After-Listenern bleiben unsichtbar, gescheiterte PUTs können Wirkung haben (A16, A21/A24/A25) | Grundsatz T4/T5 beibehalten; `FileGateway` bildet **alle** Fehler auf `error(unknown)` ab, außer vor dem Aufruf geprüften | OQ-083 |
| K8 | „Prüfung des Papierkorbeintrags nur über `ITrashManager` (Klasse 3) oder WebDAV“ (`nextcloud-integration.md` §2.4) | nicht nötig: `getById` + WebDAV genügen (D01, D02) | Nachweisverfahren §9.2 verwenden; Pfadkonvention kapseln | OQ-077 |
| K9 | „`NodeRenamedEvent` besitzt `getSource()` und `getTarget()`; damit lässt sich ein Rename abgleichen“ (`nextcloud-integration.md` §2.3) | bestätigt für **Pfade**; die Quell-ID/-Größe ist nicht lesbar (`NotFoundException`), ein Ordner-Move erzeugt kein Ereignis je Kind | Zielknoten-ID + Quellpfad verwenden; Teilbaumabgleich bei Ordnern | OQ-080 |
| K10 | „Dateinamen unnormalisiert wie geliefert speichern und vergleichen“ (Vorschlag zu OQ-057, `ns_key`) | NFD→NFC-Zusammenfall (A05) | Normalisierung (NFC) in Reservierungs- und Kollisionsschlüssel | OQ-079 |
| K11 | Implizit in `job-processing.md` §6.4 / `crash-recovery.md` S19: jede Löschung hinterlässt einen eigenen prüfbaren Papierkorbeintrag | Eintragsname = Name + Sekundenzeitstempel (ein früherer Papierkorbeintrag desselben Pfades wird bei Löschung in derselben Sekunde ersetzt, D03) | Papierkorb-Nachweis über File-ID statt über Namen; Kollisionsfall als `needs_review` | OQ-077 |
| K12 | „Stale Sperre eines toten Workers ⇒ temporärer Fehler (Backoff)“ (`job-processing.md` §6.3.4) | die verwaiste Sperre hält ≈ 1 h (TTL 3598 s, C07) | Backoff-Fenster der Recovery auf diese Größenordnung auslegen oder nach mehreren Versuchen `needs_review`; kein App-Eingriff in fremde Sperren | OQ-074 |

## 11. Ungeprüfte Storage-Klassen und fehlende Tests

**Geprüft:** ausschließlich der lokale Heim-Storage (`Home` über `Local`) im Docker-Named-Volume auf ext4 mit der genannten Wrapperkette; zusätzlich ein Move in einen Freigabe-Storage (B07). Die Fingerprints zeigen, dass die Move-/Sperr-/Papierkorb-Implementierung in 34.0.4 und 35.0.0 identisch ist, **nicht** dass andere Storage-Klassen sich gleich verhalten.

| Klasse | Stand | Warum relevant | Nötiger Test |
|---|---|---|---|
| Objektspeicher als Primärspeicher (S3/Swift) | **U** | `Common::rename` = `remove($target)` + Kopie + Löschen (Q); File-IDs, Atomizität, Papierkorb anders | gesamte Gruppe A–D gegen MinIO-Container |
| Externe Speicher (`files_external`: SMB, SFTP, WebDAV, S3) | **U** (außerhalb des Scopes, `DEC-USR-003`) | Erkennung/Ausschluss muss zuverlässig sein | Erkennungstest, A/B kurz |
| Freigabe-Storage als Ziel/Quelle | nur B07 | Gruppenordner/Freigaben können im Heim-Mount vorkommen | A, B, C, D mit Freigabe als Zielstorage; Berechtigungen |
| Gruppenordner (`groupfolders`) | **U** | zusätzliche Wrapper (Berechtigungen), eigener Papierkorb | Gruppe A, D |
| Serverseitige Verschlüsselung | **U** | `Encryption`-Wrapper überschreibt `rename` (Q: `Wrapper/Encryption.php:194`) | Gruppe A, F |
| NFS/SMB-Datadir, andere Dateisysteme (z. B. case-insensitiv) | **U** | `rename()`-Semantik, `file_exists`/Unicode | A04/A05 |
| DB-basiertes Locking, `filelocking.enabled=false` | **U** (A12/A15 nur punktuell) | A14/A15-Ergebnis hängt an der Sperrwirkung | Gruppe A, C wiederholen mit `memcache.locking` leer |
| MySQL statt MariaDB, andere Versionen | **U** | DB-Verhalten ist nicht Gegenstand der Spikes | Datenmodell-Tests (testing.md) |
| PHP 8.3.14 im Nextcloud-Betrieb | **U** (HashContext getestet) | Referenzumgebung | Gruppen B–F gegen NC 34 auf PHP 8.3 |
| Nextcloud ≠ 34.0.4/35.0.0, 33 und älter | **U** | Support-Matrix `OQ-037` | Fingerprint-Vergleich, dann Läufe |
| Hintergrundjobs/`TimedJob`, `occ background-job:worker` | **U** | Dispatcher der App | eigener Spike (SP-Cron) |
| Retention des Papierkorbs, Quota | **U** | Verlässlichkeit des Papierkorb-Nachweises | D erweitern |
| Desktop-/Mobile-Clients real | **U** | nur Protokoll nachgestellt (Chunked v2) | Client-Smoke-Tests |

Außerdem nicht ausgeführt (bewusst): endgültiges Löschen bei deaktiviertem Papierkorb; direkte Datadir-Änderungen (Isolationsregel); `occ files:scan`-Spikes.

## 12. Entscheidungen, Konflikte und offene Punkte

Zur Entscheidung durch den PO (Vorlage in `open-questions.md`, „Runde 5“): **OQ-073** (Mechanismus für Nichtüberschreibung), **OQ-074** (Rolle der Dateisperren), **OQ-075** (Signaturlücke), **OQ-076** (`group_follow` adoptiert), **OQ-077** (Papierkorb-Nachweis), **OQ-078** (Befüllung der Fähigkeitsmatrix); P1: OQ-079…083. Neue Konflikte: **CONFLICT-018…021**. Bereits entschiedene IDs (`DEC-*`) bleiben unverändert; `decisions.md` wurde in dieser Runde nicht geändert, die Aufnahme der Konflikte und etwaiger neuer Entscheidungen folgt nach dem Review.

**Vorläufige Einordnung der Umsetzbarkeit (nicht beschlossen):** Solange OQ-073 nicht entschieden ist, ist die **Indexierung, Analyse, Dublettenerkennung und Vorschau** (M1–M7) von den Befunden unberührt; **jede mutierende Operation** (import, reassign, group_follow, reorganize, undo/Move, Papierkorb-Aktion nur mit `trash_verified`) steht unter dem Vorbehalt von §3.6. Die Papierkorb-Aktion für bestätigte Duplikate ist dagegen mit dem Verfahren §9.2 plattformseitig belegbar – mit der Einschränkung, dass ein früherer Papierkorbeintrag desselben Pfades bei Löschung in derselben Sekunde verloren gehen kann (D03).

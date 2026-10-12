# Nextcloud-Integration

Stand: 2026-10-09 · Runde 5 · Status: **Spikes experimentell auf Nextcloud 34.0.4 und 35.0.0 ausgeführt (nur lokaler Heim-Storage, PHP 8.5, MariaDB 11.4, Redis-Locking); Ergebnisse in `platform-spikes.md`; alle früheren Tabellen unten bleiben unverändert, der aktuelle Verifikationsstand steht im Abschnitt „Ergänzung Runde 5“** [T]

## 0. Methodik und Grenzen

- Die Zielinstallation (`/home/cloud/owncloud`) ist **nicht zugänglich**; die Version ist unbekannt (siehe `environment.md`). Geprüft wurde daher der Quelltext des Repositories `github.com/nextcloud/server` (Branch `master`, abgerufen 2026-10-09) und die Entwicklerdokumentation `docs.nextcloud.com/server/latest`. `master` ist neuer als jede produktive Zielversion; **`@since`-Angaben im Quelltext sind die einzige belastbare Versionsinformation** und erlauben eine Untergrenze („mindestens Nextcloud X“).
- Es wurden Auszüge über ein Web-Abruf-Werkzeug zusammengefasst; Methodensignaturen sind deshalb nur dort wiedergegeben, wo sie ausgegeben wurden. Vor Implementierung ist jede Zeile gegen `lib/public` der Zielversion zu bestätigen (DEC-NCI-006). Das vollständige `Verifiziert gegen Zielversion`-Häkchen fehlt überall.
- **Klassen:** 1 = offiziell unterstützte öffentliche API (`OCP\…`) · 2 = versionsabhängig/eingeschränkt unterstützt · 3 = interne API (`OC\…`/`OCA\…`), potenziell instabil · 4 = nicht verifiziert.
- Zusatzfrage: Das Verzeichnis heißt `owncloud`. **Ist die Installation tatsächlich Nextcloud?** (siehe `environment.md`). Alles Folgende gilt nur für Nextcloud.

## 1. Ergebnisse

| # | Bedarf | Schnittstelle | Klasse | Befund (Quelle) |
|---|---|---|---|---|
| 1 | Benutzerordner | `OCP\Files\IRootFolder::getUserFolder($uid)` | 1 | Standard der Datei-API; **Methode nicht in dieser Runde abgerufen** – Existenz allgemein bekannt, Signatur zu bestätigen | 
| 2 | Move | `OCP\Files\Node::move($targetPath)` | 1 | `@since 6.0.0` (`lib/public/Files/Node.php`). Rename ist ein Move im selben Ordner. |
| 3 | Löschen | `Node::delete()` | 1 | `@since 6.0.0`. **Ob dies in den Papierkorb führt, ist nicht aus der OCP-Schnittstelle ablesbar** → 4 (Spike nötig, siehe 2.4). |
| 4 | Datei-ID | `Node::getId()` | 1 | `@since 6.0.0`. |
| 5 | Etag/MTime/Größe | `Node::getEtag()`, `getMTime()`, `getSize($includeMounts = true)` | 1 | alle `@since 6.0.0`. |
| 6 | Eigentümer | `FileInfo::getOwner(): ?IUser` | 1 | `@since 9.0.0` (`lib/public/Files/FileInfo.php`); Rückgabe kann `null` sein → Fall behandeln. |
| 7 | Mountpoint | `FileInfo::getMountPoint(): IMountPoint` | 1 | `@since 8.0.0`. |
| 8 | Share/Mount-Erkennung | `FileInfo::isShared()`, `isMounted()` | 1 | beide `@since 7.0.0`. Reichen allein nicht als Ausschlusskriterium für Gruppenordner/External – Mount-Typ via `IMountPoint` zu prüfen (4: Verfahren noch nicht verifiziert). |
| 9 | Storage/Internal Path | `Node::getStorage()`, `getInternalPath()` | 1 formal, aber `getStorage()` liefert `OCP\Files\Storage\IStorage` | `@since 6.0.0`. Nutzung nur für Identitätsprüfung (Storage-ID), nicht für Dateizugriff am Datadir vorbei. |
| 10 | Knoten per ID | `Folder::getById()`, `Folder::getFirstNodeById()` | 1 | `getById` `@since 6.0.0` (liefert Array, da mehrere Pfade über Shares möglich); `getFirstNodeById` `@since 29.0.0`. → **Untergrenze 29 oder eigener Wrapper.** |
| 11 | Suche/Listing | `Folder::getDirectoryListing()`, `search()`, `nodeExists()`, `newFile()`, `newFolder()`, `isSubNode()` | 1 | alle `@since 6.0.0`. |
| 12 | Eltern-ID | `FileInfo::getParentId()` | 1 | `@since 28.0.0`. |
| 13 | Events | `OCP\Files\Events\Node\{NodeCreatedEvent, NodeWrittenEvent, NodeRenamedEvent, NodeDeletedEvent, NodeCopiedEvent, NodeTouchedEvent}` und `Before…`-Varianten | 1 | Dateien existieren im Verzeichnis `lib/public/Files/Events/Node/`. `AbstractNodesEvent` und `NodeWrittenEvent` sind `@since 20.0.0`; `AbstractNodesEvent` liefert `getSource()`/`getTarget()` (für Rename/Copy). `NodeWrittenEvent` dokumentiert **nicht**, wann genau er ausgelöst wird. → Auslösezeitpunkt bei Chunked Upload, Client-Sync, Move, Versionsrestore: **4, Spike**. |
| 14 | Dispatcher | `OCP\EventDispatcher\IEventDispatcher`, `IEventListener` | 1 | Etabliert; in dieser Runde nicht einzeln abgerufen → 4 (Detailsignatur). |
| 15 | Dateisperren | `Node::lock($type)`, `unlock($type)`, `changeLock($targetType)` | 1 | `@since 9.1.0` (Node). `OCP\Lock\ILockingProvider`: `isLocked`, `acquireLock`, `releaseLock`, `changeLock`, `releaseAll`; Konstanten `LOCK_SHARED=1`, `LOCK_EXCLUSIVE=2`; `@since 8.1.0` (`$readablePath` seit 20.0.0). Semantik gegenüber WebDAV-Clients/Chunk-Uploads: **4**. |
| 16 | Background Jobs | `OCP\BackgroundJob\{Job, TimedJob, QueuedJob}`, `IJobList` | 1 | Dokumentiert (`developer_manual/basics/backgroundjobs.html`). `setInterval()`, `scheduleAfter()`. Läufe sind nicht pünktlich garantiert. |
| 17 | Parallele Läufe | `setAllowParallelRuns(false)` | 2 | Laut Dokumentation **seit Version 27**. In `IJob` selbst nicht deklariert (Methode liegt an der Basisklasse `Job`). |
| 18 | Zeitsensitivität | `IJob::TIME_SENSITIVE` / `TIME_INSENSITIVE`, `setTimeSensitivity()` | 1/2 | Konstanten `@since 24.0.0`. Erlaubt Verlagerung ins nächtliche Fenster. |
| 19 | Job-Zeitlimits | – | 4 | Doku behandelt Ausführungs-Zeitlimits nicht. |
| 20 | occ-Worker | `occ background-job:worker` | 4 | In der Doku nur gelistet; Optionen nicht belegt. Hilfetext auf Zielsystem auslesen (`occ help background-job:worker`). Benutzerbezogene Filter nicht belegt. |
| 21 | Eigene occ-Befehle | Symfony Command, Registrierung in `appinfo/info.xml` | 1 | Etabliertes Muster; in dieser Runde nicht abgerufen → Detail 4. |
| 22 | Papierkorb PHP | `OCA\Files_Trashbin\Trash\ITrashManager` (extends `ITrashBackend`) | **3** | Liegt unter `apps/files_trashbin/lib/Trash/` (Namensraum `OCA`, **nicht** `OCP`). Methoden `registerBackend`, `pauseTrash`, `resumeTrash` mit `@since 15.0.0`; **kein** Public-API-Marker erkennbar. Das Interface bietet laut Abruf Methoden zum Auflisten/Wiederherstellen nur über den geerbten `ITrashBackend` (nicht ausgewertet). → Abhängigkeit von App-Interna. |
| 23 | Papierkorb WebDAV | `remote.php/dav/trashbin/USER/{trash, restore, <item>}` | 1 (dokumentiert) | Dokumentiert (`client_apis/WebDAV/trashbin.html`): `PROPFIND …/trash` listet; Eigenschaften `nc:trashbin-filename`, `nc:trashbin-original-location`, `nc:trashbin-deletion-time`; Wiederherstellen durch Verschieben eines Elements nach `…/restore`; `DELETE <item>` löscht endgültig. Aus einer App heraus als HTTP-Aufruf an den eigenen Server **nicht praktikabel** (Authentifizierung im Cron-Kontext). Die Doku gibt keine Inhalts-/Hash-Garantie an. |
| 24 | Papierkorb occ | `occ trashbin:restore`, `trashbin:cleanup` | 4 | In Doku nur referenziert; Optionen nicht belegt. |
| 25 | Vorschauen | `OCP\IPreview` | 1 | Nicht in dieser Runde abgerufen → Detail 4. Hinweis: Vorschau ist ein **abgeleitetes, skaliertes, rotiertes** Bild und für pHash ungeeignet, wenn Reproduzierbarkeit über Updates verlangt ist. |
| 26 | Appdata | `OCP\Files\IAppData` | 1 | Nicht in dieser Runde abgerufen → 4. |
| 27 | DB/Migrationen | `IQueryBuilder`, `SimpleMigrationStep`, `ISchemaWrapper` | 1 | Etabliert; Detail → 4. |
| 28 | Einstellungen | `IConfig`/`IAppConfig` | 1 | Detail → 4. |
| 29 | Benutzerzeitzone | `OCP\IDateTimeZone` | 4 | Existenz/Semantik nicht verifiziert. |
| 30 | Benutzerlöschung | `OCP\User\Events\UserDeletedEvent` | 4 | nicht verifiziert. |
| 31 | Webhook-/Flow-Kompatibilität der Events | `IWebhookCompatibleEvent` | 2 | `AbstractNodesEvent` implementiert dieses Interface (sichtbar im Quelltext). Für uns nicht relevant, nur Hinweis auf Nachrichtenform. |

## 2. Bewertung der Kernfragen

### 2.1 Eigentümerschaft, Mounts, Shares (DEC-USR-002/003, DEC-NCI-004)

- Für jeden Knoten sind `getOwner()` (kann `null`), `getMountPoint()`, `isShared()`, `isMounted()` verfügbar. **Ausschlussregel [T]:** Ein Knoten ist indexierbar, wenn sein Eigentümer der App-Benutzer ist **und** sein Mountpoint der Home-Storage-Mount dieses Benutzers ist. Letzteres muss über `IMountPoint` (Mount-Typ/Provider-Klasse) geprüft werden; die genaue Prüfung (Home-Mount erkennen ohne `OC\`-Klassen) ist **4**. Fallback-Strategie: Vergleich der Storage-ID des Quell-/Zielordners mit der Storage-ID des Benutzer-Wurzelordners, gelesen über `getStorage()->getId()`.
- `Folder::getById()` kann mehrere Treffer liefern (gleiche Datei über mehrere Pfade, etwa als Share). Deshalb immer den Treffer wählen, dessen Pfad unter dem Eigentümer-Home liegt.

### 2.2 File-ID-Stabilität (Kernannahme der Architektur)

- `Node::move()` bleibt in derselben Storage ein Umbenennen im Dateicache; die Datei-ID bleibt dabei erfahrungsgemäß erhalten. **Das ist aus den abgerufenen Quellen nicht belegt (4).** Zu verifizieren per Spike auf dem Zielsystem: ID vor/nach Rename, Move in anderen Ordner, Move in Papierkorb, Versionsrestore.
- Bei Move über **Storage-Grenzen** wird kopiert und gelöscht – neue ID. Deshalb Beschränkung auf Home-Storage (REQ-SRC-06).
- Konsequenz: Identität = `file_id` **plus** Verifikation durch SHA-256/Signatur; Verlust der ID führt zu Prüffall, nicht zu automatischer Zuordnung über Dateinamen.

### 2.3 Eigene Events (DEC-NCI-005)

- `NodeRenamedEvent` besitzt `getSource()` und `getTarget()` (über `AbstractNodesEvent`, `@since 20.0.0`). Damit lässt sich ein Rename mit Quell- und Zielpfad in der Journal-Absicht abgleichen.
- Der Mechanismus „In-Memory-Flag“ ist unzureichend (Events können in anderem Prozess ausgeliefert werden; Listener läuft synchron im selben Prozess der Operation – das **ist** dort oft nutzbar, aber nicht verlässlich bei Folgeverarbeitung durch Client-Sync). Empfohlen [T]: Journal-Abgleich nach Operations-ID/erwartetem Nachzustand (`architecture-overview.md` §6).
- Da Nextcloud-Events für **jeden** Benutzer und jede Quelle (Web, WebDAV, Client, andere Apps) feuern, muss der Listener sehr früh filtern: Benutzer aktiviert? Pfad in Quelle/Sammlung? Home-Storage? Erst dann Queue-Eintrag.

### 2.4 Papierkorb (OQ-005, CONFLICT-006)

| Weg | Klasse | Eignung |
|---|---|---|
| `Node::delete()` | 1 | Löschen durch öffentliche API; **Papierkorb-Verhalten nicht aus OCP belegt**. Hängt von aktivierter App `files_trashbin` und Retention-Konfiguration ab. Nachprüfung nach dem Löschen nötig. |
| `OCA\Files_Trashbin\Trash\ITrashManager`/`ITrashBackend` | 3 | Technisch erreichbar; nicht als stabile App-API ausgewiesen. Nur hinter Adapter, nur wenn Spike bestätigt. |
| WebDAV `trashbin/USER/restore` | 1 (Doku) | Für externe Clients dokumentiert; aus der App im Cron nicht sinnvoll. |
| `occ trashbin:restore` | 4 | Operatorwerkzeug, nicht als App-Mechanismus geeignet. |

Schlussfolgerung [T]: **Löschen ist über öffentliche API darstellbar, automatisches Wiederherstellen nicht.** Empfohlene Semantik (siehe OQ-032): Undo einer Papierkorb-Aktion ist im MVP „Wiederherstellung angeleitet“ (Journal enthält Originalpfad, Zeit, Hash, `file_id` vor dem Löschen); automatisches Restore nur, wenn ein Spike die Nutzung von `ITrashBackend` auf der Zielversion belegt und als Adapter akzeptiert wird.

### 2.5 Background Jobs und Parallelität

- Jobs sind Cron-getrieben, ohne Pünktlichkeitsgarantie. `setAllowParallelRuns(false)` verhindert parallele Instanzen *desselben* Jobs (seit 27 laut Doku). Das genügt **nicht** als Fairness- oder Mutex-Mechanismus über mehrere Benutzer und Worker → App-eigene Lease-Sperre (DB) pro `(user, source)` und global (siehe `architecture-overview.md` §6).
- Zeitlimits des Cron-Laufs und Belegung durch andere Apps: **4**. Konservativ planen: Jobs kurz, selbstbegrenzend (Zeitbudget), wiederaufnehmbar.
- `occ`: Benutzerbezogener Worker wäre ein **eigener** Befehl der App (`photoorganizer:run --user=…`), nicht der Systemworker `background-job:worker` (Optionen unbelegt).

### 2.6 Sperren und konkurrierende Clients

`Node::lock()`/`ILockingProvider` existieren öffentlich. Ob eine laufende Chunked-/WebDAV-Übertragung eine für uns sichtbare Sperre hält, ist **4**. Die Upload-Stabilitätsregeln (DEC-STB) dürfen sich daher nicht auf Sperren verlassen; Sperren sind zusätzlicher Schutz während der kurzen Mutationsphase.

### 2.7 Vorschau und Metadaten

`IPreview` (4) liefert Vorschaubilder; Medienmetadaten (EXIF/QuickTime) bietet Nextcloud nicht als allgemeine öffentliche API (Metadaten-Provider in Core sind app-/versionsabhängig). **Eigene Extraktion nötig** (siehe `architecture-overview.md` §7).

## 3. Offene Verifikationsaufgaben (Spike-Liste, noch nicht durchgeführt)

1. Zielversion und Produktidentität (Nextcloud vs. ownCloud) – `environment.md`.
2. `lib/public` der Zielversion: Existenz der Signaturen aus Tabelle 1 (bes. Zeilen 1, 10, 14, 21, 25–30).
3. Spike „File-ID“: Rename/Move/Trash/Restore → ID, Pfad, Events.
4. Spike „Events“: Web-Upload, Desktop-Client, Mobile, Chunked-Upload, `occ files:scan`, Versionsrestore → welche Events, welche Reihenfolge, mit welchem Benutzer.
5. Spike „Papierkorb“: `Node::delete()` führt zu Trashbin-Eintrag? Eintrag-ID? Bleibt `file_id`? Restore-Wege.
6. Spike „Sperren“: Sichtbarkeit laufender Uploads.
7. Spike „Cron“: Laufzeit, Parallelität, Job-Limit.
8. Ergebnisse mit Versionsangabe hier eintragen; erst dann Klasse auf „verifiziert“ setzen.

---

# Ergänzung Runde 2b

## 4. Evidenztrennung (DEC-ARC-009)

Jede Aussage in diesem Dokument gehört zu genau einer Evidenzart:

| Evidenzart | Bedeutung | Beispiele |
|---|---|---|
| **R – Referenz bestätigt** | Vom Product Owner für die Referenzinstallation bestätigt; **nicht** allgemeingültig | Nextcloud 34.0.4; Cron funktioniert; `files_trashbin` aktiv; keine Server-Verschlüsselung; Imagick/GD/EXIF/APCu/Redis/intl vorhanden; ffprobe vorhanden; ExifTool fehlt |
| **A – API verifiziert (Quelle/`@since`)** | Öffentliche API im Upstream-`master` gelesen (Runde 2), `@since` nennt Untergrenze. **Noch nicht gegen 34.0.4 geprüft.** | Zeilen 2, 4–8, 10–13, 15–16, 18 der Tabelle in §1 |
| **D – Dokumentation** | Offizielle Entwicklerdokumentation sagt es, Quelltext nicht eingesehen | Trashbin-WebDAV; `setAllowParallelRuns` seit 27 |
| **U – Nicht verifiziert** | Weder Referenz noch Quelle | `file_id`-Stabilität, Event-Auslösezeitpunkte, `Node::delete()` → Trash, Sperrverhalten |

Hinweis zur Referenz: Die in Runde 2 gelesenen Quelltexte stammen aus dem Upstream-`master` zum Abrufzeitpunkt (sie enthielten Änderungsvermerke bis „33.0.0“). Ob dieser Stand dem Quelltext von 34.0.4 entspricht, wurde **nicht geprüft**. Bis zu einem Abgleich gegen 34.0.4 gilt: **keine Zeile ist für 34 verifiziert**.

## 5. Zielbild: Allgemeine Nextcloud-Fähigkeiten vs. Referenz

| Fähigkeit | Allgemein (für die App-Architektur) | Referenzumgebung |
|---|---|---|
| Dateizugriff | `OCP\Files` (Klasse 1) | Verfügbar (impliziert) |
| Hintergrundverarbeitung | Nextcloud `TimedJob` über Cron/Webcron/AJAX (Durchsatz variiert) | Cron funktioniert (R) |
| Papierkorb | App `files_trashbin` **kann** deaktiviert sein → Papierkorb-Aktion verweigern | aktiviert (R) |
| Verschlüsselung | kann aktiv sein → **nicht unterstützt im MVP** (Warnung/Verweigerung bei Erkennung, wenn erkennbar: U) | deaktiviert (R) |
| Metadaten-/Bildwerkzeuge | nicht vorausgesetzt (PHP-Basis; alles Weitere optional) | EXIF/GD/Imagick (R), ffprobe (R), ExifTool fehlt (R) |
| Datenbank | alle von Nextcloud unterstützten über Abstraktion | MySQL (R) |
| Storage | Home-Storage des Benutzers; Typ nicht vorausgesetzt | nicht angegeben |

## 6. Versionsabhängige Integrationen (Kapselungsliste für DEC-ARC-007)

| Bereich | Variation | Kapselung [T] |
|---|---|---|
| Knoten per ID | `Folder::getFirstNodeById` ab 29 (A), sonst `getById` | `FileGateway::findNodeById()` |
| Events | Verfügbarkeit/Reihenfolge der `Node*Event`-Klassen (A: seit 20), Auslösezeitpunkt (U) | `EventSource` mit Version-/Spike-abhängiger Konfiguration |
| Parallelität von Jobs | `setAllowParallelRuns` ab 27 (D) | App-eigene Lease unabhängig davon (§2.5 Runde 2) |
| Papierkorb | Löschen: `Node::delete()` (A); Restore: nicht öffentlich (A/3) | `TrashGateway` mit Fähigkeiten `can_trash`, `can_verify_trash`, `can_restore` (alle per Capability Detection; Restore im MVP `false`) |
| Benutzerzeitzone | `IDateTimeZone` (U) | `UserSettingsGateway` |
| Vorschauen | `IPreview` (U) | `PreviewGateway` |
| Temporäre Dateien | `ITempManager` (U) | `TempFileGateway` |

Ergebnis der Prüfungen wird erst als „verifiziert“ eingetragen, wenn sie gegen den Quelltext der betreffenden Zielversion mit Pfad, Klasse und Methode belegt sind.

---

## Ergänzung Runde 3b: zusätzliche Spikes (nicht ausgeführt)

Aus `data-model.md` §17/§18 ergeben sich weitere zu verifizierende Punkte (alle **unverifiziert** für 34 und 35):
| Nr. | Frage | Warum |
|---|---|---|
| SP-A | Verhält sich `Node::move()` bei vorhandenem Ziel als Fehler, Überschreiben oder Umbenennen? *(ersetzt durch SP-A1…A6, 4b; **ausgeführt in Runde 5: überschreibt**, `platform-spikes.md` §3)* | Fencing-Restfenster F4/F5 |
| SP-B | Bleibt `file_id` bei Move innerhalb desselben Storage erhalten? Bei Storage-Wechsel? Bei Papierkorb und Wiederherstellung? | Integritätsklassen `rename_same` / `copy_possible`, Referenzwiedererkennung |
| SP-C | Ist ein Move im selben Storage eine Umbenennung oder eine Kopie mit Löschen (lokaler Storage, Objektspeicher)? | Klassifikation `storage_class` |
| SP-D | Wie lässt sich der Storage-Typ einer Node über öffentliche API erkennen? | Storage-Profil |
| SP-E | Welche Sperren erwirbt `Node::move()` selbst; schützt `Node::lock()` gegen WebDAV-Clients? | Mechanismus 6 in §17.2 |
| SP-F | Dauer einer Einzeloperation und Dauer eines Neuhash je Dateigröße auf der Referenz | Sicherheitsabstände, Kosten von OQ-052 |

| SP-G | Kann ein unveränderter Ordner-ETag als Hinweis auf einen unveränderten Teilbaum genutzt werden (Scan-Abkürzung)? | Vermeidung von Vollscans im Normalbetrieb (job-processing.md §4.4) |
| SP-H | Sind PHP-Hash-Kontexte (`hash_init`) serialisierbar und fortsetzbar in der Laufzeit der Zielversionen? | fortsetzbares Hashing großer Videos (job-processing.md §3.6) |

## Ergänzung Runde 4b: Spike-Katalog zur Überschreibsicherheit (nicht ausgeführt)

| Spike | Gegenstand | Details |
|---|---|---|
| SP-A1…A6 | Verhalten von `Node::move()` bei vorhandenem Ziel (Datei/Ordner), nebenläufiger Zielanlage (Race-Harness), Quelle=Ziel/Groß-Kleinschreibung/Unicode, verspätetem zweiten Aufruf, fehlendem Zielordner | `job-processing.md` §6.3.5. Ergebnisse je Nextcloud-Version (34, 35) und Storage-Klasse. Ersetzt die frühere Einzelfrage SP-A. |
| SP-I | Move eines zuvor geladenen, inzwischen umbenannten Knotens: scheitert er oder folgt er der `file_id`? | Grundlage von `move_source_by_path` |
| SP-J | `Node::delete()`: Papierkorb aktiv/inaktiv, Retention, Papierkorbeintrag auffindbar, endgültiges Löschen | Grundlage von `trash_verified` |
| SP-E (erweitert) | Sperre auf nicht existierenden Zielpfad; Wirkung auf Move und WebDAV-Clients; Wiedereintritt der eigenen Sperre | Grundlage von `lock_target` |
| SP-H (erweitert) | `HashContext`-Serialisierung unter PHP 8.3 (Referenz) und den Zielversionen; lokal nur PHP 8.5.4 geprüft: `sha256` ok, Einzelbitfehler teils stillschweigend akzeptiert | `job-processing.md` §3.6 |
Vergleichspunkt, nicht Standard: WebDAV `MOVE` mit `Overwrite: F` (RFC 4918, 412 bei vorhandenem Ziel).


---

## Ergänzung Runde 5: Verifikationsstand nach den Spikes (Auftrag 5)

**Quelle der Aussagen:** `platform-spikes.md` (Testfall-IDs dort). Geprüft wurde ausschließlich Nextcloud **34.0.4** und **35.0.0**, **lokaler Heim-Storage** (`OC\Files\Storage\Home`), MariaDB 11.4, Redis-Locking, PHP 8.5. Evidenz: **E** experimentell · **Q** Quelltext des Release-Images · **V** veröffentlichter API-Vertrag (PHPDoc) · **U** weiterhin unverifiziert. Die früher als „A“ (Upstream-`master`) geführten Zeilen sind für die genannten Versionen jetzt wie folgt eingeordnet; Zeilen, die nicht genannt sind, bleiben unverändert (U bzw. A/D).

### R5.1 Schnittstellen der Tabelle in §1

| # | Schnittstelle | Stand nach Runde 5 | Evidenz |
|---|---|---|---|
| 1 | `IRootFolder::getUserFolder` | funktioniert in CLI und Webkontext für beliebige Benutzer; liefert den Heim-Mount (`HomeMountPoint`) | E (alle Fälle) |
| 2 | `Node::move()` | **überschreibt ein vorhandenes Ziel stillschweigend**: Datei durch Datei, Datei durch Ordner (auch nicht leer), Ordner durch Datei; kein Papierkorbeintrag für das Überschriebene. Der PHPDoc-Vertrag nennt für diesen Fall weder Überschreiben noch Ablehnung. Ein veralteter Knoten verschiebt, **was jetzt am gespeicherten Pfad liegt** (nicht die File-ID). Ein Fehler in einem After-Listener erreicht den Aufrufer nicht (der Move gilt als erfolgreich); ein Fehler in einem Before-Listener (`AbortedEventException`) bricht den Move sauber ab. | E (A02–A06, A09, A16, A20–A22, A30), Q (`Local::rename`: `unlink($target)` bzw. `rmdir`, Zeile 375–380 in 34, 376–381 in 35; `View::rename` ohne Zielprüfung), V |
| 3 | `Node::delete()` | verschiebt bei aktivem `files_trashbin` in den Papierkorb (CLI wie WebDAV); File-ID bleibt; Papierkorbknoten per `getById` über die Wurzel (`/<uid>/files_trashbin/files/<name>.d<ts>`) erreichbar. Verhalten bei deaktiviertem Papierkorb nur aus dem Quelltext (bewusst nicht ausgeführt). | E (B05, D01–D05), Q (`Files_Trashbin\Storage::unlink`) |
| 4 | `Node::getId()` | stabil bei Rename, Move (auch Ordner mit Kindern), WebDAV-MOVE, Trash+Restore und – in der getesteten Kombination – beim Move vom Heim-Storage in einen beschreibbaren Freigabe-Storage; neu bei Copy; unbrauchbar für den **Quellknoten in `NodeRenamedEvent`** (wirft `NotFoundException`), verwendbar für den Zielknoten | E (B01–B05, B07, E05) |
| 5 | `getEtag/getMTime/getSize` | Signatur aus Größe, MTime, ETag **erkennt eine Inhaltsänderung nicht**, wenn sie in derselben Sekunde und mit gleicher Größe geschieht | E (B06, F05) |
| 10 | `Folder::getById` / `getFirstNodeById` | `getById` über die Benutzerwurzel findet auch Knoten im Papierkorbbereich; `Folder::getFirstNodeById` liefert den aktuellen Pfad nach fremder Umbenennung | E (D01, A08) |
| 11 | `Folder::newFile` | **überschreibt** einen vorhandenen Pfad (keine Exklusiv-Anlage); sie ist als Reservierungsprimitive ungeeignet | E (A10), Q (`View::file_put_contents`) |
| 13 | Datei-Events (`OCP\Files\Events\Node\*`) | alle erwarteten Klassen werden im Prozess der Aktion ausgelöst (Apache bei WebDAV, CLI bei App-Operationen); Rename/Move/Copy/Delete je genau einmal; Zielknoten trägt die File-ID, Quellknoten nicht; Ordner-Move erzeugt kein Ereignis je Kind | E (E01–E06), Q (`HookConnector`) |
| 14 | `IEventDispatcher`/`IEventListener` | Registrierung über `IRegistrationContext::registerEventListener` genügt für CLI- und Apache-Prozess | E |
| 15 | `Node::lock/unlock`, `ILockingProvider` | **nicht blockierend** (sofortige `LockedException`), exklusiv gegen alles, shared nur mit shared verträglich; wirkt für Node-API **und** WebDAV (423 bzw. 500 bei MOVE); **nicht** mit dem eigenen `Node::move` kombinierbar (auch eine eigene shared-Sperre blockiert den Move); auf nicht existierende Pfade nur über den internen Schlüssel des Providers; verwaiste Sperre bleibt ca. 1 h (TTL 3598 s) | E (C01–C07, A12), Q (`MemcacheLockingProvider::acquireLock/changeLock`) |
| 22 | `ITrashManager` (Klasse 3) | nicht benötigt: der Nachweis einer Papierkorboperation gelingt über `IRootFolder::getById` und WebDAV | E (D02) |
| 23 | Papierkorb-WebDAV | Listing, Eigenschaften (`trashbin-filename`, `trashbin-original-location` – mit/ohne führenden `/`!, `trashbin-deletion-time`, `fileid`), Inhalt per GET, Restore per MOVE; Restore bei belegtem Ziel überschreibt nicht | E (B05, D01–D04) |

Nicht ausgeführt und weiterhin **U**: `getOwner`, `isShared/isMounted` als Ausschlusskriterium, `getParentId` (stattdessen `getParent()->getId()`), Hintergrundjobs/TimedJob-Verhalten (außer dass der Cron-Service startet), `IPreview`, `IAppData`, `IAppConfig`, `IDateTimeZone`, `UserDeletedEvent`, `occ`-Befehle der App, DB-basiertes Locking, Verschlüsselung, Gruppenordner, externe Speicher, Objektspeicher.

### R5.2 Status des Spike-Katalogs

| Spike | Status | Ergebnis in einem Satz | Details |
|---|---|---|---|
| SP-A1…A3 | ausgeführt | `Node::move` und WebDAV `MOVE` (ohne `Overwrite: F`) überschreiben; mit `Overwrite: F` bleibt ein **nach** dem Sabre-Check entstehendes Ziel ungeschützt; kein getesteter öffentlicher Weg ist atomar nicht-überschreibend | `platform-spikes.md` §3 |
| SP-A4 | ausgeführt | Move auf den eigenen Pfad scheitert an der eigenen Sperre; Unicode-NFD-Zielname ersetzt eine NFC-benannte Datei; Groß-/Kleinschreibung: siehe §3.7 | §3.7 |
| SP-A5/A6 | ausgeführt | verspäteter Aufruf mit leerem Quellpfad scheitert; am Quellpfad inzwischen liegende Fremddatei wird **verschoben** | §3.5 |
| SP-B | ausgeführt | File-IDs stabil bei allen getesteten Verschiebungen, Trash/Restore und Heim→Freigabe-Move | §4 |
| SP-C | ausgeführt (teilweise) | Move heim→Freigabe erhält die ID (keine Kopie mit neuer ID); andere Storage-Klassen ungeprüft | §4.4 |
| SP-D | nicht ausgeführt | Storage-Typ über öffentliche API: nur `getStorage()` Klassenname/`getMountPoint()` getestet; kein Spike | – |
| SP-E | ausgeführt | siehe §5 | §5 |
| SP-F | teilweise | Größenordnung des SHA-256-Durchsatzes (F07), keine Referenzmessung | §8.5 |
| SP-G | nicht ausgeführt | Ordner-ETag als Scan-Abkürzung | – |
| SP-H | ausgeführt | SHA-256-Kontext serialisierbar und portabel zwischen PHP 8.2–8.5 in der Stichprobe; PHP erkennt Bitfehler und falsche Offsets nicht | §8 |
| SP-I | ausgeführt | siehe SP-A5/A6 | §3.5 |
| SP-J | ausgeführt | siehe §6 | §6 |

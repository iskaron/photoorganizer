# Zielumgebung und Referenzumgebung

Stand: 2026-10-09 · Runde 5 (Abschnitt 6: Docker-Testumgebung)

## 0. Grundsatz [V]

**Photo Organizer soll eine allgemein nutzbare, veröffentlichungsfähige Nextcloud-App werden** (`DEC-PRD-006`). Die Installation des Product Owners ist ausschließlich die **erste Test- und Referenzumgebung** (`DEC-PRD-007`), nicht die Grundlage installationsspezifischer Architekturentscheidungen.

Daraus folgt die strikte Trennung:

| Kategorie | Bedeutung | Konsequenz |
|---|---|---|
| **Referenzumgebung (nachgewiesen)** | Vom Product Owner bestätigt; hier dokumentiert. Claude hat sie **nicht selbst geprüft** (kein Serverzugriff). | Testziel, Messbasis, Entwicklungs-Smoke-Tests |
| **Allgemeine Nextcloud-Fähigkeit** | Öffentliche OCP-API bzw. dokumentierte Plattformeigenschaft (`nextcloud-integration.md`) | Grundlage der Architektur |
| **Optionale Umgebungsfähigkeit** | Von der Installation abhängig (ExifTool, ffprobe, Imagick-Formate, …) | Nur über Capability Detection; Fehlen blockiert die App nie |

## 1. Bestätigte Referenzumgebung (Angabe des Product Owners)

| Merkmal | Wert | Status |
|---|---|---|
| Nextcloud | 34.0.4, Build 34.0.4.1 | bestätigt (PO) |
| PHP CLI | 8.3.14 | bestätigt (PO) |
| PHP-FPM-Version/-Limits | – | **nicht angegeben** |
| Datenbank | MySQL (Version nicht angegeben; MySQL vs. MariaDB nicht unterschieden) | Typ bestätigt, Version offen |
| Background Jobs | Reguläre Nextcloud-Cron-Verarbeitung funktioniert | bestätigt; Intervall nicht angegeben |
| Serverseitige Verschlüsselung | deaktiviert | bestätigt |
| `files_trashbin` | aktiviert | bestätigt; Retention-Einstellungen nicht angegeben |
| `files_external` | aktiviert, für die App **nicht im Scope** (`DEC-USR-003`) | bestätigt |
| PHP-Erweiterungen | EXIF, GD, Imagick, intl, APCu, Redis vorhanden | bestätigt (ob Redis als Locking-Cache konfiguriert ist: nicht angegeben) |
| FFprobe | vorhanden (Version nicht angegeben) | bestätigt |
| ExifTool | **nicht installiert** | bestätigt |
| Imagick-Formate (HEIC/RAW) | – | **nicht angegeben** |
| Storage-Typ (lokal/Object Storage) | – | **nicht angegeben** (Datadir-Pfad deutet auf lokal; **nicht belegt**) |
| Bestand | ≈ 108.466 Bilddateien, zusätzlich Videos und Sidecars | Angabe PO |
| Pfade | `/home/cloud/owncloud` (Installation), `/home/cloud/data` (Datadir) | Referenz-Spezifika, **dürfen nirgends im App-Code oder in Konfigurationsannahmen vorkommen** |

Folgerungen, ausdrücklich nur für die Referenzumgebung:
- Kein ExifTool: Live-Photo-Zuordnung per Apple Content Identifier und HEIC/RAW-Metadaten sind dort ohne zusätzliche Installation nur eingeschränkt möglich (→ `architecture-overview.md` §7, `DEC-GRP-003`: unsichere Fälle bleiben im Eingang).
- FFprobe vorhanden: Videometadaten dort über einen optionalen Provider möglich.
- Imagick vorhanden: ob HEIC/RAW decodierbar sind, ist **unbekannt** und per Capability Detection festzustellen.
- Server-Verschlüsselung aus und Trashbin an: die in `ASM-03`/`ASM-04` genannten Annahmen sind für diese Umgebung erfüllt. Für andere Installationen gelten sie nicht automatisch.

## 2. Architekturvorgaben aus der Leitlinie [V]

Diese Vorgaben sind verbindlich (`DEC-ARC-001` bis `DEC-ARC-009`, `decisions.md` Abschnitt F): öffentliche OCP-APIs bevorzugen, Nextcloud-Datenbankabstraktion statt MySQL-Spezifika, keine absoluten Linux-Pfade, Nextcloud-eigene Background Jobs, optionale Medienwerkzeuge über Capability Detection, kein Blockieren der App bei fehlenden optionalen Fähigkeiten, Kapselung versionsabhängiger Integrationen, Betrieb ohne zusätzliche manuelle Serverkonfiguration, strikte Trennung nachgewiesener Fähigkeiten und allgemeiner APIs.

## 3. Vorschlag: Unterstützter Nextcloud-Versionsbereich [T]

**Nicht beschlossen** (`OQ-037`). Begründung und Vorbehalte:

| Aspekt | Vorschlag | Begründung / Vorbehalt |
|---|---|---|
| Entwicklungs- und Referenzziel | Nextcloud **34** | Referenzumgebung des Product Owners. |
| Untere Grenze | **Nextcloud 29** als technischer Boden | Ergibt sich ausschließlich aus den in Runde 2 geprüften `@since`-Angaben der geplanten API-Nutzung (`Folder::getFirstNodeById` 29.0.0 ist die höchste benötigte Mindestversion; `FileInfo::getParentId` 28.0.0; `setAllowParallelRuns` laut Doku 27). Wird `getFirstNodeById` über einen Adapter durch `getById` ersetzt, sinkt der Boden auf 28 bzw. 27. |
| Tatsächlich unterstützter Bereich | **Alle zum Release-Zeitpunkt von Nextcloud selbst als gepflegt geführten Hauptversionen, mindestens 29-kompatibel gebaut, aber nur die getesteten in `info.xml` ausweisen** | Welche Hauptversionen zum Veröffentlichungszeitpunkt gepflegt sind, wurde nicht geprüft und wird hier **nicht behauptet**. Die konkrete Untergrenze ist daher vor Veröffentlichung gegen die offizielle Release-Übersicht festzulegen. |
| `max-version` | Nur bis zur zuletzt getesteten Hauptversion | Vermeidet unbeabsichtigte Aktivierung auf ungetesteten Versionen. |
| CI-Matrix | älteste unterstützte, 34 (Referenz), neueste verfügbare Hauptversion | Siehe `testing.md` (später). |
| PHP | Der von der jeweiligen Nextcloud-Version geforderte PHP-Bereich; App-Code ohne Features, die höher als die niedrigste unterstützte PHP-Version liegen | Konkrete Bereiche sind gegen die Nextcloud-Systemanforderungen der unterstützten Versionen zu prüfen; PHP 8.3.14 ist nur die Referenz. |
| Datenbanken | Alle von Nextcloud offiziell unterstützten, soweit sie die Query-Builder-/Schema-Abstraktion tragen | Referenz: MySQL. Keine MySQL-spezifische Implementierung (`DEC-ARC-002`). SQLite (Nextcloud-Entwicklungsdatenbank) weiterhin **nicht** als Produktivziel, solange Nebenläufigkeitsverhalten nicht belegt ist. |

## 4. Weiterhin fehlende Informationen zur Referenzumgebung (optional)

Nur zur Planung der Referenz-Tests; beeinflusst die allgemeine Architektur **nicht**. Alle Befehle lesend. Webserver-Benutzer und Pfade bitte lokal ersetzen (die hier gezeigten Variablen sind Platzhalter).

```bash
# Platzhalter: NC_DIR = Nextcloud-Installationsverzeichnis, WEBUSER = Webserver-Benutzer
OCC="sudo -u $WEBUSER php $NC_DIR/occ"

$OCC config:app:get core backgroundjobs_mode
$OCC config:system:get trashbin_retention_obligation
$OCC config:system:get filelocking.enabled
$OCC config:system:get memcache.locking
grep -c "'objectstore'" "$NC_DIR/config/config.php"       # Zähler; Inhalt nicht ausgeben
mysql --version 2>/dev/null                                 # MySQL oder MariaDB, Version
sudo -u $WEBUSER php -r 'if(class_exists("Imagick")){foreach(["HEIC","HEIF","CR2","NEF","ARW","DNG"] as $f){echo $f,": ",count(Imagick::queryFormats($f))?"ja":"nein","\n";}}'
ffprobe -version 2>/dev/null | head -1
grep -HE 'memory_limit|max_execution_time|request_terminate_timeout|pm\.max_children' /etc/php/*/fpm/php.ini /etc/php/*/fpm/pool.d/*.conf 2>/dev/null
```

Hinweis: `occ config:list` nicht verwenden (kann Secrets enthalten).

---

## 5. Aktualisierung Runde 3: Plattformfokus (Vorschlag, aus Auftrag 3)

Auftrag 3 legt den **ersten Entwicklungs‑ und Testfokus auf Nextcloud 34 und 35** fest; Erweiterung auf 33 nach Tests möglich; die unterstützte Versionsmatrix wird später mit Kompatibilitätstests verbindlich festgelegt (`PROP-DM-009`, `OQ-037`). Dies **ersetzt** den Vorschlag in §3 (Boden 29, „gepflegte Hauptversionen“). Die in Runde 2 festgestellte API‑Untergrenze aus `@since`‑Angaben (z. B. 29 für `Folder::getFirstNodeById`) bleibt als technische Information gültig, ist aber kein Support‑Versprechen. **Nextcloud 35** ist in dieser Runde nicht geprüft; jede API‑Aussage gilt als unverifiziert, bis sie gegen den Quelltext von 34 und 35 belegt ist.


---

## 6. Docker-Testumgebung für Plattform-Spikes (Runde 5, Auftrag 5)

Die Architekturannahmen zur Nextcloud-Plattform werden auf einer **vollständig isolierten, wegwerfbaren** Docker-Compose-Umgebung experimentell geprüft (`tests/integration/nextcloud-spikes/`, Bedienung dort im `README.md`, Ergebnisse in `platform-spikes.md`). Diese Umgebung ist **keine** Abbildung der Referenzumgebung aus §1, sondern eine reproduzierbare Laborumgebung; ihre Abweichungen sind unten ausdrücklich aufgeführt.

### 6.1 Isolation (Auftrag 5 §1)

| Regel | Umsetzung |
|---|---|
| Keine Verbindung zu Produktivsystemen, keine produktiven Daten/Zugangsdaten | eigene Datenbank, eigener Redis, eigene Volumes; zufällig erzeugte, rein lokale Testpasswörter |
| Keine Abhängigkeit von `/home/cloud/owncloud` | keine Referenz in der Testinfrastruktur |
| Keine Host-Mounts produktiver Verzeichnisse | einziger Host-Mount: die schreibgeschützte Probe-App aus dem Repository |
| Kein Docker-Socket, nichts Privilegiertes | `privileged: false`, `no-new-privileges`, kein Socket-Mount |
| Keine öffentlich erreichbaren Ports | es werden **keine Ports veröffentlicht**; Netzwerk `internal: true`; Zugriff nur über `docker compose exec` |
| Eindeutiger Präfix, kein globales Cleanup | Projekte `po-spike-nc34` / `po-spike-nc35`, Label `po-spike=1`; `reset`/`down` wirken nur auf das gewählte Projekt |
| Getrennte Installationen | je Version eigenes Projekt, Volume und Netz; kein Upgrade zwischen den Versionen |

### 6.2 Tatsächlich eingesetzte Versionen (Digest-fest, keine beweglichen Tags)

| Komponente | Nextcloud 34 | Nextcloud 35 |
|---|---|---|
| Nextcloud | 34.0.4 (Build 34.0.4.1) | 35.0.0 (Build 35.0.0.10) |
| Image | `nextcloud@sha256:0e5688ba…acacf74` (Tag `34.0.4-apache`) | `nextcloud@sha256:23c10153…8c89936` (Tag `35.0.0-apache`) |
| PHP (im Image) | 8.5.11 (CLI/Apache) | 8.5.10 (CLI/Apache) |
| Webserver | Apache 2.4.68 (mod_php) | Apache 2.4.68 (mod_php) |
| Datenbank | MariaDB 11.4.13 (`mariadb@sha256:12928441…47cad1b3b18e3a6`, Tag `11.4`) | gleich |
| Cache/Locking | Redis 7.4.11 (`redis@sha256:858f009f…8b3499`, Tag `7.4-alpine`), `memcache.locking = \OC\Memcache\Redis`, Provider `MemcacheLockingProvider` | gleich |
| Hintergrundjobs | Cron-Modus, eigener Cron-Service | gleich |
| Datadir-Dateisystem | Docker-Named-Volume auf ext4 (`stat -f` meldet „ext2/ext3“) | gleich |
| Heim-Storage | `OC\Files\Storage\Home` (Local), Wrapperkette: `Files_Trashbin\Storage` → `FilesLock\Storage\LockWrapper` → (Probe-Wrapper, passiv) → `Quota` → `Home` | gleich |
| Papierkorb / Versionen / Verschlüsselung | aktiv / aktiv / aus | gleich |
| PHP-CLI-Zusatzimages (nur HashContext-Matrix) | `php:8.2`, `8.3.14` (= PO-Referenz), `8.3`, `8.4`, `8.5` (alle `-cli-alpine`, per Digest, siehe `versions/php-matrix.env`) | – |
| Host | Docker 25.0.2, Compose 2.24.5, Linux 7.0.0, x86_64 | – |

Beide Image-Indizes enthalten `linux/amd64`; die Verfügbarkeit wurde vor dem Aufbau mit `docker buildx imagetools inspect` geprüft (2026-10-09). Die tatsächlichen Versionen jedes Testlaufs stehen in `results/nc<ver>/runs/<Lauf>/environment.json`.

### 6.3 Abweichungen zur Referenzumgebung des Product Owners (§1) – für die Übertragbarkeit der Ergebnisse relevant

| Aspekt | Referenz (PO) | Testumgebung | Folge |
|---|---|---|---|
| PHP | 8.3.14 | 8.5.10 / 8.5.11 (Image) | Nextcloud-Verhalten wurde unter PHP 8.5 beobachtet. Nur der HashContext-Test deckt PHP 8.3.14 ab (eigenes Image). Ob Nextcloud 34 im Referenzbetrieb unter 8.3 andere Pfade nimmt, ist **nicht** geprüft. |
| Datenbank | MySQL (Version unbekannt) | MariaDB 11.4.13 | Datenbankabhängiges Verhalten (Transaktionen, Isolationsstufen) ist **nicht** Gegenstand dieser Spikes; die Datenbankaussagen in `data-model.md`/`job-processing.md` bleiben ungeprüft. |
| Locking | `memcache.locking` unbekannt | Redis | Das Sperrverhalten (Abschnitt C) gilt für den Redis-Provider. Die DB-basierte Variante (`DBLockingProvider`) ist **nicht** geprüft. |
| Storage | nicht angegeben | lokaler Heim-Storage auf ext4 | Alle Befunde gelten **nur** für diese Storage-Klasse (siehe `platform-spikes.md` §11). |
| Zusätzliche Apps | unbekannt | Standardapps des Images, darunter `files_lock` (wirksam als Storage-Wrapper) | Die Wirkung weiterer Apps/Wrapper (Gruppenordner, Verschlüsselung, externe Speicher) ist **nicht** geprüft. |

### 6.4 Instrumentierung in der Testumgebung

Die Probe-App registriert zwei Test-Hilfen, die **interne** Nextcloud-APIs nutzen (Storage-Wrapper `OC\Files\Filesystem::addStorageWrapper`, Sabre-Hook). Sie sind passiv, solange keine Steuerdatei bzw. kein Flag gesetzt ist, aber der Probe-Wrapper steht dauerhaft in der Wrapperkette des Heim-Storages. Ergebnisse, die auf dieser Instrumentierung beruhen (Zeitfenster-Messungen, „geschützter Move“), sind in `platform-spikes.md` als **instrumentiert** gekennzeichnet.

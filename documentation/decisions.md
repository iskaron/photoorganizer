# Decision Log

Stand: 2026-10-09 · Runde 4b – siehe Abschnitte D.3–D.5, E, F, G, H, I und J

Dieses Dokument enthält **ausschließlich** die vom Product Owner und Jana (ChatGPT) in Auftrag 1 verbindlich beschlossenen fachlichen Entscheidungen (Abschnitt A) sowie davon getrennt technische Vorschläge (Abschnitt B) und Entscheidungsbedarf (Abschnitt C).

## Statuskennzeichnung

| Kürzel | Bedeutung |
|---|---|
| **[V]** | Verbindlich beschlossen (Quelle: Auftrag 1) |
| **[T]** | Technisch vorgeschlagen, noch nicht beschlossen |
| **[O]** | Noch zu entscheiden (siehe `open-questions.md`) |

Regeln: IDs sind stabil und werden nie wiederverwendet. Geänderte oder verworfene Entscheidungen werden mit Status `ersetzt durch DEC-…` bzw. `verworfen` markiert, nicht gelöscht. Entscheidungen in Abschnitt A werden nur durch den Product Owner geändert.

Die Formulierungen in Abschnitt A folgen eng dem Auftragstext. Wo eine Entscheidung nach Analyse Auslegungsspielraum lässt, steht dies unter „Hinweis“ und verweist auf eine offene Frage (`OQ-…`); der Beschluss selbst wird nicht umgedeutet.

---

## A. Verbindlich beschlossene Entscheidungen [V]

### A.1 Benutzer und Berechtigungen (DEC-USR)

| ID | Entscheidung |
|---|---|
| DEC-USR-001 | Jeder Benutzer verwaltet ausschließlich seinen eigenen Nextcloud-Dateibestand. |
| DEC-USR-002 | Freigegebene Dateien und Ordner (eingehende Shares) werden nicht einmal indiziert. |
| DEC-USR-003 | Gruppenordner und externe Mounts sind zunächst ausgeschlossen. |
| DEC-USR-004 | Benutzer können ihren eigenen Index und Organizer aktivieren, deaktivieren und pausieren. |
| DEC-USR-005 | Eine App-Installation allein startet keinerlei Dateiverarbeitung. |
| DEC-USR-006 | Ein Administrator kann einzelne Benutzer oder die gesamte Verarbeitung pausieren. |
| DEC-USR-007 | Administrative Freigabe überschreibt niemals die persönliche Aktivierung (Verarbeitung läuft nur, wenn Benutzer aktiviert **und** Admin nicht pausiert hat). |
| DEC-USR-008 | Die Admin-Oberfläche zeigt ausschließlich Benutzeridentität, aggregierte Zähler und Betriebszustände. |
| DEC-USR-009 | Die Admin-API enthält keine Dateinamen, Pfade, Vorschauen, EXIF-Daten, Hashes oder Einzelheiten über Benutzerfotos. |

Hinweis zu DEC-USR-009: Nextcloud-Administratoren haben technisch über andere Wege (DB, Datadir, `occ`) Zugriff. Die Entscheidung bezieht sich auf die Oberfläche und API **dieser App**. Siehe Risiko RISK-ADM in `architecture-overview.md`.

### A.2 Quellen und Ziele (DEC-SRC)

| ID | Entscheidung |
|---|---|
| DEC-SRC-001 | Beliebig viele konfigurierbare Eingangsordner pro Benutzer. |
| DEC-SRC-002 | Ein konfigurierbarer Ordner für die organisierte Fotosammlung. |
| DEC-SRC-003 | Quellen besitzen eigene Datumsstrategien, Namensregeln und Prioritäten. |
| DEC-SRC-004 | Die Zielstruktur ist grundsätzlich `Fotos/Jahr/Monat/Quelle`. |
| DEC-SRC-005 | Fotos und eigenständige Videos liegen gemeinsam in dieser Struktur. |
| DEC-SRC-006 | Medien können aus einem historischen Bestand oder einem laufenden Sofortupload stammen. |

### A.3 Datum und Quellenpriorität (DEC-DAT)

| ID | Entscheidung |
|---|---|
| DEC-DAT-001 | Quelle „Kamera“: EXIF-Aufnahmedatum bzw. Video-/QuickTime-Aufnahmezeit, sonst Datei-MTime. |
| DEC-DAT-002 | Quelle „Messenger“: Datei-MTime als Empfangsdatum. |
| DEC-DAT-003 | MTime ist kein Zeitpunkt der erstmaligen Indexierung. |
| DEC-DAT-004 | EXIF-Zeit ohne Zeitzone bleibt als lokale Aufnahmezeit erhalten. |
| DEC-DAT-005 | Vorhandene Zeitzonenangaben werden berücksichtigt. |
| DEC-DAT-006 | MTime wird in der konfigurierten Benutzerzeitzone dargestellt. |
| DEC-DAT-007 | Zeitstempel, Zeitquelle und Rohwerte werden dokumentiert (persistiert). |
| DEC-DAT-008 | Quellen haben konfigurierbare Prioritäten. |
| DEC-DAT-009 | Kamera/EXIF gewinnt gegenüber Messenger. |
| DEC-DAT-010 | Eine später auftauchende, höher priorisierte Herkunft darf eine bereits einsortierte Datei neu zuordnen, sofern kein manueller Ablageort-Override (`location_override`) besteht. |

Hinweis: DEC-DAT-009 vermischt Quellenpriorität und Datumsquelle → `OQ-007`. DEC-DAT-010 steht in Spannung zu DEC-DRY-007 → `OQ-001`.

### A.4 Dateinamen (DEC-NAM)

| ID | Entscheidung |
|---|---|
| DEC-NAM-001 | Standardschema: Quelle, Datum, Uhrzeit, Kollisionsnummer, optionale Beschreibung, Erweiterung. Beispiel: `Kamera 2026-10-09 13-12-12 01 Mein Lieblingsfoto.jpg` |
| DEC-NAM-002 | Windows-kompatible Dateinamen; keine Doppelpunkte. |
| DEC-NAM-003 | Automatisch erzeugt wirkende Originalnamen (z. B. `IMG_1234`) werden nicht angehängt. |
| DEC-NAM-004 | Aussagekräftige Originalnamen werden als Beschreibung übernommen. |
| DEC-NAM-005 | Vollständige Originalnamen bleiben in der Datenbank. |
| DEC-NAM-006 | Manuelle Umbenennungen in Nextcloud werden als persönliche Beschreibung übernommen und in das standardisierte Schema integriert. |
| DEC-NAM-007 | Bereits vorhandene Präfixe werden nicht mehrfach angehängt. |
| DEC-NAM-008 | Kollisionsnummern werden atomar reserviert. |
| DEC-NAM-009 | Nummern werden niemals wiederverwendet oder nachträglich umnummeriert. |
| DEC-NAM-010 | Alle Komponenten einer Mediengruppe besitzen dieselbe Nummer und denselben Basisnamen. |

Hinweis: Was „automatisch erzeugt wirkend“ heißt, ist nicht definiert → `OQ-010`. Ob eine manuelle Umbenennung die Nummer ändern darf, → `OQ-011`.

### A.5 Unveränderlichkeit (DEC-IMM)

| ID | Entscheidung |
|---|---|
| DEC-IMM-001 | Dateiinhalte dürfen **niemals** verändert werden. |
| DEC-IMM-002 | Keine EXIF-Manipulation, Skalierung, Rotation, Neukomprimierung oder Metadatenkorrektur. |
| DEC-IMM-003 | Ausschließlich Verschieben und Umbenennen über unterstützte Nextcloud-Datei-APIs (plus Papierkorb-Verschiebung gemäß DEC-DUP-005). |
| DEC-IMM-004 | Die Original-SHA-256 bleibt unverändert. |
| DEC-IMM-005 | Vorschauen und Analysemetadaten werden separat (außerhalb der Originaldatei) verwaltet. |

### A.6 Deduplikation (DEC-DUP)

| ID | Entscheidung |
|---|---|
| DEC-DUP-001 | SHA-256 identisch bedeutet identischer Dateiinhalt. |
| DEC-DUP-002 | Pro Benutzer und SHA-256 höchstens ein kanonisches Exemplar in der organisierten Sammlung. |
| DEC-DUP-003 | Weitere physische Kopien dürfen im Eingang verbleiben. |
| DEC-DUP-004 | Sämtliche Herkünfte und Importvorgänge werden dokumentiert. |
| DEC-DUP-005 | Exakte Duplikate werden zunächst nur logisch in Quarantäne gestellt. Keine automatische Löschung. Eine **manuelle** Sammelaktion darf zusätzliche, nachgeprüfte Kopien in den Nextcloud-Papierkorb verschieben. |
| DEC-DUP-006 | Vor dem Entfernen werden Dateiidentität und Hash erneut geprüft. |
| DEC-DUP-007 | Mediengruppen und das zu behaltende Exemplar müssen vor dem Entfernen geschützt sein. |

### A.7 Perceptual Hashing (DEC-PHA)

| ID | Entscheidung |
|---|---|
| DEC-PHA-001 | pHash dient ausschließlich der Erkennung möglicher Bildduplikate. |
| DEC-PHA-002 | Bilder mit unterschiedlichem SHA-256 werden regulär importiert, auch bei ähnlichem pHash. |
| DEC-PHA-003 | Ähnlichkeiten erscheinen zusätzlich im Prüfbereich. |
| DEC-PHA-004 | Keine automatische Löschung oder Importblockierung aufgrund von pHash. |
| DEC-PHA-005 | Manuelle Entscheidungen „Kein Duplikat“ werden dauerhaft an Inhaltsidentitäten (SHA-256) gebunden. |
| DEC-PHA-006 | SHA-256-identische Kopien dürfen diese Entscheidungen übernehmen. |
| DEC-PHA-007 | Bloße pHash-Ähnlichkeit erlaubt keine automatische Übertragung einer Entscheidung. |

### A.8 Mediengruppen (DEC-GRP)

| ID | Entscheidung |
|---|---|
| DEC-GRP-001 | Live Photos, RAW+JPEG und XMP-Sidecars werden als Mediengruppen verwaltet. |
| DEC-GRP-002 | Erkennung anhand eindeutiger Metadaten oder zuverlässiger Kombinationen aus Dateinamen, Zeitstempeln und Quellordnern. |
| DEC-GRP-003 | Unsichere Gruppierungen bleiben zur Prüfung im Eingang. |
| DEC-GRP-004 | Einzelne RAW-/JPEG-Dateien sind grundsätzlich gültige Medien. |
| DEC-GRP-005 | Fehlende optionale Begleitdateien blockieren den Import nicht. |
| DEC-GRP-006 | Erkennbar unvollständige Live Photos erhalten zunächst eine konfigurierbare Wartezeit. |
| DEC-GRP-007 | Neue Komponenten können vorhandene Gruppen ergänzen. |
| DEC-GRP-008 | Deduplikation erfolgt komponentenweise. |
| DEC-GRP-009 | Zusammengehörende Dateien werden mit konsistentem Basisnamen und Zielort verwaltet. |
| DEC-GRP-010 | Bei manueller Verschiebung einer Komponente **innerhalb** der Fotosammlung werden nach kurzer Verzögerung die anderen Komponenten automatisch nachgezogen. |
| DEC-GRP-011 | Bei Verschiebung **außerhalb** der Sammlung erfolgt keine automatische Mitnahme, stattdessen ein Prüffall. |
| DEC-GRP-012 | Manuell verschobene Gruppen erhalten einen `location_override`, den spätere automatische Sortierentscheidungen respektieren. |
| DEC-GRP-013 | Manuelles Löschen einer Komponente löscht niemals weitere Komponenten. |
| DEC-GRP-014 | Eine gelöschte kanonische Datei wird nicht automatisch durch eine noch quarantänisierte Kopie ersetzt. |

Hinweis: DEC-GRP-008 (komponentenweise Dedup) kann mit DEC-NAM-010/DEC-GRP-009 (konsistente Gruppe) kollidieren → `OQ-004`.

### A.9 Dry-Run und Freigaben (DEC-DRY)

| ID | Entscheidung |
|---|---|
| DEC-DRY-001 | Vor erstmaliger automatischer Organisation ist ein vollständiger Dry-Run verpflichtend. |
| DEC-DRY-002 | Anschließend muss der Benutzer den Plan ausdrücklich freigeben. |
| DEC-DRY-003 | Historische Bestände verwenden einmalige, dateibezogene Freigaben. |
| DEC-DRY-004 | Laufende Incoming-Quellen erhalten dauerhafte Freigaben für eine konkrete Regelversion. |
| DEC-DRY-005 | Freigaben sind möglichst quellenspezifisch. |
| DEC-DRY-006 | Relevante Regeländerungen erfordern erneut Dry-Run und Freigabe. |
| DEC-DRY-007 | Bereits importierte Medien werden durch Regeländerungen nicht ungefragt neu organisiert. |
| DEC-DRY-008 | Eine separate Funktion „Sammlung neu organisieren“ erhält eigenen Dry-Run und eigene Freigabe. |
| DEC-DRY-009 | Manuelle Zielordner-Overrides bleiben dabei standardmäßig geschützt. |

### A.10 Upload-Stabilität (DEC-STB)

| ID | Entscheidung |
|---|---|
| DEC-STB-001 | Standard-Mindestalter einer neuen Datei: fünf Minuten, pro Quelle konfigurierbar. |
| DEC-STB-002 | Zwei aufeinanderfolgende Prüfungen mit gleicher Größe und MTime. |
| DEC-STB-003 | Unmittelbar vor Änderungen erneut prüfen. |
| DEC-STB-004 | Unvollständige Uploads dürfen nicht verarbeitet werden. |
| DEC-STB-005 | Zusammengehörige Medienkomponenten werden konsistent behandelt. |

### A.11 Hintergrundverarbeitung (DEC-JOB)

| ID | Entscheidung |
|---|---|
| DEC-JOB-001 | Nextcloud-Events dienen der Änderungserkennung und stellen Aufgaben in eine Queue. |
| DEC-JOB-002 | Kein vollständiges Hashing innerhalb eines Event-Listeners. |
| DEC-JOB-003 | Täglich ein inkrementeller Kontrollscan. |
| DEC-JOB-004 | Manueller vollständiger Neuabgleich möglich. |
| DEC-JOB-005 | Unveränderte Dateien werden nicht erneut gehasht. |
| DEC-JOB-006 | Kleine, wiederaufnehmbare Batches. |
| DEC-JOB-007 | Benutzerbezogene und globale Pausen. |
| DEC-JOB-008 | Faire Ressourcenverteilung zwischen Benutzern. |
| DEC-JOB-009 | Temporäre Fehler erhalten Retry/Backoff. |
| DEC-JOB-010 | Dauerhafte Fehler erscheinen im Prüfbereich. |
| DEC-JOB-011 | Kritische Integritätsfehler pausieren zunächst die betroffene Quelle. |
| DEC-JOB-012 | Systematische Fehler pausieren größere Verarbeitungseinheiten. |
| DEC-JOB-013 | Keine unkontrollierten parallelen Operationen. |

### A.12 Quarantäne, Journal, Rückgängig (DEC-JNL)

| ID | Entscheidung |
|---|---|
| DEC-JNL-001 | Quarantäne ist zunächst logisch, kein physischer Quarantäneordner. |
| DEC-JNL-002 | Der Prüfbereich unterscheidet exakte Duplikate, mögliche Duplikate und Konflikte. |
| DEC-JNL-003 | Einzel- und Stapelentscheidungen mit Filterung. |
| DEC-JNL-004 | Jede Dateioperation ist journalisiert. |
| DEC-JNL-005 | Aktionen sind nur nach Prüfung des aktuellen Zustands rückgängig zu machen. |
| DEC-JNL-006 | „Rückgängig“ ist eine neue, protokollierte Operation. |
| DEC-JNL-007 | Papierkorb-Wiederherstellung nur, solange die Datei tatsächlich wiederherstellbar ist. |
| DEC-JNL-008 | Keine blinden Rollbacks und kein Überschreiben fremder Dateien. |
| DEC-JNL-009 | Manuelle Entscheidungen bleiben nachvollziehbar. |

### A.13 Nextcloud-Integration (DEC-NCI)

| ID | Entscheidung |
|---|---|
| DEC-NCI-001 | Alle regulären Dateioperationen laufen über die Nextcloud-Datei-API. |
| DEC-NCI-002 | Kein direkter Zugriff auf das physische Nextcloud-Datadir als Architekturvoraussetzung. |
| DEC-NCI-003 | Externe Linux-Dateisystemänderungen werden nicht eigenständig erkannt; ggf. ist ein Nextcloud-Dateiscan nötig. |
| DEC-NCI-004 | Datei-ID, Storage-ID und tatsächliche Eigentümerschaft müssen korrekt berücksichtigt werden. |
| DEC-NCI-005 | Von der App selbst ausgelöste Events dürfen keine Verarbeitungsschleifen auslösen. |
| DEC-NCI-006 | Die technische Umsetzung muss gegen die konkret unterstützte Nextcloud-Version überprüft werden. |

### A.14 Produkt (DEC-PRD)

| ID | Entscheidung |
|---|---|
| DEC-PRD-001 | Das Produkt ist eine native Nextcloud-App zur sicheren Organisation und Deduplikation privater Foto- und Videobestände, zentral installiert, pro Benutzer individuell zu konfigurieren und ausdrücklich zu aktivieren. |
| DEC-PRD-002 | Funktionsumfang: Indexierung des Bestands, beliebige Eingangsordner mit unterschiedlichen Importregeln, einmalige Migration historischer Bestände, dauerhafter automatischer Import, SHA-256-Dedup, pHash-Ähnlichkeitserkennung, Mediengruppen, Webfrontend (Dashboard, Quarantäne, Importpläne), benutzerbezogene Hintergrundverarbeitung, Admin-Monitoring ohne Medienzugriff, Operationsjournal mit sicherem Rückgängig. |
| DEC-PRD-003 | Der Import vorhandener Indexdaten des Python-Fotoindexers (SQLite/SHA-256/pHash) ist wünschenswert, aber **kein** MVP-Pflichtfeature. |
| DEC-PRD-004 | Dokumentation auf Deutsch, technische Bezeichner auf Englisch. |
| DEC-PRD-005 | Fachliche Entscheidungen treffen Product Owner und ChatGPT (Jana); Claude ist für Analyse, Architekturvorschläge, Dokumentation und spätere Implementierung verantwortlich. |

---

## B. Technische Vorschläge [T]

Nicht beschlossen. Details und Begründungen in `architecture-overview.md`. Auftrag 1 fordert ausdrücklich, die Vorüberlegungen fachlich zu bewerten und nicht ungeprüft zu übernehmen.

| ID | Vorschlag | Bewertung (Kurzfassung) |
|---|---|---|
| PROP-ARC-001 | Native Nextcloud-App mit PHP-Backend. | Folgt aus der Produktentscheidung; praktisch alternativlos für Zugriff auf Datei-API, Events, Background Jobs. |
| PROP-ARC-002 | Vue/TypeScript-Frontend. | Passt zum Nextcloud-Ökosystem (`@nextcloud/vue`); genaue Major-Version an Zielversion koppeln. |
| PROP-ARC-003 | Nextcloud-Datenbank mit eigenen Tabellen und Migrationen statt separater SQLite. | Stark empfohlen: Transaktionen mit Nextcloud-Zustand, Multi-Prozess-Zugriff durch mehrere Cron-Worker; SQLite wäre Nebenläufigkeits- und Backup-Risiko. Datenbankportabilität (MySQL/MariaDB, PostgreSQL, ggf. SQLite) ist zu klären. |
| PROP-ARC-004 | Background Jobs (Queue-basiert) plus Event-Listener. | Passt zu DEC-JOB-001/002; Durchsatz hängt von der Nextcloud-Cron-Konfiguration ab (→ `job-processing.md`). |
| PROP-ARC-005 | Eigene `occ`-Befehle für Wartung und große Imports. | Sinnvoll für Bulk-Operationen außerhalb des Web-/Cron-Kontexts. |
| PROP-ARC-006 | Getrennte Controller/Routen und Rechte für User-API und Admin-API; Admin-API mit eigenem, bewusst kleinem Datenmodell (nur Zähler/Zustände). | Setzt DEC-USR-009 strukturell durch (keine Medien-Felder in Admin-DTOs, Tests darauf). |
| PROP-ARC-007 | Storage-unabhängige Dateioperationen über `OCP\Files`-Abstraktion. | Entspricht DEC-NCI-001/002. |
| PROP-ARC-008 | Zustandsautomat pro Datei/Operation, Journal als Write-Ahead-Log (Intent vor Ausführung). | Notwendig für Wiederaufnahme nach Abbruch und sicheres Rückgängig. |
| PROP-ARC-009 | Hash-Cache-Schlüssel `(file_id, size, mtime, etag)` zur Erkennung unveränderter Dateien. | Umsetzung von DEC-JOB-005; Grenzen siehe `performance.md`. |
| PROP-ARC-010 | Eigene Self-Origin-Markierung für App-Operationen (Operations-ID/Token im Journal, Abgleich im Event-Listener). | Mechanismus für DEC-NCI-005. |

---

## C. Noch zu entscheiden [O]

Vollständige Liste mit Priorität und Vorschlag: `open-questions.md`. Entscheidungen, die vor dem Datenmodell nötig sind:

- `OQ-001` Neuzuordnung bei höherer Priorität vs. „nicht ungefragt neu organisieren“
- `OQ-002` Zielplattform: minimal unterstützte Nextcloud-/PHP-Version, Datenbanken
- `OQ-003` Identität eines „Medium“ vs. „Datei“ (Content Identity, Tombstones)
- `OQ-004` Komponentenweise Dedup vs. Gruppen-Konsistenz
- `OQ-005` Papierkorb-Schnittstelle (nicht-öffentliche API)
- `OQ-006` Erkennung manueller Verschiebungen/Umbenennungen (Events vs. Scan)
- `OQ-007` Quellenpriorität vs. Datumsstrategie

---

## D. Erkannte Widersprüche und Spannungen (nicht eigenmächtig aufgelöst)

| ID | Beschreibung | Betrifft | Verweis |
|---|---|---|---|
| CONFLICT-001 | Automatische Neuzuordnung bei höher priorisierter Herkunft vs. „Bereits importierte Medien werden nicht ungefragt neu organisiert“. | DEC-DAT-010 ↔ DEC-DRY-007 | OQ-001 |
| CONFLICT-002 | Komponentenweise Dedup vs. einheitlicher Basisname/Zielort pro Gruppe, wenn nur eine Komponente ein Duplikat ist. | DEC-GRP-008 ↔ DEC-GRP-009, DEC-NAM-010 | OQ-004 |
| CONFLICT-003 | „Höchstens ein kanonisches Exemplar pro SHA-256“ vs. „gelöschte kanonische Datei wird nicht ersetzt“: Verhalten bei erneutem Auftauchen desselben Inhalts ist nicht definiert (Wiederimport? Tombstone?). | DEC-DUP-002 ↔ DEC-GRP-014 | OQ-003 |
| CONFLICT-004 | „Kamera/EXIF gewinnt gegenüber Messenger“ ist eine Aussage über die Quelle, zugleich aber über die Datumsstrategie; Priorität ist laut DEC-DAT-008 konfigurierbar. | DEC-DAT-008 ↔ DEC-DAT-009 | OQ-007 |
| CONFLICT-005 | Manuelle Umbenennung wird „in das Schema integriert“, Nummern werden aber „nie umnummeriert“; Konsequenz für Dateien, die der Benutzer außerhalb des Schemas benennt, ist offen. | DEC-NAM-006 ↔ DEC-NAM-009 | OQ-011 |
| CONFLICT-006 | Papierkorb-Verschiebung und -Wiederherstellung sollen über „unterstützte“ APIs laufen; die Papierkorb-Verwaltung der Nextcloud ist nach aktuellem Kenntnisstand keine öffentliche `OCP`-Schnittstelle (zu verifizieren). | DEC-DUP-005, DEC-JNL-007 ↔ DEC-NCI-001 | OQ-005 |
| CONFLICT-007 | Quarantäne ist „logisch“ (Datei bleibt liegen), die Zielstruktur-Regel erlaubt aber nur ein kanonisches Exemplar; ob quarantänisierte Dateien im Eingang erneut als „neu“ erkannt werden (z. B. beim Neuscan) muss durch Zustandsmodell ausgeschlossen werden. | DEC-JNL-001 ↔ DEC-JOB-005 | data-model.md (später) |

---

## D.2 Status der Widersprüche nach Runde 2

| ID | Status | Begründung / Rest |
|---|---|---|
| CONFLICT-001 | **Fachlich aufgelöst** (PO-Klarstellung OQ-001 → DEC-DAT-011, DEC-DRY-012) | Reassign erlaubt gemäß zuvor freigegebener Regel; Regeländerung erfordert neuen Dry-Run. Rest: ob Prioritätsänderung selbst eine Regeländerung ist (Vorschlag: ja, relational, `architecture-overview.md` §8.3) → OQ-023. |
| CONFLICT-002 | **Fachlich aufgelöst** (OQ-004 → DEC-GRP-015) | Komponenten haben eigene Inhaltsidentität, Dedup komponentenweise, Gruppen bleiben konsistent verwaltet. Rest: genaue Regeln für „eindeutig passend“ (Gruppierung) und Verhalten bei teils doppelter Gruppe → Detailentwurf in `data-model.md`/`job-processing.md`. |
| CONFLICT-003 | **Fachlich aufgelöst im Grundsatz** (OQ-003 → DEC-DUP-008), Modell offen | Tombstone-Semantik beschlossen; Modellierung (Granularität, Aufhebung) noch zu entwerfen. |
| CONFLICT-004 | **Fachlich aufgelöst** (OQ-007 → DEC-DAT-011) | Höchste berechtigte Priorität bestimmt Herkunft, deren Datumsstrategie das Ablagedatum. „Kamera gewinnt“ ist Folge der Standardprioritäten. Rest: Begriff „berechtigt“ definieren (Quelle aktiv, freigegeben, nicht pausiert). |
| CONFLICT-005 | **Weiterhin offen** | Betrifft OQ-011 (Teilfragen a–d). Technischer Vorschlag in `architecture-overview.md` §8.1; Auslegung „Nummer gehört zum Namen in dieser Phase“ benötigt Freigabe. |
| CONFLICT-006 | **Technisch präzisiert, weiterhin offen** | Löschen über öffentliche API darstellbar, aber Papierkorb-Verhalten nicht aus OCP belegt (Spike). Wiederherstellen: nur über Interna (`OCA\Files_Trashbin\Trash\ITrashManager`, Klasse 3) oder WebDAV (für externe Clients). Empfehlung OQ-032 (angeleitete Wiederherstellung im MVP). Betrifft DEC-JNL-007 („tatsächlich wiederherstellbar“): erfüllbar durch Prüfung, nicht durch automatische Wiederherstellung. |
| CONFLICT-007 | **Zur Modellierung verschoben** | Zustandsmodell muss „logisch quarantänisiert“ als dauerhaften Dateizustand führen (kein Reimport bei Neuscan). Durch Tombstone und Hash-Cache-Signatur adressierbar; Entwurf in `data-model.md`. |

---

## E. Änderungen Runde 2

### E.1 Neue verbindliche Entscheidungen [V] (aus Auftrag 2, Abschnitt 2)

Bestehende IDs bleiben unverändert. Die folgenden **präzisieren** frühere Entscheidungen; die jeweils präzisierte ID ist angegeben.

| ID | Entscheidung | Präzisiert | Löst |
|---|---|---|---|
| DEC-DAT-011 | Die höchste **berechtigte** Quellenpriorität bestimmt die bevorzugte Herkunft. Die konfigurierte Datumsstrategie dieser Quelle bestimmt das Ablagedatum. Kamera: Aufnahmezeit aus EXIF oder Videometadaten, sonst MTime. Messenger: MTime als Empfangsdatum. | DEC-DAT-001/002/008/009 | OQ-007, CONFLICT-004 |
| DEC-DAT-012 | Manuelle Zielordner-Overrides (`location_override`) haben Vorrang vor automatischer Neuorganisation (inkl. Reassign und „Sammlung neu organisieren“). | DEC-DAT-010, DEC-GRP-012, DEC-DRY-009 | OQ-001, OQ-007 |
| DEC-DRY-010 | Bereits in der organisierten Sammlung vorhandene Medien werden **indiziert, nicht erneut importiert**. | – | OQ-030 |
| DEC-DRY-011 | Automatische Neuzuordnung vorhandener Medien ist nur gemäß bereits freigegebener Quellenpriorität erlaubt. Eine allgemeine Neustrukturierung erfordert den gesondert genehmigten Reorganisationsplan (DEC-DRY-008). | DEC-DRY-007/008 | OQ-030 |
| DEC-DRY-012 | Eine neue, höher priorisierte Herkunft darf ein bereits einsortiertes Medium gemäß einer **zuvor freigegebenen** Regel umsortieren. Eine nachträgliche Änderung von Sortierregeln erfordert neuen Dry-Run und erneute ausdrückliche Freigabe. | DEC-DAT-010, DEC-DRY-006/007 | OQ-001, CONFLICT-001 |
| DEC-DUP-008 | Eine bewusste manuelle Löschung darf weder durch automatische Wiederaufnahme noch durch eine vorhandene Quarantänekopie rückgängig gemacht werden. Es ist eine dauerhafte Löschentscheidung (Tombstone-Semantik) vorzusehen; die Datenmodellierung ist noch zu entwerfen. | DEC-GRP-014, DEC-DUP-002 | OQ-003, CONFLICT-003 |
| DEC-GRP-015 | Komponenten (RAW/JPEG/XMP, Live-Photo-Teile) besitzen **eigenständige Inhaltsidentitäten**; SHA-256-Deduplikation erfolgt komponentenweise; Mediengruppen werden dennoch konsistent verwaltet. Neue Komponenten dürfen eindeutig passenden bestehenden Gruppen hinzugefügt werden; unklare Zuordnungen erfordern manuelle Prüfung. | DEC-GRP-007/008/009 | OQ-004, CONFLICT-002 |

Hinweis: Die Formulierung „berechtigt“ in DEC-DAT-011 ist nicht weiter definiert (OQ-007, Restfrage).

### E.2 Technische Vorschläge Runde 2 [T] (nicht beschlossen)

| ID | Vorschlag | Dokument |
|---|---|---|
| PROP-JNL-001 | Intent-Journal mit Roll-forward-Recovery, `operation_id`, Revalidierung vor Mutation, Gruppenoperation als Eltern-/Kindoperationen. | architecture-overview.md §6 |
| PROP-JNL-002 | Undo ausschließlich als neue Operation mit Zustandsprüfung; Papierkorb-Undo im MVP angeleitet statt automatisch (OQ-032). | architecture-overview.md §6.3, OQ-032 |
| PROP-NUM-001 | Kollisionsnummer: Unique-Constraint-basierte, nie gelöschte Reservierungen je `(user, Zielverzeichnis, Quellenpräfix, Sekunde)`, gruppenweit; Nummer wird bei Ausführung (nicht im Dry-Run) endgültig reserviert. | architecture-overview.md §8.1 |
| PROP-TIME-001 | Getrennte Zeitfelder: Rohwert, Zeitquelle, optionaler Offset, lokale Sortierzeit (naiv), optionaler UTC-Instant, Basis, genutzte Benutzerzeitzone. | architecture-overview.md §8.2 |
| PROP-RULE-001 | Regel-Fingerprint pro Quelle aus relevanten Bestandteilen und abhängigen globalen Einstellungen; Invalidierung nur bei Fingerprint-Änderung; Priorität paarweise relational. | architecture-overview.md §8.3 |
| PROP-EVT-001 | Scan als Wahrheit, Events als Beschleuniger; Selbst-Event-Erkennung per Journal-Abgleich (Operations-ID/Sollzustand). | architecture-overview.md §8.4 |
| PROP-TOOL-001 | Capability-Abstraktion für Metadaten/pHash: PHP-Basis, optionale externe Provider (ExifTool, ffprobe, Imagick), Probe zur Laufzeit, keine Importblockade bei Fehlen. | architecture-overview.md §7 |
| PROP-LEASE-001 | App-eigene DB-Lease je Benutzer/Quelle für Worker-Exklusivität. | architecture-overview.md §6.7 |

### E.3 Korrekturen an Runde 1
- `nextcloud-integration.md`: Alle Zeilen neu klassifiziert (1–4); die in Runde 1 mit „?“ markierten Einträge sind jetzt – wo möglich – gegen `master`-Quelltext belegt. **Unverändert gilt: keine Zielversion verifiziert.**
- Die in Runde 1 formulierte Vermutung zum Papierkorb (kein stabiler OCP-Zugang) ist bestätigt: `ITrashManager` liegt im Namensraum `OCA\Files_Trashbin` ohne Public-API-Markierung (Quelle: `apps/files_trashbin/lib/Trash/ITrashManager.php`).
- Runde 1 nannte `setAllowParallelRuns` nicht; laut Entwicklerdokumentation gilt es seit Version 27 (nicht in `IJob`, sondern an der Basisklasse – nicht eigenständig geprüft).
- Neu: Produktidentität (Verzeichnis heißt `owncloud`) ist zu bestätigen (`environment.md`).

---

## F. Änderungen Runde 2b (Architektur-Review)

Quelle: Auftrag 2b, Rückmeldung von Product Owner und Jana. Alle Einträge in F.1 sind **[V]**. Bestehende IDs bleiben unverändert; Präzisierungen verweisen auf die betroffene ID. Einträge in F.2 sind Vorschläge **[T]**.

### F.1 Neue verbindliche Entscheidungen [V]

#### Produkt und Architekturleitlinie (DEC-PRD / DEC-ARC)

| ID | Entscheidung | Präzisiert |
|---|---|---|
| DEC-PRD-006 | Photo Organizer wird eine **allgemein nutzbare, veröffentlichungsfähige** Nextcloud-App. | DEC-PRD-001 |
| DEC-PRD-007 | Die Installation des Product Owners (Nextcloud 34.0.4, PHP 8.3.14, MySQL, …; `environment.md`) ist **nur erste Test- und Referenzumgebung**, nicht Grundlage installationsspezifischer Architekturentscheidungen. | – |
| DEC-ARC-001 | Öffentliche OCP-APIs werden bevorzugt; Abhängigkeiten von Nextcloud-internen Klassen nur, wenn keine öffentliche Alternative existiert, und dann gekapselt. | DEC-NCI-001 |
| DEC-ARC-002 | Nextcloud-Datenbankabstraktion (Query Builder/Schema), keine MySQL-spezifische Implementierung. | PROP-ARC-003 |
| DEC-ARC-003 | Keine absoluten Linux-Pfade werden vorausgesetzt (weder im Code noch in Konfigurationsannahmen). | DEC-NCI-002 |
| DEC-ARC-004 | Nextcloud-eigene Background Jobs werden verwendet. | PROP-ARC-004 |
| DEC-ARC-005 | Optionale Medienwerkzeuge werden ausschließlich über Capability Detection eingebunden. | PROP-TOOL-001 |
| DEC-ARC-006 | Fehlende optionale Fähigkeiten dürfen nicht die gesamte App blockieren. | DEC-GRP-005 |
| DEC-ARC-007 | Versionsabhängige Integrationen werden gekapselt (Adapter). | RISK-VER |
| DEC-ARC-008 | Installation und Betrieb sollen möglichst ohne zusätzliche manuelle Serverkonfiguration möglich sein. | – |
| DEC-ARC-009 | In der Dokumentation wird strikt zwischen **auf der Referenzinstallation nachgewiesenen Fähigkeiten** und **allgemeingültig unterstützten Nextcloud-APIs** unterschieden. | – |

#### Berechtigung und Pausen (OQ-036)

| ID | Entscheidung | Präzisiert |
|---|---|---|
| DEC-DAT-013 | Es werden zwei Eigenschaften getrennt: **`processing_eligible`** (darf die Quelle *aktuell* verarbeitet werden?) und **`provenance_eligible`** (darf ihre *dokumentierte Herkunft* bei der Bestimmung des kanonischen Ablageortes berücksichtigt werden?). Eine Benutzer-, Quellen-, Admin- oder globale Pause darf **niemals** die historische Herkunftsbewertung verändern. | DEC-DAT-011, DEC-USR-006/007 |

#### Tombstones (OQ-003)

| ID | Entscheidung | Präzisiert |
|---|---|---|
| DEC-DUP-009 | Eine bewusste manuelle Löschung wird **niemals** durch späteren Import oder eine vorhandene Quarantänekopie automatisch rückgängig gemacht. Ein einfacher Tombstone je `(user_id, sha256)` genügt für die vollständige Semantik nicht; zu berücksichtigen sind Löschereignis, bisherige kanonische Datei, Inhaltsidentität, Herkunft, Mediengruppe und manuelle Wiederzulassung. Später erneut auftauchende identische Inhalte dürfen einen **Prüffall** erzeugen, werden aber weder automatisch importiert noch automatisch gelöscht. Die Datenmodellierung bleibt Auftrag 3. | DEC-DUP-008 |

#### Namen (OQ-010/OQ-011)

| ID | Entscheidung | Präzisiert |
|---|---|---|
| DEC-NAM-011 | Bei **eindeutig** automatisch generierten Originalnamen wird der Originalname nicht an den Standardnamen angehängt. Bei **unsicherer** Erkennung bleibt die Originalbeschreibung erhalten. | DEC-NAM-003/004 |
| DEC-NAM-012 | Manuelle Änderungen am technischen Präfix oder an der Kollisionsnummer werden **nicht blind korrigiert**; unklare Fälle gehen in die Prüfung. | DEC-NAM-006/009 |
| DEC-NAM-013 | Atomare Nummernreservierung mit niemals wiederverwendeten Nummern ist akzeptierte Architekturgrundlage. Dry-Run-Nummern dürfen **vorläufig** sein. Bei der Ausführung dürfen dadurch **keine stillschweigenden Änderungen** an Datumsstrategie, Herkunft, Zielordner oder persönlicher Beschreibung entstehen. Gleichzeitige Importe und nachträglich ergänzte Mediengruppen sind zu berücksichtigen. | DEC-NAM-008/009/010 |

#### Freigaben und Regelversionierung (OQ-023)

| ID | Entscheidung | Präzisiert |
|---|---|---|
| DEC-DRY-013 | Relevante Konfigurationsänderungen berücksichtigen auch **noch nicht ausgeführte historische Importpläne**. Es wird unterschieden zwischen (a) erfolgreich abgeschlossenen Operationen, (b) freigegebenen, aber noch nicht ausgeführten Plan-Items und (c) dauerhaft freigegebenen Incoming-Regeln. Die Invalidierung erfolgt **selektiv** anhand von Regelversionen bzw. Fingerprints. | DEC-DRY-003/004/006/007 |
| DEC-DRY-014 | Eine **Quellenpause ist keine relevante Regeländerung** und löst keine Neuzuordnung aus. | DEC-DRY-006, DEC-DAT-013 |

#### Integrität und Dateioperationen

| ID | Entscheidung | Präzisiert |
|---|---|---|
| DEC-IMM-006 | Es wird unterschieden zwischen (a) „keine bewusste Inhaltsänderung durch Anwendungscode“ und (b) tatsächlicher Ende-zu-Ende-Integrität bei Storage-Operationen. Ein Move ist nicht automatisch transaktional. Intent-Journal, Roll-forward und Eltern-/Kindoperationen werden beibehalten (`PROP-JNL-001`). | DEC-IMM-001/003 |
| DEC-IMM-007 | Externe und Storage-übergreifende Operationen sind **vorerst außerhalb des MVP**, solange ihre Sicherheit nicht nachgewiesen ist. | DEC-USR-003, DEC-NCI-004 |

#### Datum und Papierkorb

| ID | Entscheidung | Präzisiert |
|---|---|---|
| DEC-DAT-014 | **Keine pauschale Altersuntergrenze** für historische Fotos. Auffällige Datumswerte werden lediglich **gekennzeichnet**. | DEC-DAT-001, OQ-013 |
| DEC-DAT-015 | Originalwert, Zeitquelle, Offset und lokale Sortierzeit werden **getrennt** vorgesehen. | DEC-DAT-004/005/007 |

### F.2 Technische Vorschläge Runde 2b [T] (noch nicht beschlossen)

| ID | Vorschlag | Dokument |
|---|---|---|
| PROP-JNL-003 | Manuell angeleitete Papierkorb-Wiederherstellung im MVP ist als technischer Vorschlag **akzeptiert** (Feedback Auftrag 2b), jedoch weiterhin Vorschlag; automatische Wiederherstellung erst nach API-Verifikation. | OQ-032 |
| PROP-VER-001 | Unterstützter Versionsbereich: technischer Boden 29, Entwicklungsziel 34, Veröffentlichungsbereich = zum Release-Zeitpunkt gepflegte Hauptversionen (noch zu bestimmen). | environment.md §3, OQ-037 |
| PROP-ELIG-001 | Trennung `processing_eligible` / `provenance_eligible` mit Herkunftsbewertung anhand der zum Importzeitpunkt freigegebenen Regelversion. | architecture-overview.md §9 |
| PROP-TOMB-001 | Löschentscheidung als Ereignis mit Verweis auf Inhaltsidentität, Datei, Herkunft, Gruppe und Wiedereintritt (kein einzelnes Flag). Anforderungsliste, kein Schema. | architecture-overview.md §10 |
| PROP-RULE-002 | Selektive Invalidierung auf Ebene von Regelkomponenten-Fingerprints; Plan-Items erfassen die tatsächlich verwendeten Komponenten. | architecture-overview.md §11 |
| PROP-NUM-002 | Plan-gebundene Eingaben (Datum, Herkunft, Zielordner, Beschreibung) werden bei Ausführung neu berechnet; Abweichung → Plan-Item veraltet statt stiller Änderung; nur die Kollisionsnummer darf abweichen. | architecture-overview.md §12 |
| PROP-INT-001 | Nach jeder Mutation Nachzustandsprüfung per `file_id`; kein Vertrauen auf Rückgabewert allein. | architecture-overview.md §13 |

---

## D.3 Status der Widersprüche nach Runde 2b

IDs unverändert. „Neu“ kennzeichnet Änderungen gegenüber D.2.

| ID | Status | Begründung |
|---|---|---|
| CONFLICT-001 | Aufgelöst (unverändert) | DEC-DAT-011, DEC-DRY-012. Neu: Prioritätsänderung ist relevante Regeländerung nur für Quellen mit geänderter relativer Rangfolge (Vorschlag, PROP-RULE-002); Pausen verändern nichts (DEC-DAT-013/DEC-DRY-014). |
| CONFLICT-002 | Aufgelöst (unverändert) | DEC-GRP-015. Rest: Gruppierungsregeln im Datenmodell. |
| CONFLICT-003 | **Fachlich aufgelöst; Modellierung Auftrag 3** (neu) | DEC-DUP-008/009: Tombstone-Semantik ist mehr als `(user_id, sha256)`; Anforderungen in architecture-overview.md §10. |
| CONFLICT-004 | **Aufgelöst** (neu) | DEC-DAT-011 und DEC-DAT-013 (Definition der Berechtigung als zwei getrennte Eigenschaften). |
| CONFLICT-005 | **Fachlich aufgelöst** (neu) | DEC-NAM-012 (keine blinde Korrektur, Prüffall) und DEC-NAM-013 (Nummernreservierung akzeptiert). |
| CONFLICT-006 | **Fachlich entschärft, technisch offen** (neu) | Manuell angeleitete Wiederherstellung im MVP akzeptabel (PROP-JNL-003); automatisch erst nach API-Verifikation. Weiterhin: Papierkorb-Verhalten von `Node::delete()` und `file_id` nach Trash per Spike zu verifizieren. |
| CONFLICT-007 | **Zur Modellierung verschoben** (unverändert) | Zustandsmodell Auftrag 3. |

---

## G. Runde 3 (Datenmodell) – nur Vorschläge, keine neuen verbindlichen Entscheidungen

Auftrag 3 enthält Architekturvorgaben, die über die bestehenden `DEC-…` hinausgehen. Sie sind **nicht** als verbindlich übernommen, sondern als **zu bestätigende Vorschläge** erfasst (`PROP-DM-…`). Verbindlich bleibt ausschließlich Abschnitt A, E.1 und F.1.

| ID | Vorschlag (aus Auftrag 3, soweit nicht anders vermerkt) | Verweis |
|---|---|---|
| PROP-DM-001 | Eigene unveränderliche interne Dateiidentität (`po_file_instance.id`); Nextcloud‑Datei‑ID, Storage und Pfad sind veränderliche externe Referenzen mit Historie; Architektur darf nicht auf `file_id`‑Erhalt beruhen; Wiedererkennung nur bei ausreichender Sicherheit. | OQ-039, data-model.md §2, §5.3 |
| PROP-DM-002 | Tombstone‑Modell als Ereignisgeflecht (Löschereignis → Tombstone → Wiederauftauchen/Wiederzulassung); Tombstone nur aus bestätigter Löschung des kanonischen Originals; Entfernung zusätzlicher Kopien und Verlust erzeugen keinen inhaltsweiten Tombstone. (Konkretisiert DEC-DUP-009.) | OQ-040, data-model.md §6.7–6.8 |
| PROP-DM-003 | Herkunftsereignisse referenzieren unveränderliche Regel‑Snapshots; Quellen werden archiviert statt gelöscht; wirksame Herkunftspriorität = Priorität der zuletzt freigegebenen Quellenrevision. (Konkretisiert DEC-DAT-013.) | OQ-041, OQ-038 |
| PROP-DM-004 | Zwei Revisionsebenen (gemeinsame Regeln, Quellenregeln) mit effektiven Komponenten‑Fingerprints (`date`, `naming`, `target`, `group`, `scope`, `priority_ctx`); Scheduling‑Parameter nicht fingerprintet. (Konkretisiert DEC-DRY-013/014.) | OQ-042, OQ-023 |
| PROP-DM-005 | Gruppen‑Evidenzstufen (1 Metadaten, 2 kompatible Kombination, 3 manuell); mehrdeutig → Prüfung; Gruppen erweiter‑, trenn‑ und korrigierbar. (Konkretisiert DEC-GRP-002/003.) | OQ-045 |
| PROP-DM-006 | Zeitmodell mit Feldern Rohwert, Zeitquelle, Offset, UTC, lokale Sortierzeit, IANA‑Zone, Zuverlässigkeit, manuelle Korrektur; EXIF mit Offset behält lokale Uhrzeit; MTime nach Benutzerzeitzone mit Fallbackkette (App → Profil → Nextcloud‑Standard → UTC). (Konkretisiert DEC-DAT-015.) | OQ-012, OQ-044 |
| PROP-DM-007 | Nummernreservierung: Namensraum `(Benutzer, Zielverzeichnis, Quellenpräfix, lokale Sekunde)` mit restriktivster Normalisierung; mindestens zweistellig, beliebig erweiterbar; vorläufige Plan‑Nummern getrennt von verbindlichen Reservierungen. (Konkretisiert DEC-NAM-013.) | OQ-043, OQ-047 |
| PROP-DM-008 | Gemeinsamer Inhalt in zwei Mediengruppen: Konfliktstatus, keine automatische Verdopplung/Löschung, Optionen O1–O5 zur manuellen Auflösung. | OQ-048, CONFLICT-008 |
| PROP-DM-009 | Plattformfokus Nextcloud **34 und 35**, Erweiterung auf 33 nach Tests; Versionsmatrix später mit Kompatibilitätstests verbindlich. **Überholt PROP-VER-001** (Boden 29 / „gepflegte Hauptversionen“). | OQ-037, environment.md §3 |
| PROP-DM-010 | Keine hartcodierten Pfade, keine MySQL‑spezifischen SQL‑Konstrukte, keine Annahmen über Werkzeuge der Referenzinstallation (bereits verbindlich als DEC-ARC‑002/003/005 – hier nur bestätigt). | – |

## D.4 Neue Konflikte (Runde 3)

| ID | Beschreibung | Status | Verweis |
|---|---|---|---|
| CONFLICT-008 | Gemeinsamer (byteidentischer) JPEG in zwei Mediengruppen vs. höchstens ein kanonisches Exemplar je Inhalt (DEC-DUP-002) vs. einheitlicher Basisname/Zielort je Gruppe (DEC-NAM-010, DEC-GRP-009). Beispiel: Gruppe A = RAW‑A + JPEG‑X, Gruppe B = RAW‑B + dasselbe JPEG‑X. | **Offen**, Modell erlaubt manuelle Prüfung (Optionen O1–O5) | data-model.md §9, OQ-048, Szenario 10 |
| CONFLICT-009 | Tombstone verhindert Wiederimport (DEC-DUP-009) vs. Rückholung der gelöschten Datei durch den Benutzer aus dem Nextcloud‑Papierkorb: Ist die Rückkehr „Wiederauftauchen“ (Prüffall) oder darf sie automatisch als Wiederzulassung gelten? | **Offen** | OQ-046 |
| CONFLICT-010 | Wirksame Herkunftspriorität: am Ereignis eingefroren vs. zuletzt freigegebene Quellenrevision (beide vereinbar mit DEC-DAT-011, unterschiedliche Sortierergebnisse nach Prioritätsänderung). | **Offen** | OQ-041 |

Status der CONFLICT‑001 bis ‑007: unverändert gegenüber D.3. CONFLICT‑003 ist durch das Löschereignis‑/Tombstone‑Modell (data-model.md §6.7–6.8) **modelliert**, die Bestätigung der Vorschläge PROP-DM-002 steht aus.


---

## H. Runde 3b (Review des Datenmodells) – nur Vorschläge

Auftrag 3b ändert **keine** verbindliche Entscheidung. Alle Punkte sind zu bestätigende Vorschläge (`PROP-DM-011` ff.). Bestehende IDs bleiben erhalten. `PROP-DM-003` wird in der Passage „wirksame Herkunftspriorität = Priorität der zuletzt freigegebenen Quellenrevision“ durch `PROP-DM-012` **ersetzt** (die übrigen Teile von `PROP-DM-003` bleiben); `PROP-DM-008` wird durch `PROP-DM-018` konkretisiert; `PROP-DM-007` wird bezüglich der Nummernformatierung durch `PROP-DM-019` korrigiert.

| ID | Vorschlag | Verweis |
|---|---|---|
| PROP-DM-011 | **Scan-Scope als eigene Entität** (`po_scan_scope`): ein Scope je Eingangsquelle und ein Sammlungs-Scope je Benutzer. Provenance, Scan-Cursor, Jobs und Referenzen hängen am Scope; `source_id` wird nullable. Sammlungsindexierung ohne Importvorgang, ohne Plan-Items, ohne Dateiänderung; täglicher Abgleich und manueller Vollscan als `scan_kind`. Verworfen: interne Sammlungsquelle in `po_source`. | data-model.md §15, OQ-051 |
| PROP-DM-012 | **Persistente Herkunftsentscheidung** (`po_origin_decision`); Provenance bleibt unverändert; Konfigurationsänderungen ordnen nichts um; Neuzuordnung nur durch neues Herkunftsereignis mit strikt höherer eingefrorener Priorität nach freigegebener Regel, durch freigegebenen Plan oder manuell; Standort-Override hat Vorrang; Gleichstand ändert nichts. Offen: Vergleichsbasis (Variante F eingefroren / Variante C aktueller Rang). | data-model.md §16, OQ-041, CONFLICT-010 |
| PROP-DM-013 | **Fencing-Modell mit ehrlichen Grenzen:** Beanspruchung der Operation per bedingtem Update (Lease-Bedingung, Frist), Aktivschlüssel je Instanz, Ziel im Intent, Prüfung vor und nach dem Storage-Aufruf, Nextcloud-Sperre nur als zusätzliche Schicht, Abbruch nur vor Beanspruchung. Das Token schützt nur Datenbankschreibvorgänge; Restfenster sind benannt und nicht schließbar. | data-model.md §17, OQ-055 |
| PROP-DM-014 | **Integritätsstrategie:** Metadaten sind nur Hinweise; `hash_verified` erst nach Neuhash des Ziels; Operationsklassen `rename_same` / `copy_possible` / `unknown`; Recovery-Abschlüsse zählen als `copy_possible`; Hash-Bestätigung der kanonischen Instanz vor jeder Duplikat-Papierkorb-Aktion; Tabelle `po_integrity_check`. (Konkretisiert DEC-IMM-006.) | data-model.md §18, OQ-052 |
| PROP-DM-015 | **pHash-Versionierung:** `po_phash_algo` (Algorithmus, Version, Bandlayout, Sondierungsradius, Garantieradius); Hashes, Bandzeilen und Befunde versioniert; manuelle Paarentscheidungen algorithmusunabhängig; `hash_hex STRING(16)` statt signed `BIGINT`; `user_num` statt `user_id` im Bandindex; Kandidatenkappung; Benchmarkplan. | data-model.md §19, OQ-054, OQ-016 |
| PROP-DM-016 | **Provenance als Eintrittsepisode:** Unique `(instance_id, scope_id, entry_ref_id)`; `entry_kind` `arrival`/`reentry`/`adoption`; Rückkehr einer verwalteten Datei in den Eingang erzeugt ein Ereignis und eine Prüfaufgabe (konservativer Standard). | data-model.md §20, OQ-053 |
| PROP-DM-017 | **Referenzschlüssel:** `nc_key` als SHA-256 über längenpräfixierte Originalwerte (`storage_key`, `nc_file_id` bleiben getrennte Spalten); Längenprüfung aller zusammengesetzten Schlüssel; `po_override.active_key` auf 64 vergrößert. | data-model.md §21, OQ-056 |
| PROP-DM-018 | **Konfliktmengen werden als Ganzes gehalten:** Bei mehrdeutiger Gruppierung (z. B. gemeinsamer JPEG) kein Teilimport und keine Teiloperationen; Variante „RAW sofort importieren“ nur nach ausdrücklicher Freigabe. | data-model.md §9, OQ-048, CONFLICT-012 |
| PROP-DM-019 | **Nummernformat gehört zur Komponente `naming`** (korrigiert Z8/OQ-043): Änderung der Stellenzahl macht offene Plan-Items und dauerhafte Freigaben mit `naming` ungültig; bestehende Nummern und Namen bleiben. | data-model.md §4.3 |
| PROP-DM-020 | **Redundanzabbau und Konsistenz:** sechs redundante Spalten entfernt oder ersetzt; Mutabilitätsmatrix; einheitliche Freigabe aller `active_key`/`dedup_key` bei terminalen Übergängen; Namen als `TEXT`. | data-model.md §22 |
| PROP-DM-021 | **Adoptierte Sammlungsdateien sind gegen automatische Neuzuordnung geschützt** (`management_mode=adopted`); weitere Kopien in der Sammlung sind `collection_duplicate` und gehören nicht zur Duplikat-Sammelaktion. | data-model.md §15.5, OQ-051, CONFLICT-011 |

## D.5 Konflikte (Runde 3b)

| ID | Beschreibung | Status | Verweis |
|---|---|---|---|
| CONFLICT-008 | Gemeinsamer JPEG in zwei Gruppen. | **Neu gefasst:** konservative Standardbehandlung (gesamte Konfliktmenge im Eingang halten); Optionen O1–O5, Variante P2 nur nach Freigabe. Weiterhin **offen** bis zur Freigabe von PROP-DM-018. | data-model.md §9, OQ-048, Szenario 10 |
| CONFLICT-010 | Wirksame Herkunftspriorität. | **Neu gefasst:** persistente Herkunftsentscheidung (PROP-DM-012) löst den Widerspruch zu DEC-DRY-007; **offen** bleibt die Vergleichsbasis (Variante F / C). | data-model.md §16, OQ-041 |
| CONFLICT-011 | Adoptierte Sammlungsdatei vs. neue höher priorisierte Herkunft: DEC-DAT-011/DEC-DRY-012 (Neuzuordnung nach freigegebener Priorität) gegen DEC-DRY-007/DEC-DRY-010 (Bestehendes nicht ungefragt neu organisieren, Sammlung indizieren). | **Offen**; Vorschlag: adoptierte Dateien bleiben (PROP-DM-021) | OQ-051 |
| CONFLICT-012 | DEC-GRP-005 (fehlende optionale Begleitdatei blockiert nicht) vs. DEC-GRP-003 (unsichere Gruppierung bleibt im Eingang) beim Teilimport einer mehrdeutigen Gruppe. | **Offen**; Vorschlag: DEC-GRP-003 hat Vorrang bei vorhandener, aber mehrdeutiger Begleitdatei | OQ-048 |
| CONFLICT-009 | Tombstone vs. Papierkorb-Rückholung. | unverändert offen | OQ-046 |

Status der übrigen CONFLICT unverändert.

---

## I. Runde 4 (Job-State-Machine) – freigegebene Leitlinien und Vorschläge

### I.1 Vom Product Owner und Jana im Review freigegebene Leitlinien [V]
Übernommen aus Auftrag 4, Abschnitt 2. Geprüft auf Widersprüche in `job-processing.md` §1; Anpassungen sind dort dokumentiert, nicht stillschweigend vorgenommen.

| ID | Entscheidung | Bezug / Anpassung |
|---|---|---|
| DEC-DAT-016 | Herkunftsentscheidung nach **Variante F**: Vergleich mit der eingefrorenen Priorität der bisherigen Herkunftsentscheidung. Konfigurationsänderungen allein lösen keine Neuorganisation aus. | OQ-041, CONFLICT-010 **aufgelöst**; konkretisiert DEC-DAT-011, DEC-DRY-007 |
| DEC-IDX-001 | Reine Sammlungsindexierung nach persönlicher Aktivierung ohne verpflichtenden Import-Dry-Run. Adoptierte Sammlungsdateien sind gegen automatische Umorganisation geschützt. Doppelte Bestandsdateien werden konservativ behandelt (`collection_duplicate`, nicht in der Sammelaktion). | OQ-051, CONFLICT-011 **aufgelöst**; präzisiert DEC-DRY-010; Ausnahme zu DEC-DRY-012 für adoptierte Dateien |
| DEC-IMM-008 | Für App-veranlasste Verschiebungen: vollständige SHA-256-Verifikation **vor und nach** der Operation; erst danach endgültiger Erfolgszustand. | OQ-052; konkretisiert DEC-IMM-006; **ersetzt** den Klassenentwurf PROP-DM-014 für App-Moves |
| DEC-JOB-014 | Leases, Fencing, exklusiver Operationsanspruch und unterstützte Nextcloud-Dateisperren werden kombiniert. Ungeklärte Mutationen werden niemals blind wiederholt. | OQ-055; konkretisiert DEC-JOB-013, DEC-JNL-008; **präzisiert** `architecture-overview.md` §6.3 |
| DEC-GRP-016 | Mehrdeutige Mediengruppen bleiben zunächst vollständig unverändert. | OQ-048, CONFLICT-008/012 **aufgelöst** (DEC-GRP-003 hat Vorrang vor DEC-GRP-005 bei vorhandener, aber mehrdeutiger Komponente); konkretisiert DEC-GRP-003 |
| DEC-GRP-017 | Rückkehr einer verwalteten Datei in einen Eingangsordner erzeugt einen Prüffall, keinen automatischen erneuten Import. | OQ-053; PROP-DM-016 bestätigt |
| DEC-PHA-008 | Standard-pHash-Distanz 8, konfigurierbar. Eine begrenzte Kandidatensuche darf nicht als vollständiges Ergebnis ausgegeben werden. | OQ-054; konkretisiert DEC-PHA-003/004 |

Nicht abschließend entschieden bleiben aus OQ-054: Kappungsgrenze und Benchmark-Akzeptanzkriterien (Implementierungsphase). Aus OQ-055: Zahlenwerte der Sicherheitsabstände.

### I.2 Vorschläge aus Runde 4 [Z]
| ID | Vorschlag | Verweis |
|---|---|---|
| PROP-SM-001 | Transaktionsgrundsätze T1–T9: keine Transaktion um Storage-Aufrufe oder Hashes; bedingte Übergänge; Intent vor Mutation; „executing“ = möglicherweise ausgeführt; Beobachten statt Annehmen; kein automatischer Rollback. | job-processing.md §2 |
| PROP-SM-002 | Dreizehn Zustandsautomaten M1–M13 mit Vokabular, Übergängen, Persistenz, Transaktionsgrenzen, Idempotenz, Fehler- und Recovery-Verhalten. | job-processing.md §5 |
| PROP-SM-003 | Operationsablauf P0–P14 mit Pre-Hash vor dem Claim, Claim mit Lease-Fence, Nextcloud-Sperre, `executing` vor dem Storage-Aufruf, Beobachtung, Post-Hash, Abschluss-TX. | job-processing.md §6 |
| PROP-SM-004 | Jobklassen Q/R/M, globale Slots, ein mutierender Worker je Benutzer, Fairness nach Kosten, Lease-Heartbeat an Einheitsgrenzen, fortsetzbare Hash-Einheiten. | job-processing.md §3 |
| PROP-SM-005 | Recovery: Ruhefrist, Übernahme mit neuem Fence, Beobachtungsmatrix, derselbe Intent, Gruppenfortsetzung nur bei erfüllten Bedingungen, kein Rollback. | crash-recovery.md §3, §5.1 |
| PROP-SM-006 | Live-Photo-Konfliktstrategie: Nachziehen nur mit gültigem `pre_state`; bei manueller Änderung der Zielkomponente abbrechen, Prüfaufgabe, Gruppe `needs_review`. | crash-recovery.md §5.2 |
| PROP-SM-007 | Korrektur K1: Eintrittsepisode `entry_seq` statt `entry_ref_id` als Provenance-Schlüssel. | data-model.md §23.1, job-processing.md §4.5 |
| PROP-SM-008 | Holds (kritisch/systematisch) als eigene Spalten, getrennt von Pausen. | data-model.md §23.5, OQ-061 |
| PROP-SM-009 | Pausen: Events weiter einreihen; Recovery darf lesend beobachten, nicht fortsetzen. | OQ-058 |
| PROP-SM-010 | `search_state` für pHash-Suchergebnisse. | data-model.md §23.6 |

### I.3 Konfliktstatus nach Runde 4
| ID | Status |
|---|---|
| CONFLICT-008 | **Fachlich aufgelöst** durch DEC-GRP-016 (Standard); Optionen O1–O5 zur Auflösung im Einzelfall bleiben Benutzerentscheidung |
| CONFLICT-010 | **Aufgelöst** durch DEC-DAT-016 |
| CONFLICT-011 | **Aufgelöst** durch DEC-IDX-001 |
| CONFLICT-012 | **Aufgelöst** durch DEC-GRP-016 |
| CONFLICT-009 | unverändert **offen** (OQ-046) |
| CONFLICT-006 | Technisch weiterhin offen (Papierkorb-/file_id-Verhalten, Spikes SP-B, SP-C) |
| CONFLICT-013 (neu) | `architecture-overview.md` §6.3 („Datei am Vorzustand → wiederholen“) vs. DEC-JOB-014 („niemals blind wiederholen“): **in `crash-recovery.md` §3 aufgelöst** durch Ruhefrist + Beobachtung + neuen Anspruch (Vorschlag PROP-SM-005) |
| CONFLICT-014 (neu) | DEC-JOB-006 (kleine Batches) vs. DEC-IMM-008 (vollständiger Pre-/Post-Hash großer Dateien): aufgelöst durch fortsetzbare Einheiten (PROP-SM-004); Restkosten ≈ 2× Lesen je Operation, zu messen |

---

## J. Runde 4b (Konsistenz- und Sicherheitsreview) – Anforderungen des Auftrags und Vorschläge

### J.1 Im Auftrag 4b ausdrücklich formulierte Anforderungen [V]
Diese Sätze sind als Anforderungen des Product Owners/Jana formuliert und werden als Entscheidungen geführt. **Die technische Umsetzung bleibt Vorschlag** (J.2).

| ID | Entscheidung | Bezug |
|---|---|---|
| DEC-ARC-010 | **Fail-closed bei Mutationen:** Keine mutierende Operation ohne verifiziertes Verhalten bezüglich Zielkollisionen. Die automatische Organisation überschreibt niemals einen bestehenden Zielknoten. Eine Konfiguration ohne ausreichend sicheres Move-Verhalten darf indexieren und analysieren, aber nicht automatisch organisieren. | DEC-JNL-008, DEC-JOB-014 |
| DEC-DUP-010 | Vor jeder vom Benutzer bestätigten Duplikat-Papierkorbaktion werden das zusätzliche **und** das kanonische Exemplar vollständig erneut gehasht; Inhaltsidentität, Gruppenschutz, Tombstones und Benutzerentscheidungen werden geprüft; bei jeder Abweichung wird die Aktion verweigert und ein Prüffall erzeugt. Ein älterer Datenbankstatus `hash_verified` genügt nicht. Keine automatische Löschung. | DEC-DUP-005/006/007, DEC-IMM-008 |
| DEC-IMM-009 | `needs_review → done` umgeht niemals die verbindliche Pre-/Post-SHA-256-Verifikation. | DEC-IMM-008 |
| DEC-IMM-010 | Ein aus einzelnen Chunks berechneter alternativer Hash wird nicht als SHA-256 der vollständigen Originaldatei ausgegeben. | DEC-DUP-001 |
| DEC-JNL-010 | Undo verspricht nichts wiederherzustellen, dessen ursprünglicher Wert unbekannt ist. Unterscheidung: nachweislich beobachtet / nur vermutet / nicht mehr rekonstruierbar. | DEC-JNL-005/006 |
| DEC-JOB-016 | Pausensemantik: Benutzerpause setzt neue reguläre Verarbeitung aus (notwendige rein lesende Recovery-Beobachtung nach definierten Regeln erlaubt); Quellenpause analog für die Quelle; Admin-Pause eines Benutzers: keine neuen regulären Arbeiten dieses Benutzers; globale Admin-Pause = Wartungsmodus (nach sicherem Abschluss begonnener Einheiten keine weiteren Storage-Zugriffe der App; Ereignisse nur minimal ohne Storage-Zugriff; danach Kontrollscan). Pausen verändern weder Quellenprioritäten noch Regel-Fingerprints. | DEC-USR-006/007, DEC-DAT-013, DEC-DRY-014 |

Klarstellungen: Die Wahl der **Reihenfolge** der Operationszustände (`intended → preverified → claimed → executing`) ist ein Konsistenzentscheid des Entwurfs (Vorschlag PROP-SM-011), keine fachliche Entscheidung.

### J.2 Vorschläge aus Runde 4b [Z]
| ID | Vorschlag | Verweis |
|---|---|---|
| PROP-SM-011 | Endgültige Operationsreihenfolge `intended → preverified → claimed → executing → executed → verifying → done`; Übergänge T1–T21 mit Worker, Fence, Transaktion, Lease-Verlust, Wiederholbarkeit; `closed_unverified` als manueller Ausgang ohne Erfolg. | job-processing.md §5.8 |
| PROP-SM-012 | Gemeinsamer Mutationskern, sechs Operationstypen (`import`, `reassign`, `group_follow`, `reorganize`, `undo`, `trash`), Autorisierungsmatrix, adoptierte Dateien nur über freigegebene Reorganisation. | job-processing.md §6.1–6.2 |
| PROP-SM-013 | Fähigkeitsmodell und Fail-closed-FileGateway; Allowlist getesteter (Version, Storage)-Tupel; Modus „Nur Indexierung und Analyse“; Spikes SP-A1…A6, SP-I, SP-J, SP-E erweitert. WebDAV-`Overwrite: F` nur Vergleichspunkt. | job-processing.md §6.3 |
| PROP-SM-014 | Pre-Delete-Prüfung beider Exemplare unmittelbar vor dem Papierkorb-Aufruf. | job-processing.md §6.4 (setzt DEC-DUP-010 um) |
| PROP-SM-015 | Änderungszähler `event_gen`/`seen_gen` statt reinem Unique-Dedup für Events. | job-processing.md §4.2 |
| PROP-SM-016 | Wissensklassen bei manueller Änderung; Quelle über erwarteten Pfad ansprechen (`move_source_by_path`); Ruhezeit-Zusatzregel. | job-processing.md §6.5, crash-recovery.md §5.2 |
| PROP-SM-017 | Wartungsmodus mit `gap_since` und Kontrollscan nach Freigabe. | job-processing.md §5.12 |
| PROP-SM-018 | Hash-Stufen H1–H3, Umschlag mit Prüfsumme, Neuberechnung bei Mismatch, Verzicht auf Migration persistierter Hashzustände, Fortschrittsgrenzen. | job-processing.md §3.6 |
| PROP-SM-019 | Exakte Datenmodelländerungen ohne neue Tabelle. | data-model.md §24 |

### J.3 Konfliktstatus
| ID | Status |
|---|---|
| CONFLICT-013 | bleibt aufgelöst (crash-recovery.md §3; T12 nur bei verifiziertem `move_no_overwrite`) |
| CONFLICT-015 (neu) | M8-Diagramm vs. Ablauf (Reihenfolge preverified/claimed): **aufgelöst** durch PROP-SM-011 |
| CONFLICT-016 (neu) | Wunsch nach automatischer Organisation vs. fehlende Plattformgarantie für „kein Überschreiben“: **aufgelöst durch DEC-ARC-010** (fail-closed); die Entscheidung über Alternativen (interne WebDAV-Nutzung) liegt bei OQ-069 |
| CONFLICT-017 (neu) | `group_follow` für adoptierte Komponenten: DEC-GRP-010 (automatisches Nachziehen) vs. DEC-IDX-001 (adoptierte Dateien gegen automatische Umorganisation geschützt) — **offen**, OQ-067 |

# Offene Fragen

Stand: 2026-10-09 · Runde 1 bis 4 (neueste Fragen am Ende)

Alle Punkte hier sind **[O] – noch zu entscheiden**. Die Entscheidungsvorschläge sind **[T]** (Vorschlag Claude), keine Beschlüsse.

Priorität: **P0** = vor Datenmodell zu entscheiden · **P1** = vor Implementierung des betroffenen Features · **P2** = kann später/MVP-Default genügt.

## Übersicht

| ID | Thema | Prio |
|---|---|---|
| OQ-001 | Neuzuordnung bei höherer Priorität vs. Freigabepflicht | P0 |
| OQ-002 | Zielplattform (Nextcloud-/PHP-/DB-Versionen) | P0 |
| OQ-003 | Inhaltsidentität, Tombstones, Wiederauftauchen gelöschter Inhalte | P0 |
| OQ-004 | Komponentenweise Dedup vs. Gruppenkonsistenz | P0 |
| OQ-005 | Papierkorb-Schnittstelle | P0 |
| OQ-006 | Erkennung manueller Verschiebung/Umbenennung | P0 |
| OQ-007 | Quellenpriorität vs. Datumsstrategie | P0 |
| OQ-008 | EXIF-/QuickTime-Feldreihenfolge | P1 |
| OQ-009 | Format Jahres-/Monatsordner | P1 |
| OQ-010 | „Automatisch erzeugt wirkende“ Namen | P1 |
| OQ-011 | Scope der Kollisionsnummer; Umbenennung durch Benutzer | P0 |
| OQ-012 | Zeitzonen-Semantik und Quelle der Benutzerzeitzone | P0 |
| OQ-013 | Unplausible Datumswerte | P1 |
| OQ-014 | Admin-Sicht: Quellennamen/Detailtiefe der Aggregate | P1 |
| OQ-015 | Hash-Neuprüfung vor Papierkorb-Aktion | P1 |
| OQ-016 | pHash-Verfahren | P1 |
| OQ-017 | Verzögerung beim Nachziehen von Gruppenkomponenten | P2 |
| OQ-018 | Deaktivierung, Datenhaltung, Journal-Retention | P1 |
| OQ-019 | Rekursion/Ausschlussmuster von Quellen | P1 |
| OQ-020 | Quellenumbenennung und Folgen | P1 |
| OQ-021 | Beschreibungs-Limits, Unicode | P2 |
| OQ-022 | Wartezeit unvollständiger Live Photos | P2 |
| OQ-023 | Was ist eine „relevante“ Regeländerung | P0 |
| OQ-024 | Schwellen für systematische Fehler | P2 |
| OQ-025 | Kategorien im Prüfbereich | P1 |
| OQ-026 | Zielgrößen Bestand/Last | P0 |
| OQ-027 | Verhalten bei Dateien mit mehreren Pfaden/Hardlink-Äquivalenten und Verschiebung zwischen Quellen | P2 |
| OQ-028 | Videos: Typen und Unterscheidung „eigenständig“ vs. Live-Photo-Komponente | P1 |
| OQ-029 | Verschlüsselung und Object-Store-Primary-Storage | P1 |
| OQ-030 | Verhalten in historischem Bestand: Dateien bereits in der Sammlung („Bestandsaufnahme“) | P0 |

---

## P0

### OQ-001 Neuzuordnung bei höherer Priorität vs. Freigabepflicht
**Spannung:** DEC-DAT-010 erlaubt automatische Neuzuordnung einer bereits einsortierten Datei, wenn eine höher priorisierte Herkunft auftaucht. DEC-DRY-007 sagt, bereits importierte Medien werden nicht ungefragt neu organisiert.
**Konkretes Beispiel:** Messenger-Kopie wird einsortiert als `Messenger 2026-10-09 …`. Später erscheint dieselbe Datei (gleiche SHA-256) in einer Kamera-Quelle, deren EXIF ein anderes Datum liefert.
**Vorschlag [T]:** Neuzuordnung ist **automatisch erlaubt**, aber nur wenn (a) die Quelle mit der höheren Priorität eine laufende, bereits freigegebene Regelversion hat und (b) kein `location_override` besteht. Sie wird als eigener Operationstyp `reassign` journalisiert und ist undo-fähig. Bei historischen Quellen ohne dauerhafte Freigabe entsteht stattdessen ein Plan-Item. Alternativ (konservativer): immer Vorschlag im Prüfbereich, nie automatisch.
**Auswirkung auf Datenmodell:** Herkunftstabelle mit Priorität und Zeitstempel je Herkunft; `reassign`-Operation.

### OQ-002 Zielplattform
Minimal unterstützte Nextcloud-Hauptversion, PHP-Version, Datenbanken (MySQL/MariaDB, PostgreSQL, SQLite?), Cron-Modus, Encryption-App, Primary Storage.
**Vorschlag [T]:** Ziel ist die vom Product Owner **tatsächlich betriebene** Nextcloud-Version (bitte nennen: Version, PHP, DB, Cron-Modus, Storage-Typ) plus die jeweils aktuelle Hauptversion; SQLite explizit nicht unterstützt (Nebenläufigkeit). Alle APIs in `nextcloud-integration.md` werden gegen diese Version geprüft (DEC-NCI-006). **Ohne diese Angabe wird keine API-Version behauptet.**

### OQ-003 Inhaltsidentität, Tombstones, Wiederauftauchen gelöschter Inhalte
**Lücke:** Was passiert, wenn der Inhalt (SHA-256) einer vom Benutzer gelöschten kanonischen Datei erneut im Eingang auftaucht (CONFLICT-003)?
**Optionen:** (A) erneut als neues kanonisches Exemplar importieren; (B) Tombstone → Prüffall „zuvor gelöscht“; (C) Tombstone → automatisch ignorieren.
**Vorschlag [T]:** (B). Tombstone je `(user, sha256)` mit Löschzeitpunkt und Art (manuell, Papierkorb durch App); wiederkehrender Inhalt erscheint im Prüfbereich; der Benutzer wählt „erneut importieren“ oder „ignorieren“. Passt zu DEC-GRP-014.

### OQ-004 Komponentenweise Dedup vs. Gruppenkonsistenz
**Fall:** RAW+JPEG-Gruppe, JPEG-SHA existiert bereits kanonisch in der Sammlung (aus anderem Import), RAW ist neu.
**Optionen:** (A) Gruppe wird getrennt: RAW neu importiert, JPEG-Duplikat in Quarantäne; (B) Gruppe wird dem bestehenden kanonischen JPEG zugeordnet (RAW erhält Basisname/Nummer des vorhandenen JPEG, DEC-GRP-007 „ergänzen“); (C) Prüffall.
**Vorschlag [T]:** (B), wenn das kanonische JPEG nicht `location_override`-geschützt in einem anderen Kontext liegt und die Gruppierung sicher ist; sonst (C). Quarantäne des JPEG-Duplikats bleibt komponentenweise (DEC-GRP-008).

### OQ-005 Papierkorb-Schnittstelle
Nextcloud kennt die App `files_trashbin`. Nach aktuellem Kenntnisstand ist deren Verwaltungs-API (Wiederherstellung, Prüfung des Papierkorbinhalts) kein dokumentierter, stabiler `OCP`-Bestandteil; Löschen über die Datei-API (`Node::delete`) verschiebt im Normalfall per Hook in den Papierkorb. **Muss gegen die Zielversion verifiziert werden** (siehe `nextcloud-integration.md`).
**Vorschlag [T]:** (1) Löschen via `Node::delete` mit anschließender Prüfung, ob der Papierkorb-Eintrag existiert; (2) Wiederherstellen nur, wenn eine stabile Schnittstelle bestätigt ist, sonst als „Hinweis an Benutzer: manuell im Papierkorb wiederherstellen“ (Undo-Status „nicht automatisch möglich“). Falls Papierkorb deaktiviert ist (`trashbin_retention_obligation`), wird die Löschaktion **verweigert**.

### OQ-006 Erkennung manueller Verschiebung/Umbenennung
Erfolgt über Nextcloud-Events (Rename/Move) bzw. täglichen Scan; Identifikation per `file_id`. Offen: Verhalten bei Änderungen außerhalb des Webs (Client-Sync läuft ebenfalls über Nextcloud, daher Events vorhanden; Filesystem-Direktänderungen nicht, DEC-NCI-003).
**Vorschlag [T]:** `file_id` ist der primäre Schlüssel; Pfad ist abgeleitet und wird bei Event/Scan aktualisiert. Events lösen nur Queue-Einträge aus; der Job vergleicht Soll- und Ist-Zustand aus dem Journal, um App-eigene von manuellen Änderungen zu unterscheiden (DEC-NCI-005).

### OQ-007 Quellenpriorität vs. Datumsstrategie
Sind „Kamera“ und „Messenger“ vordefinierte Quelltypen mit festem Verhalten oder beliebig benannte Quellen mit wählbarer Datumsstrategie und frei vergebener Priorität?
**Vorschlag [T]:** Quelle = frei benannt; Strategie aus Katalog (`exif_then_mtime`, `mtime`, später erweiterbar); Priorität numerisch (Standard-Presets: Kamera hoch, Messenger niedrig). „Kamera gewinnt gegenüber Messenger“ ergibt sich aus Standardprioritäten, nicht aus Fachlogik.

### OQ-011 Scope der Kollisionsnummer; Benutzerumbenennung
Eindeutigkeitsschlüssel der Nummer: `(user, Quelle, Datum, Uhrzeit)`, `(user, Zielordner, Basisname)`, oder global je Benutzer? Darf eine manuelle Umbenennung die Nummer ändern (CONFLICT-005)?
**Vorschlag [T]:** Nummer eindeutig je `(user, Quelle, YYYY-MM-DD HH-MM-SS)`; Reservierung per DB-Unique-Constraint; Nummern ohne Lücken-Wiederverwendung. Manuelle Umbenennung ändert nur den Beschreibungsanteil; weicht ein neuer Name stark vom Schema ab, normalisiert die App den Namen (Journal-Eintrag `rename`). Wenn Benutzer Präfix/Nummer selbst ändert, gilt dies als `location_override`-ähnlicher `name_override` → Prüffall statt Auto-Korrektur.

### OQ-012 Zeitzonen-Semantik
(a) Welche Zeitzone gilt für Ordner/Dateinamen bei EXIF-Daten **mit** Offset – lokale Aufnahmezeit oder Benutzerzeitzone? (b) Woher kommt die „konfigurierte Benutzerzeitzone“ – Nextcloud-Benutzereinstellung oder App-Einstellung?
**Vorschlag [T]:** (a) Dateiname und Ordner zeigen die **lokale Aufnahmezeit** (Wanduhrzeit am Aufnahmeort, die EXIF ohnehin meist liefert; bei Offset-Angabe: Wanduhrzeit = Zeit + Offset), konsistent mit „EXIF ohne Zeitzone bleibt lokal“; UTC-Instant wird zusätzlich gespeichert. (b) Nextcloud-Benutzerzeitzone (Profil), mit App-Override. Videos (QuickTime-UTC): Umrechnung in Benutzerzeitzone, da keine lokale Zeit vorliegt – dokumentieren.

### OQ-023 Was ist eine „relevante“ Regeländerung
**Vorschlag [T]:** Relevant (neue Regelversion, erneuter Dry-Run+Freigabe): Datumsstrategie, Namensschema, Zielstruktur, Quellenname (wirkt im Namen/Pfad), Gruppierungsregeln, Quellenpriorität, Quellordner. Nicht relevant (gleiche Version): Mindestalter/Stabilitätsparameter, Anzeigename, Pausen. Offen: Verhalten bis zur neuen Freigabe — Vorschlag: Quelle **pausiert**, bereits freigegebene alte Version wird nicht weiter angewendet (sicherer).

### OQ-026 Zielgrößen
Anzahl Benutzer, Dateien pro Benutzer, durchschnittliche/maximale Dateigröße, Videoanteil, Serverleistung (CPU, RAM, Storage-Typ), Cron-Intervall.
**Vorschlag [T]:** Planungsannahme 10 Benutzer, je bis zu 500 000 Dateien, einzelne Videos bis 4 GB. Bitte bestätigen oder korrigieren.

### OQ-030 Bestandsaufnahme von Dateien bereits in der Sammlung
Verhalten, wenn der Sammlungsordner bei Aktivierung bereits Dateien enthält (vermutlich häufig!): als kanonisch adoptieren? Namen normalisieren?
**Vorschlag [T]:** Indexierung nur lesend; vorhandene Dateien erhalten Status `adopted` (kanonisch, Namen/Orte unverändert) und werden nur über „Sammlung neu organisieren“ (DEC-DRY-008) angefasst.

---

## P1

### OQ-008 EXIF-/QuickTime-Feldreihenfolge
**Vorschlag [T]:** Bilder: `DateTimeOriginal` → `CreateDate`/`DateTimeDigitized` → MTime. Videos: QuickTime `CreationDate` (mit Offset, falls vorhanden) → `CreateDate` (UTC) → MTime. Rohwerte aller Kandidaten werden gespeichert (DEC-DAT-007).

### OQ-009 Format Jahres-/Monatsordner
**Vorschlag [T]:** `2026/10/Kamera` (numerisch, sortierbar); konfigurierbar später.

### OQ-010 „Automatisch erzeugt wirkende“ Namen
**Vorschlag [T]:** Konfigurierbare Regex-Liste mit Standardmustern (IMG_####, DSC#####, PXL_########_######, `WhatsApp Image/Video …`, `Screenshot…`, rein numerisch, UUID, Datumsmuster). Benutzer kann Muster pro Quelle ergänzen. Unsichere Fälle: Original **nicht** übernehmen (verlustfrei, da DB-Original bleibt).

### OQ-013 Unplausible Datumswerte
**Vorschlag [T]:** Wertebereich 1990-01-01 bis „jetzt + 1 Tag“ (konfigurierbar); darüber/darunter Fallback auf nächste Datumsquelle; kennzeichnen als `date_quality = suspect`; Prüfbereich-Hinweis, kein Blockieren.

### OQ-014 Admin-Sicht
Quellennamen und Benutzerkonfiguration sind Benutzerinhalte. **Vorschlag [T]:** Admin sieht nur Zähler je Benutzer und Zustandsarten, keine Quellennamen/Pfade; Fehler nur als Kategorie-Code.

### OQ-015 Hash-Neuprüfung vor Papierkorb
**Vorschlag [T]:** Immer neu hashen (Streaming) für die zu entfernende Kopie **und** das zu behaltende Exemplar, wenn Signatur nicht identisch; bei identischer Signatur und Cache-Alter < X Tage genügt Cache. Für Sammelaktion konservativ: immer neu hashen (Korrektheit vor Geschwindigkeit, Aktion ist selten).

### OQ-016 pHash-Verfahren
**Vorschlag [T]:** 64-Bit-DCT-pHash, Hamming-Distanz; Schwelle konfigurierbar (Start ≤ 8 als Prüfkandidat); Kandidatensuche über Bucket-/Präfix-Indizierung (Multi-Index) statt Vollvergleich. Zu prüfen: Kompatibilität mit bestehendem Python-Indexer (gleiche Bibliothek/Algorithmus für Import, REQ-IMP-02). HEIC/RAW-Dekodierung hängt von Serverbibliotheken ab → Fallback: eingebettete Vorschau oder Nextcloud-Preview.

### OQ-018 Deaktivierung, Datenhaltung, Journal-Retention
**Vorschlag [T]:** Deaktivieren löscht nichts; separate Aktion „Alle App-Daten löschen“ (mit Bestätigung); Journal-Aufbewahrung konfigurierbar, Standard unbegrenzt für Dateioperationen (Datenvolumen klein).

### OQ-019 Rekursion/Ausschlussmuster
**Vorschlag [T]:** Rekursiv als Standard; Ausschlussmuster (`.nomedia`, `@eaDir`, `.thumbnails`, Papierkorbordner); Unterordner mit eigener Quelle sind nicht erlaubt (Überschneidung, REQ-SRC-05).

### OQ-020 Quellenumbenennung
Name der Quelle steckt in Dateinamen und Pfaden. **Vorschlag [T]:** Umbenennung = relevante Regeländerung (OQ-023); bestehende Dateien bleiben unverändert, es sei denn, „Sammlung neu organisieren“ wird freigegeben.

### OQ-025 Prüfbereich-Kategorien
**Vorschlag [T]:** Vier Reiter: Exakte Duplikate, Mögliche Duplikate, Konflikte (inkl. Gruppen- und Override-Konflikte), Fehler/Wartend (dauerhafte Fehler, unsichere Gruppen, wartende Live Photos).

### OQ-028 Videos
Welche Videotypen zählen als „eigenständig“; wie unterscheidet man Live-Photo-MOV von normalem Video (kurz, Content Identifier, Apple-spezifische Metadaten)? **Vorschlag [T]:** Nur sicher identifizierbare Live-Photo-Videos (Content Identifier-Treffer) werden Gruppenkomponenten; alle anderen sind eigenständig.

### OQ-029 Verschlüsselung und Object-Store
**Vorschlag [T]:** MVP unterstützt nicht: serverseitige Verschlüsselung (Warnung); Object-Store nur nach Test.

---

## P2

### OQ-017 Nachzieh-Verzögerung
**Vorschlag [T]:** 60 s Debounce nach letzter Bewegung der Gruppe.

### OQ-021 Beschreibungs-Limits
**Vorschlag [T]:** Maximal 80 Zeichen Beschreibung, Gesamtpfad < 250 Zeichen; Unicode NFC; Kürzung an Wortgrenze mit Journal-Vermerk; Original unverändert in DB.

### OQ-022 Wartezeit Live Photos
**Vorschlag [T]:** 30 Minuten nach letzter Komponente; danach Import als Einzelmedium mit Option zur späteren Ergänzung (DEC-GRP-007).

### OQ-024 Schwellen systematischer Fehler
**Vorschlag [T]:** >20 % Fehlerquote bei mindestens 50 Operationen innerhalb 1 h → Benutzer pausiert; >50 % bei mehreren Benutzern → global; gleiche Fehlerursache in drei aufeinanderfolgenden Batches → Quelle pausiert.

### OQ-027 Sonderfälle Pfade und Quellenwechsel
Datei wird vom Benutzer von einer Quelle in eine andere verschoben; Dateien, die in Eingang und Sammlung gleichzeitig mit derselben SHA-256 existieren (normaler Fall). **Vorschlag [T]:** Zuordnung zur Quelle ergibt sich aus Pfad; Verschiebung zwischen Quellen erzeugt neuen Herkunftseintrag, kein neuer Import.

---

# Status-Update Runde 2

Stand: 2026-10-09. Die Zielumgebung konnte nicht ermittelt werden (`environment.md`). Vorschläge sind **[T]**; als „beantwortet“ markierte Fragen folgen Klarstellungen des Product Owners (jetzt `DEC-…` in `decisions.md` Abschnitt E.1).

| ID | Status | Verweis |
|---|---|---|
| OQ-001 | **Beantwortet** (DEC-DRY-012, DEC-DAT-012). Rest: Gilt Prioritätsänderung als Regeländerung? → OQ-023 | – |
| OQ-002 | **Offen** – Zielumgebung unbekannt; Befehlsliste in `environment.md`. Neue Teilfrage: Nextcloud oder ownCloud? | P0 |
| OQ-003 | **Grundsatz beantwortet** (DEC-DUP-008); Modellierung offen | P0 |
| OQ-004 | **Beantwortet** (DEC-GRP-015). Rest: Kriterien „eindeutig passend“ | P1 |
| OQ-005 | **Technisch ausgearbeitet**, Entscheidung offen → OQ-032 | P0 |
| OQ-006 | **Konzept vorgeschlagen** (`architecture-overview.md` §8.4); Spike erforderlich | P0 |
| OQ-007 | **Beantwortet** (DEC-DAT-011). Rest: Definition „berechtigt“ | P1 |
| OQ-011 | **Technisch ausgearbeitet**; Teilfragen a–d offen (unten) | P0 |
| OQ-012 | **Technisch ausgearbeitet** (§8.2), Semantik-Entscheidung (Ordner/Name bei Offset) offen | P0 |
| OQ-023 | **Technisch ausgearbeitet** (§8.3), Entscheidung zu Prioritäts- und Mindestalter-Änderung offen | P0 |
| OQ-026 | **Teilweise beantwortet:** ≈108.466 Bilddateien plus Videos/Sidecars; Zielgröße „deutlich größer“, ohne Performanceversprechen. Weitere Angaben (Benutzerzahl, Videoanteil, Serverleistung) fehlen | P0 |
| OQ-030 | **Beantwortet** (DEC-DRY-010/011) | – |

## Präzisierungen der noch offenen Fragen

### OQ-011 – Teilfragen
a) Gilt die Auslegung „Nummer gehört zum Namen dieser Phase“ (neue Reservierung bei Reassign mit geändertem Zeitstempel/Quelle, alte bleibt verbrannt)? **Vorschlag:** ja.
b) Schlüssel `(user, Zielverzeichnis, Quellenpräfix, Sekunde)` einverstanden? **Vorschlag:** ja, mit Normalisierung (NFC, Case-Folding).
c) Wird die Nummer bei Dry-Run nur vorläufig angezeigt und erst bei Ausführung reserviert? **Vorschlag:** ja → OQ-031.
d) Überlauf >99: dreistellig? **Vorschlag:** ja, automatisch `001`-Format ab 100, Sortierung bleibt innerhalb gleicher Länge.
e) Manuell geänderter Präfix/Nummer in Namen (CONFLICT-005)? **Vorschlag:** Prüffall `name_override`, keine automatische Korrektur.

### OQ-012 – Teilfragen
a) Ordner/Dateiname bei EXIF **mit** Offset: lokale Aufnahmezeit (Vorschlag) oder Benutzerzeitzone?
b) Quelle der „konfigurierten Benutzerzeitzone“: Nextcloud-Profil (`IDateTimeZone`, unverifiziert) oder App-Einstellung? **Vorschlag:** App-Einstellung mit Vorbelegung aus dem Profil, Teil des Regel-Fingerprints.
c) Videos nur mit UTC-Zeit: Umrechnung in Benutzerzeitzone (kein lokaler Wert vorhanden). **Vorschlag:** ja, `sort_time_basis = user_tz_from_utc` kennzeichnen.

### OQ-023 – Teilfragen
a) Wirkt eine Prioritätsänderung nur auf Quellen mit geänderter relativer Rangfolge? **Vorschlag:** ja (paarweise).
b) Soll eine **Verkürzung** des Mindestalters die Freigabe invalidieren? **Vorschlag:** nein, aber Mindestalter < 1 Minute nur mit Warnung.
c) Verhalten bis zur neuen Freigabe: Quelle pausiert (**Vorschlag**) oder alte Version läuft weiter.

## Neue Fragen

### OQ-031 (P1) Vorläufige Nummern im Dry-Run
Plan zeigt vorläufige Kollisionsnummern; endgültig bei Ausführung. **Vorschlag [T]:** so; Freigabe bezieht sich auf Regel + Plan-Item, nicht auf Nummer. Alternative: Nummern im Plan reservieren (verbrennt Nummern bei verworfenen Plänen).

### OQ-032 (P0) Undo für Papierkorb-Aktionen
Optionen: (A) automatisches Restore über `ITrashBackend` (Klasse 3), (B) angeleitete manuelle Wiederherstellung mit Journal-Daten, (C) WebDAV-Restore nur von externen Clients. **Vorschlag [T]:** MVP = (B); (A) nur nach Spike, gekapselt in Adapter. Vor der Papierkorb-Aktion Pflichtprüfung, dass `files_trashbin` aktiv ist und die Aufbewahrung eine Wiederherstellung zulässt; sonst Aktion verweigern.

### OQ-033 (P1) Externe Werkzeuge
Sollen ExifTool/ffprobe/Imagick optionale Provider sein (PROP-TOOL-001)? Abhängig von `environment.md`. **Vorschlag [T]:** ja, optional mit Probe; Live-Photo-Erkennung per Content Identifier nur mit ExifTool, sonst konservativ.

### OQ-034 (P1) Benutzerbezogener Worker
`occ photoorganizer:run --user=…` als eigener Befehl? **Vorschlag [T]:** ja, plus TimedJob-Scheduler, der Batches nach Fairness-Budget abarbeitet; Lease verhindert Doppelarbeit (PROP-LEASE-001).

### OQ-035 (P0) Produktidentität
Ist `/home/cloud/owncloud` tatsächlich Nextcloud? **Vorschlag:** `occ status` und `version.php` auswerten (`environment.md`).

### OQ-036 (P1) „Berechtigte“ Quelle
Definition: aktiv ∧ freigegebene Regelversion ∧ nicht pausiert ∧ Benutzer aktiviert ∧ Admin-Pause nicht aktiv. **Vorschlag [T]:** so.

---

# Status-Update Runde 2b

Stand: 2026-10-09. Bezug: Feedback zu Auftrag 2. Neue verbindliche Entscheidungen: `decisions.md` Abschnitt F.1.

## Statusänderungen

| ID | Neuer Status | Verweis |
|---|---|---|
| OQ-002 | **Teilweise beantwortet**: Referenz Nextcloud 34.0.4 / PHP 8.3.14 / MySQL; allgemeiner Versionsbereich als Vorschlag in `environment.md` §3 → OQ-037 | DEC-PRD-007 |
| OQ-003 | **Semantik verbindlich erweitert** (kein einfacher Tombstone); Modellierung → Auftrag 3 | DEC-DUP-009 |
| OQ-010 | **Teilweise beantwortet**: eindeutig → nicht anhängen; unsicher → beibehalten. Muster sind konfigurierbare Daten | DEC-NAM-011 |
| OQ-011 | **Weitgehend beantwortet**: Reservierungsstrategie akzeptiert; manuelle Präfix-/Nummernänderung → Prüffall; Dry-Run-Nummern vorläufig ohne stille Änderungen. Rest: Teilfrage d (Überlauf >99), Schlüssel-Normalisierung | DEC-NAM-012/013 |
| OQ-013 | **Beantwortet**: keine Altersuntergrenze, nur Kennzeichnung | DEC-DAT-014 |
| OQ-023 | **Grundsatz beantwortet** (selektive Invalidierung, Pause keine Regeländerung); Komponentenliste final festzulegen | DEC-DRY-013/014 |
| OQ-031 | **Beantwortet** (vorläufige Nummern) | DEC-NAM-013 |
| OQ-032 | MVP-Vorschlag „angeleitet“ akzeptiert (als technischer Vorschlag); automatisch erst nach API-Verifikation | PROP-JNL-003 |
| OQ-035 | **Beantwortet**: Produkt ist Nextcloud 34.0.4 | environment.md |
| OQ-036 | **Beantwortet**: `processing_eligible` / `provenance_eligible` getrennt; Restfragen unten | DEC-DAT-013 |
| OQ-012 | Unverändert offen (Semantik bei Offset); Feldtrennung beschlossen | DEC-DAT-015 |

## Neue Fragen

### OQ-037 (P0) Unterstützter Nextcloud-Versionsbereich
Vorschlag [T] siehe `environment.md` §3: technischer Boden 29 (aus geprüften `@since`), Entwicklungsziel 34, veröffentlichter Bereich = zum Release-Zeitpunkt von Nextcloud gepflegte Hauptversionen. Entscheidung: konkrete Untergrenze festlegen und Support-Policy (wie viele Hauptversionen zurück).

### OQ-038 (P1) Verhalten bei gelöschter oder umbenannter Quelle
Sind Herkünfte einer entfernten Quelle weiter `provenance_eligible`? Vorschlag [T]: Historie bleibt erhalten und zählt weiter für Soll-Ort-Bestimmung, bis ein Reorganisationsplan etwas anderes beschließt; Benutzer wird gewarnt.

## Vor dem relationalen Datenmodell (Auftrag 3) tatsächlich zu entscheiden

Nur Fragen, deren Antwort die **Struktur** des Modells verändert. Alle Vorschläge [T].

| Prio | Frage | Warum modellrelevant | Vorschlag |
|---|---|---|---|
| **P0-1** | **Tombstone-Granularität und -Ereignisse** (OQ-003): Welche Löschungen gelten als „bewusst manuell“? Gelten Tombstones je Inhaltsidentität, je Datei oder je Gruppenkomponente? Wie viele Wiederzulassungen? | Entitäten `deletion_event`, `readmission_event`, Beziehung zu Identität/Gruppe | Pro Inhaltsidentität; Papierkorb-Sammelaktion für Duplikatkopien erzeugt keinen Tombstone; Wiederzulassung als separates Ereignis (siehe architecture-overview.md §10) |
| **P0-2** | **Herkunftsmodell** (OQ-036 Rest, OQ-038): Wie wird `provenance_eligible` gespeichert (Regelversion pro Herkunft)? Verhalten bei gelöschter Quelle | Herkunftstabelle, Beziehung zu Regelversionen | Herkunft referenziert die beim Import freigegebene Regelversion; Quelle wird nie hart gelöscht (nur archiviert) |
| **P0-3** | **Regelkomponenten und Plan-Item-Abhängigkeiten** (OQ-023): endgültige Komponentenliste; Mindestalter ja/nein im Fingerprint | Tabellen für Regelversion, Komponenten-Fingerprints, Plan-Item-Abhängigkeiten | Komponenten wie in architecture-overview.md §11.2; Mindestalter nicht enthalten |
| **P0-4** | **Gruppenmodell** (DEC-GRP-015): Gruppen-ID, Mitgliedschaftsevidenz („eindeutig passend“), Nachträge, Rolle der Komponente | Gruppe/Komponenten-Beziehung, Evidenzfelder | Evidenz als Enum + Rohwerte; eine Komponente höchstens eine Gruppe; unklar → Prüffall |
| **P0-5** | **Zeitsemantik** (OQ-012): Ordner/Name bei Offset: lokale Aufnahmezeit oder Benutzerzeitzone? Herkunft der Benutzerzeitzone | Spalten und Ableitungsregeln | Lokale Aufnahmezeit; Zeitzone als App-Einstellung (Vorbelegung aus Profil) |
| **P0-6** | **Nummern-Schlüssel und Überlauf** (OQ-011 d): Normalisierung, Format >99 | Unique-Constraint-Definition | Schlüssel wie §8.1; dreistellig ab 100 |
| **P0-7** | **Versionsbereich** (OQ-037) | Migrationen/Schema-Features, Index-Längen | siehe oben |
| **P0-8** | **`file_id`-Kontinuität als Modellannahme** (nicht entscheidbar ohne Spike) | Identitäts- und Verlustbehandlung | Modell mit Austauschpunkt: `file_ref` (Nextcloud-ID) plus Historie (`file_ref_history`), sodass ein ID-Wechsel modelliert werden kann; Spike vor Implementierung, nicht vor Modell |

Nicht vor dem Modell nötig (können im Modell-Review oder später entschieden werden): OQ-016 (pHash-Parameter), OQ-017 (Nachzieh-Verzögerung), OQ-018 (Retention), OQ-019/020 (Rekursion/Quellumbenennung), OQ-021 (Beschreibungslimits), OQ-022 (Live-Photo-Wartezeit), OQ-024 (Fehlerschwellen), OQ-027, OQ-028, OQ-029, OQ-033, OQ-034.

---

# Runde 3 (Datenmodell) – neue und aktualisierte Fragen

Stand: 2026-10-09. Vorschläge **[T]** bzw. **[Z]** (zu bestätigen). Die Fragen sind nach Dringlichkeit für das **relationale Schema** sortiert. P0 = ändert Tabellenstruktur/Constraints; P1 = ändert Spaltenwerte/Regeln, nicht die Struktur.

## Aktualisierte Fragen
| ID | Änderung |
|---|---|
| OQ-037 | **Neu gefasst durch Auftrag 3:** Entwicklungs‑ und Testfokus **Nextcloud 34 und 35**; 33 nach Tests möglich; Matrix später verbindlich (PROP-DM-009). Der frühere Vorschlag (Boden 29) ist überholt. Offene Detailfrage: Kriterium, wann 33 aufgenommen wird, und wie mit zusätzlichen älteren Versionen verfahren wird. |
| OQ-012 | Modellseitig vorbereitet (Felder getrennt). Bestätigung der Regel „EXIF mit Offset: lokale Uhrzeit für Ablage“ steht aus. |
| OQ-003 | Modellentwurf liegt vor (PROP-DM-002); Bestätigung steht aus → OQ-040. |
| OQ-011 | Normalisierung und Format (≥ 2 Stellen, unbegrenzt) im Modell abgebildet → OQ-043, OQ-047. |
| OQ-016 | pHash‑Kandidatensuche modelliert (Bänder); Schwelle und Bandanzahl bleiben datenabhängig und zu messen. |

## Neue Fragen
### P0 – strukturrelevant
| ID | Frage | Vorschlag [Z] |
|---|---|---|
| **OQ-039** | Interne Dateiidentität und Referenzhistorie bestätigen? Unter welchen Bedingungen darf eine Instanz nach `file_id`‑Verlust automatisch wiedererkannt werden? | Eigene ID; Auto‑Wiedererkennung nur wenn genau eine `missing` Instanz mit gleichem SHA‑256/Größe und genau ein neuer Fund passt, keine bestätigte Löschung und kein Tombstone; sonst Prüfaufgabe (data-model.md §5.3). |
| **OQ-040** | Tombstone‑Granularität und Erzeugungsregeln bestätigen: Tombstone je **Inhalt**, nur aus bestätigter Löschung der **kanonischen** Instanz; zusätzliche Kopien und Verlust erzeugen keinen. Wie viele „Bestätigungsstufen“ braucht `certainty=confirmed` (Papierkorb‑Eintrag, mehrere Scans)? | wie beschrieben; `confirmed` = Löschereignis plus Nachweis Papierkorb/Abwesenheit im folgenden Scan; Zahl der Scans konfigurierbar, nicht festgeschrieben. |
| **OQ-041** | **(in Runde 3b neu gefasst, siehe unten)** Wirksame Herkunftspriorität (CONFLICT-010) und Tie‑Break. | Priorität der zuletzt freigegebenen Quellenrevision; Tie‑Break: älteste Beobachtung, dann kleinste ID. |
| **OQ-042** | Zwei Revisionsebenen und Komponentenliste (`date`, `naming`, `target`, `group`, `scope`, `priority_ctx`) bestätigen; Quellpräfix in `naming`, Quellordner in `scope`. | wie data-model.md §4. |
| **OQ-045** | Gruppierungsregeln: Kriterien Stufe 2 (kompatible Dateiarten, gleicher Ordner, gleicher Basisname, Zeitstempeltoleranz) und die Rolle der Toleranz; Verhalten bei mehreren Hypothesen. | Toleranz konfigurierbar (kein Standardwert behauptet); mehrere Hypothesen ⇒ `ambiguous`; Benutzer kann trennen/zusammenführen. |
| **OQ-048** | **(in Runde 3b neu gefasst, siehe unten)** Auflösung des gemeinsamen‑JPEG‑Konflikts (CONFLICT-008): welche Optionen sollen angeboten werden und was ist der Standard bis zur Entscheidung? | Standard: nichts verschieben, RAWs regulär importieren, JPEG‑Kopien im Eingang halten; Optionen O1–O5 (data-model.md §9.4); O4 (`shared_ref`) nur mit ausdrücklicher Freigabe als Ausnahme von DEC-NAM-010. |

### P1
| ID | Frage | Vorschlag [Z] |
|---|---|---|
| OQ-043 | Nummerndarstellung: mindestens zweistellig, unbegrenzt; Lexikographische Sortierung ab 100 | akzeptieren; keine Umnummerierung (DEC-NAM-009). |
| OQ-044 | Zeitzonen‑Fallbackkette | App → Nextcloud‑Profil → Nextcloud‑Standard (falls abfragbar) → UTC, Stufe in `tz_source` protokolliert, `reliability` herabgestuft. |
| OQ-046 | Papierkorb‑Rückholung durch den Benutzer (CONFLICT-009) | Wiederauftauchen mit Bezug zur ursprünglichen Instanz ⇒ Prüffall, keine automatische Wiederzulassung. |
| OQ-047 | Namensraum‑Normalisierung | NFC, Case‑Folding, Windows‑Regeln: bewusst restriktivste Semantik. |
| OQ-038 | (aus 2b) Archivierte Quellen: bleiben Herkünfte `provenance_eligible`? | Ja, Historie zählt, bis ein Reorganisationsplan anderes beschließt. |
| OQ-049 | Löschen von Zeitkandidaten/Referenzhistorie nach Retention (Datenvolumen) | Journal und Tombstones nie; Referenzhistorie und Kandidaten nach konfigurierbarer Frist kürzbar, solange nicht referenziert. |
| OQ-050 | `certainty`‑Bestätigung bei Verlust/Nichterreichbarkeit: nach wie vielen Beobachtungen wird aus „verloren“ eine Löschung? | konfigurierbar, nur nach explizitem Nachweis oder manueller Bestätigung. |

## Erneut priorisierte Liste: vor Auftrag 4 (Job‑State‑Machine) zu klären
1. OQ-039, OQ-040, OQ-041, OQ-042 (Modellstruktur).
2. OQ-048 (Konfliktfall, Prüfaufgabentypen).
3. OQ-045 (Gruppenstatus, Warte‑ und Konfliktzustände).
4. OQ-044/OQ-012 (Zeitentscheidungslogik in Jobs).
Alles andere kann parallel zum Job‑Entwurf entschieden werden.

---

# Runde 3b (Review des Datenmodells) – neu gefasste und neue Fragen

Stand: 2026-10-09. Alle Vorschläge **[Z]**. P0 = ändert Tabellenstruktur/Constraints oder blockiert die Job-State-Machine; P1 = ändert Regeln/Werte.

## Neu gefasste Fragen
| ID | Neufassung |
|---|---|
| **OQ-041** (P0, ersetzt Runde 3) | **Herkunftsentscheidung statt Neuberechnung.** Vorschlag: persistente `po_origin_decision`; Provenance unverändert; Konfigurationsänderung allein ordnet nichts um; Neuzuordnung nur durch neues Herkunftsereignis mit **strikt höherer** eingefrorener Priorität nach freigegebener Regel, durch freigegebenen Plan oder manuell; Gleichstand ändert nichts (ersetzt den früheren Tie-Break „älteste Beobachtung“); Standort-Override hat Vorrang. **Zu entscheiden:** (a) Vergleichsbasis: Variante F (eingefrorene Priorität des bisherigen Siegers, empfohlen) oder Variante C (aktueller freigegebener Rang der Quelle des bisherigen Siegers); (b) ob Gleichstand grundsätzlich nichts ändert (Vorschlag: ja). Siehe data-model.md §16, CONFLICT-010. |
| **OQ-048** (P0, ersetzt Runde 3) | **Konservative Standardbehandlung bei gemeinsamem Bestandteil.** Vorschlag: gesamte Konfliktmenge (beide Gruppen, alle Bestandteile) bleibt unverändert im Eingang (`held_conflict`), keine Teiloperation, keine Reservierung; Optionen O1–O5 zur manuellen Auflösung; die Variante P2 („RAW sofort importieren“) wäre eine neue fachliche Entscheidung. **Zu entscheiden:** Bestätigung des Standards; ob O4 (`shared_ref`) überhaupt angeboten wird; ob P2 als Benutzeroption zugelassen wird. Siehe data-model.md §9, CONFLICT-008/012. |
| OQ-043 (P1, korrigiert) | Stellenzahl der Nummer ist Teil der Komponente `naming` (Änderung macht offene Plan-Items und Standing-Freigaben mit `naming` ungültig; keine Umnummerierung). Bestätigen. |
| OQ-016 (P1, erweitert) | Siehe OQ-054: Bandlayout, Garantieradius, Kappung und Akzeptanzkriterien. |

## Neue Fragen
### P0
| ID | Frage | Vorschlag [Z] |
|---|---|---|
| **OQ-051** | Indexierung der bestehenden Sammlung: (a) Scan-Scope als eigene Entität statt interner Sammlungsquelle? (b) Braucht die reine Indexierung (rein lesend) keinen Dry-Run, aber eine Vorschau? (c) Sind adoptierte Dateien gegen automatische Neuzuordnung durch höher priorisierte Herkunft geschützt (CONFLICT-011)? (d) Wie wird bei Duplikaten **innerhalb** der Sammlung der Kanon bestimmt, und bleiben diese aus der Sammelaktion ausgenommen? | (a) ja; (b) ja; (c) ja, bis ausdrücklich ein Reorganisations- oder Neuzuordnungsplan freigegeben ist; (d) kleinste Instanz-ID, `collection_duplicate`, nie in der Sammelaktion. |
| **OQ-052** | Integritätsstufe: (a) Alle Operationen erhalten einen nachgelagerten Hash‑Job (100 %), nur Stichproben oder nur auf Anforderung? (b) Muss die kanonische Instanz vor jeder Duplikat‑Papierkorb‑Aktion `hash_verified` sein? (c) Behandlung unbekannter Storage‑Typen (`unknown` ⇒ Hash vor `done`)? | (a) 100 % mit niedriger Priorität und Drosselung, Frist konfigurierbar; (b) ja; (c) ja. Kosten (Lesezugriffe je Datei) sind auf der Referenz zu messen. |
| **OQ-055** | Fencing: (a) Welche Sicherheitsabstände (Lease‑Rest, Claim‑Frist) gelten? (b) Wird die Nextcloud‑Dateisperre als zusätzliche Schicht verwendet, falls verfügbar? (c) Wie verhält sich `Node::move()` bei vorhandenem Ziel und bei gleichzeitigem Zugriff (Spike)? (d) Wird das Restrisiko „verspäteter Storage‑Aufruf nach Lease‑Verlust“ in der beschriebenen begrenzten Form akzeptiert? | (a) Konfiguration, auf der Referenz zu messen; (b) ja, ohne Abhängigkeit; (c) Spike vor der Implementierung; (d) ja, mit Nachprüfung und Undo (data-model.md §17.4). |
| **OQ-054** | pHash: (a) Algorithmusregister und Versionierung wie beschrieben? (b) `hash_hex STRING(16)` statt signed `BIGINT`? (c) Welche Hamming‑Schwelle ist fachlich beabsichtigt (Garantiebereich bis 8)? (d) Kappungsgrenze und Verhalten bei gekappten Suchen? (e) Akzeptanzkriterien des Benchmarks (Latenz, Indexgröße, Recall)? | (a) ja; (b) ja; (c), (d), (e) nach Benchmark durch den Product Owner. |

### P1
| ID | Frage | Vorschlag [Z] |
|---|---|---|
| OQ-053 | Eine bereits verwaltete (kanonische) Datei wird vom Benutzer zurück in den Eingangsordner verschoben: automatisch als erneuten Import behandeln oder nur Prüfaufgabe? | Prüfaufgabe; Weiterverarbeitung nach ausdrücklicher Bestätigung; Kanon wird `retracted`. |
| OQ-056 | `nc_key` als SHA‑256 über längenpräfixierte Originalwerte; alternative Unique auf zwei Spalten? | Hash; Originalspalten bleiben. |
| OQ-057 | Randfälle bei Dateinamen: nicht dekodierbare Bytes, Normalisierung (NFC/NFD) — wie werden sie gespeichert und angezeigt? | Name unnormalisiert wie geliefert in `TEXT`; Fall nicht dekodierbar ist zu klären. |

## Erneut priorisierte Liste: vor Auftrag 4 zu klären
1. **OQ-041, OQ-051, OQ-055, OQ-052** (ändern Zustandsautomaten für Operationen, Instanzen, Scans).
2. OQ-039, OQ-040, OQ-042 (offen aus Runde 3).
3. OQ-048, OQ-045 (Gruppenzustände und Konfliktmengen).
4. OQ-054 (nur für die pHash‑Jobs; Benchmark erst bei Implementierung).
5. OQ-053, OQ-056, OQ-057, OQ-043, OQ-044/OQ-012 können parallel entschieden werden.


---

# Runde 4 (Job-State-Machine) – Status und neue Fragen

## Beantwortet durch das Review (siehe decisions.md I.1)
| OQ | Antwort |
|---|---|
| OQ-041 | Beantwortet: Variante F (DEC-DAT-016) |
| OQ-051 | Beantwortet (DEC-IDX-001) |
| OQ-052 | Beantwortet: Pre- und Post-Hash für App-Moves (DEC-IMM-008). Offen: nachgelagerte Stichproben für nicht App-veranlasste Zustände (Häufigkeit) |
| OQ-055 | Im Prinzip beantwortet (DEC-JOB-014). Offen: Zahlenwerte (Sicherheitsabstand, Frist, Lease-Dauer) und Spikes SP-A/SP-E/SP-F |
| OQ-048 | Beantwortet (DEC-GRP-016); Optionen O1–O5 bleiben Einzelfallentscheidung |
| OQ-053 | Beantwortet (DEC-GRP-017) |
| OQ-054 | Teilweise beantwortet (DEC-PHA-008). Offen: Kappungsgrenze, Benchmark-Akzeptanzkriterien |

## Neue Fragen
### P0 (vor Implementierung der Zustandsautomaten)
| ID | Frage | Vorschlag [Z] |
|---|---|---|
| **OQ-058** | Pausen: Werden während Pause/Hold weiterhin Events eingereiht und darf die Recovery **lesend** beobachten (nicht fortsetzen, nicht wiederholen)? | Ja (job-processing.md §5.12, crash-recovery.md §3.5). |
| **OQ-059** | Akzeptanz der verbleibenden Risiken RR-1…RR-10 (crash-recovery.md §8), insbesondere RR-1 (verspäteter Storage-Aufruf nach Lease-Verlust) und RR-3 (Überschreiben einer Benutzerumbenennung im Restfenster). | Akzeptieren, begrenzt durch Intent-Ziel, Ruhefrist, Sperren, Pre-/Post-Hash und Undo; Spikes SP-A, SP-E vor Implementierung. |
| **OQ-060** | Pre-Hash: Gültigkeitsdauer, Kostenmodell (≈ 2× Lesen je Operation), Verhalten bei sehr großen Dateien (Hash über mehrere Dispatcher-Läufe). | Gültigkeit konfigurierbar, nach SP-F zu setzen; Fortsetzbarkeit des Hash-Kontexts als Capability. |
| **OQ-061** | Holds: Wer hebt einen „kritischen“ Hold auf (Benutzer, Admin, beide)? Welche Fehlerrate löst einen „systematischen“ Hold aus und wie erfolgt die Rückkehr? | Kritisch: Benutzer nach Sichtung der Prüfaufgabe; Admin sieht nur den Zustand. Systematisch: Backoff mit Probe-Einheit; Schwellen Konfiguration. |
| **OQ-062** | Reihenfolge der Kinder einer Gruppenoperation und Verhalten bei Primärkomponente gelöscht. | Sidecars zuletzt; Primärkomponente gelöscht ⇒ `needs_review`. |
| **OQ-063** | K1 (Eintrittsepisode): Bestätigung von `entry_seq` als Provenance-Schlüssel und der Regeln für Abwesenheit/Wiedererkennung. | Wie data-model.md §23.1. |

### P1
| ID | Frage | Vorschlag [Z] |
|---|---|---|
| OQ-064 | Live-Photo-Konfliktstrategie: Optionen für den Benutzer (B nachziehen mit/ohne Namensangleichung, nichts tun, trennen). | crash-recovery.md §5.2 |
| OQ-065 | Häufigkeit der Stichproben-/Vollscan-Neuhashs (nicht App-veranlasst). | konfigurierbar, Standard niedrig |
| OQ-066 | Ordner-ETag als Scan-Abkürzung (Spike SP-G). | nur nach Verifikation |

---

# Runde 4b – Status und neue Fragen

## Beantwortet oder präzisiert
| OQ | Stand |
|---|---|
| OQ-058 | Beantwortet durch Auftrag 4b (DEC-JOB-016): Metadaten-Beobachtung bei Benutzer-/Quellen-/Benutzer-Admin-Pause erlaubt; globaler Wartungsmodus ohne Storage-Zugriffe. Details unter OQ-068. |
| OQ-055 | Präzisiert: fail-closed (DEC-ARC-010); offen bleiben Spikes und Zahlenwerte (OQ-069). |
| OQ-052 | Präzisiert um Löschsicherheit (DEC-DUP-010). |
| OQ-060 | Präzisiert: Hash-Stufen H1–H3 (job-processing.md §3.6). Weiterhin offen: Pre-Hash-Gültigkeit. |

## Neue Fragen
### P0
| ID | Frage | Vorschlag [Z] |
|---|---|---|
| **OQ-069** | Fail-closed-Fähigkeiten: (a) Allowlist getesteter (Version, Storage)-Tupel als Auslieferungsform? (b) Wie viele Wiederholungen muss die Race-Harness von SP-A3 durchlaufen, bevor `move_no_overwrite` gilt? (c) Falls kein öffentlicher API-Aufruf Nichtüberschreibung belegt: Darf die App intern einen WebDAV-`MOVE` mit `Overwrite: F` nutzen, oder bleibt es beim Modus „Nur Indexierung“? (d) Erscheint für Administratoren/Benutzer eine „Trotzdem“-Option? | (a) ja; (b) PO legt fest; (c) PO entscheidet nach Spike-Ergebnis; Architekturstandard bleibt öffentliche OCP-API; (d) nein. |
| **OQ-067** | `group_follow` für adoptierte Komponenten (CONFLICT-017): dürfen sie nach einer manuellen Verschiebung einer Komponente nachgezogen werden? | Nur bei Gruppenmitgliedschaft Stufe 1 oder 3 (nicht Heuristik Stufe 2), sonst Prüffall. |
| **OQ-068** | Pausen: (a) Dürfen Benutzeraktionen (Undo, Trash) während der **eigenen** Pause ausgeführt werden? (b) Welche Metadaten darf die Recovery bei Pause beobachten? (c) Obergrenze der Warteschlange im Wartungsmodus; Verhalten bei Überlauf. | (a) nein, erst fortsetzen; (b) Existenz, Pfad, Größe, MTime, ETag; (c) Konfiguration, Überlauf ⇒ `gap_since` + Scan. |

### P1
| ID | Frage | Vorschlag [Z] |
|---|---|---|
| OQ-070 | Ruhezeit-Zusatzregel für Moves in der Sammlung, falls `move_source_by_path` nicht verifiziert wird. | konfigurierbar |
| OQ-071 | Höchstverzögerung der Event-Entprellung. | Konfiguration |
| OQ-072 | Gesamtlimit `hash_max_age` und Verhalten bei H3 (`hash_not_possible`). | Prüfaufgabe, kein Ersatzwert |


---

# Runde 5 (Plattform-Spikes) – Status und neue Fragen

Grundlage: `platform-spikes.md` (Nextcloud 34.0.4 und 35.0.0, lokaler Heim-Storage; experimentell **E**, Quelltext **Q**, Vertrag **V**). Keine bestehende Entscheidung wurde geändert; die folgenden Punkte sind **Entscheidungsvorlagen**. Neue Konflikte sind mit `CONFLICT-018` ff. nummeriert; ihre Aufnahme in `decisions.md` erfolgt nach dem Review.

## Durch die Spikes beantwortet oder präzisiert

| OQ | Stand nach Runde 5 |
|---|---|
| OQ-069 (a)–(c) | **(c) beantwortet:** WebDAV `MOVE` mit `Overwrite: F` ist **kein** sicherer Ersatz (A13: nach dem Sabre-Check entstehendes Ziel wird ersetzt, kein Papierkorbeintrag; A23–A26 Stichproben). Ohne Entscheidung zu OQ-073 gilt für **alle** getesteten Tupel `move_no_overwrite = false`, d. h. Modus „Nur Indexierung und Analyse“ (DEC-ARC-010). (a)/(b) präzisiert durch OQ-078. |
| OQ-006 (Erkennung manueller Verschiebung/Umbenennung über Events) | technisch beantwortet für die getesteten Aktionen (`platform-spikes.md` §7): Rename/Move/Copy/Delete/Restore lösen je genau ein Hauptereignis im Prozess der Aktion aus; offen: Ordner-Moves und Quellknoten-Identität (OQ-080). |
| OQ-005 / OQ-032 / CONFLICT-006 (Papierkorb, Undo) | präzisiert: **OQ-005 technisch beantwortet** – `Node::delete()` verwendet den Papierkorb, der Eintrag ist prüfbar (B05, D01–D03); Löschen per `Node::delete()` ist nachweisbar (B05, D01–D03); automatisches Restore bleibt nicht Teil des Scopes; Restore per WebDAV überschreibt nicht (D04). |
| OQ-067 (`group_follow` adoptierter Komponenten) | Der Product Owner formuliert in Auftrag 5 §8.1 das Zielverhalten (Nachziehen nach Debounce, auch adoptierte Dateien, ohne allgemeine Reorganisation); die Plattformprüfung findet **kein Hindernis**, aber dieselben Überschreib- und Staleness-Risiken wie jeder Move (§9.1). Zur Bestätigung als [V] siehe OQ-076. |
| OQ-070 (Ruhezeit-Zusatzregel, falls `move_source_by_path` nicht verifiziert) | **Bedingung eingetreten:** `Node::move` bietet `move_source_by_path` nicht (A06). Die Zusatzregel ist erforderlich; Zahlenwert offen (OQ-076). |

## Neue Fragen

### P0
| ID | Frage | Vorschlag [Z] |
|---|---|---|
| **OQ-073** | **Nichtüberschreibende Verschiebung:** Weder `Node::move()` noch WebDAV `MOVE` (auch mit `Overwrite: F`) noch `Folder::newFile()` bieten eine belegbar atomare Nichtüberschreibung; verfügbar ist nur ein Prototyp über einen Storage-Wrapper (interne API, nur mit wirksamen Dateisperren). Welchen Weg wählt das Produkt? (A) Modus „Nur Indexierung und Analyse“ bleibt dauerhaft Standard, automatische Organisation entfällt vorerst; (B) „geschützter Move“ über interne Storage-Wrapper-API als optionale, per Allowlist freigeschaltete Fähigkeit; (C) Restrisiko akzeptieren und absichern (Reservierung, Journal, Pre-/Post-Hash); (D) Upstream-Beitrag (Move mit Nicht-Überschreiben-Option) abwarten/anstoßen. | **(A) als Standard**, parallel **(D)** anstoßen; **(B)** nur nach ausdrücklicher Entscheidung des PO, mit Allowlist je Tupel und Abschaltung bei Abweichung; **(C) nicht empfohlen**, weil ein überschriebener Inhalt unwiederbringlich ist (kein Papierkorb, keine Version; A02/A03). |
| **OQ-074** | **Rolle der Dateisperren:** Eigene Sperren (auch shared) auf Quelle oder Ziel blockieren den eigenen `Node::move` (C05); nicht existierende Zielpfade sind nur über internen Schlüssel sperrbar (C04). Der Mutationskern P5 („Sperren, dann Storage-Aufruf“, `job-processing.md` §6.1) ist so nicht umsetzbar. Streichen oder ersetzen? | P5 streichen; Dateisperren nicht als Schutz der eigenen Mutation einplanen; kooperativer Mutex zwischen App-Workern bleibt DB-Lease (C06 belegt, dass der Redis-Sperrdienst als Mutex trägt, ist aber nicht nötig). Sperren sind nur Abwehr fremder Nextcloud-Zugriffe während des Rename-Aufrufs selbst (A12), nicht davor/danach. |
| **OQ-075** | **Signaturlücke:** Größe+MTime+ETag erkennen eine Änderung in derselben Sekunde mit gleicher Größe nicht (B06, F05). Konsequenz für Hash-Cache (DEC-JOB-005) und Fortsetzung von Hashes: Welche Mindest-Ruhezeit (Alter der Signatur) gilt, bevor ein Hash als cache-fähig gilt? | Hash nur dann wiederverwenden, wenn MTime mindestens *T* Sekunden vor dem Hash-Zeitpunkt lag (Vorschlag *T* = 2 s) bzw. bei Zweifel Neuberechnung; PO legt *T* fest. |
| **OQ-076** | **`group_follow` für adoptierte Dateien** (Auftrag 5 §8.1, CONFLICT-017): Bestätigung als [V]? Definition „eindeutig zugeordnet“ (Vorschlag: Gruppenmitgliedschaft Evidenz 1 oder 3, Gruppe `active`, kein `ambiguous/conflict`)? Debounce-/Ruhezeit *T* (OQ-070/071)? Reichweite: nur Zielordner der vom Benutzer bewegten Komponente, nur gleicher Storage, keine Umbenennung? Verhalten bei Ordner-Moves (E06) und bei Namenskollisionen (Prüffall)? | Alles wie in Klammern vorgeschlagen; Ausführung nur in der Betriebsart mit verifiziertem `move_no_overwrite` (OQ-073), sonst Prüffall. |
| **OQ-077** | **Nachweis einer Papierkorboperation** (Auftrag 5 §8.2): Wird das Verfahren „File-ID per `getById` im Papierkorbbereich + Hash gleich Pre-Delete-Hash + Originalpfad leer + kanonische Datei unverändert; zweiter Beleg per WebDAV“ als ausreichend akzeptiert? Verhalten, wenn der Eintrag nicht mehr auffindbar ist (Retention, Fremdaktion)? | akzeptieren; nicht auffindbar ⇒ `needs_review`, nie Wiederholung; Pfadkonvention `files_trashbin/files/<name>.d<ts>` als **interne** Konvention hinter dem `FileGateway` kapseln und je Version testen. |
| **OQ-078** | **Befüllung der Fail-closed-Fähigkeitsmatrix** (Auftrag 5 §8.3): Allowlist aus CI-Läufen der Spike-Harness je Tupel (Version, Wrapperkette, Datenbank, Locking) **und** zusätzlicher Laufzeit-Selbsttest in einem Scratch-Ordner des Heim-Storages? Wer löst ihn aus, wann verfällt das Ergebnis? Mindestzahl der Wiederholungen der Race-Tests? | Beides, Konjunktion: Eintrag nur wahr, wenn Allowlist **und** deterministischer Selbsttest bestehen; FAIL/INCONCLUSIVE ⇒ falsch; Verfall bei Änderung von Nextcloud-/App-Version, Wrapperkette oder Locking-Konfiguration; Race-Wiederholungen legt der PO fest (Stichprobe: 150 je Fall, kein Beweis). Details `platform-spikes.md` §9.3. |

### P1
| ID | Frage | Vorschlag [Z] |
|---|---|---|
| OQ-079 | **Zielnamen-Normalisierung** (konkretisiert OQ-057): Ein Move auf einen NFD-Namen ersetzte eine NFC-benannte Datei (A05). Reservierungs- und Kollisionsschlüssel (`ns_key`) müssen normalisieren: NFC, Groß-/Kleinschreibung ja/nein? | NFC, plus Prüfung auf Groß-/Kleinschreibungs-Kollision unabhängig vom Dateisystem. |
| OQ-080 | **Ereignisse:** Der Quellknoten von `NodeRenamedEvent` liefert keine File-ID; ein Ordner-Move erzeugt kein Ereignis je Kind (E05, E06); Cache-Events sind zahlreich und ungeeignet als Auslöser. Verdichtungs- und Teilbaumabgleich-Regel? | Auslöser nur `Node*Event`; Zielknoten-ID verwenden; bei Ordner-Rename Teilbaum-Listing nach File-ID. |
| OQ-081 | **Weitere Storage-Klassen:** Welche Klassen sind für die Auslieferung relevant (Objektspeicher als Primärspeicher, NFS/SMB-Datadir, Gruppenordner, Server-Verschlüsselung, externe Speicher)? Reihenfolge nachfolgender Spikes? | siehe `platform-spikes.md` §11. |
| OQ-082 | **Locking-Varianten:** DB-basiertes Locking, `files_lock`-App (WebDAV-LOCK, Wrapper in der Kette) und Verhalten mit deaktiviertem `filelocking.enabled` (A12/A15 nur bei Überschreibschutz getestet). | Spike-Erweiterung vor Freigabe jeder Fähigkeit. |
| OQ-083 | **Fehlerklassifikation im `FileGateway`:** Mehrere Fehlerantworten täuschen: WebDAV `MOVE` liefert bei Sperrkonflikt HTTP 500 (nicht 423); Fehler aus After-Listenern erreichen den Aufrufer nicht, obwohl der Rename erfolgt ist (A16); ein PUT mit Antwort 423 hinterließ seinen Inhalt in einem Drittel der Konkurrenzversuche (A24: 50 von 150, A25: 48 von 150). Klassifikation „nur eindeutig bekannte Fehler gelten als nicht ausgeführt“ festschreiben? | ja (bereits Grundsatz T4/T5; nur die Ausnahmeliste konkretisieren). |

## Neue Konflikte (Vorlage für `decisions.md`)

| ID | Gegenstand | Stand |
|---|---|---|
| **CONFLICT-018** | `job-processing.md` §6.3.2/§6.3.5 und `DEC-ARC-010` setzen voraus, dass ein sicherer Move zumindest *prüfbar* ist; die Spikes zeigen: für `Node::move` und WebDAV `MOVE` nicht erfüllbar, für den Wrapper-Prototyp nur mit interner API. Die Produktanforderung „automatische Organisation“ kollidiert damit auf allen getesteten Tupeln mit dem verbindlichen Grundsatz „niemals überschreiben“. | offen → OQ-073 |
| **CONFLICT-019** | Mutationskern P5 (Sperren vor dem Storage-Aufruf) vs. Nicht-Komposierbarkeit eigener Sperren mit `Node::move` (C05). | offen → OQ-074 |
| **CONFLICT-020** | `nextcloud-integration.md` §2.2 („Move über Storage-Grenzen: Kopie + Löschen, neue ID“) vs. B07 (ID bleibt beim Move Heim→Freigabe-Storage erhalten). Dokumentationskorrektur; Beschränkung auf den Heim-Storage (REQ-SRC-06) bleibt aus anderen Gründen (Eigentum, Berechtigungen) bestehen. | Korrekturvorschlag in `platform-spikes.md` §10 |
| **CONFLICT-021** | `DEC-JOB-005` (Hash-Cache über Signatur) und Hash-Fortsetzung vs. Signaturlücke gleicher Sekunde (B06/F05). | offen → OQ-075 |

# Datenmodell – Beispiele und Zustandsänderungen

Stand: 2026-10-09 · Runde 3b · Status: **Illustrativ [T]**. Szenarien 1, 4, 10, 13, 14 wurden nach dem Review überarbeitet (3b); 15–20 sind neu. Crash- und Konkurrenzszenarien (26 Stück) stehen in `crash-recovery.md` §6; zu Szenario 18 gilt seit Runde 4 die Korrektur K1 (`entry_seq`, `data-model.md` §23.1). Die Beispiele verdeutlichen das Modell aus `data-model.md`; sie sind keine Testdaten und keine Spezifikation von Algorithmen. Konkrete Zahlenwerte (IDs, Zeiten) sind erfunden und konsistent gewählt. Alle Zusätze mit **[Z]** hängen von noch nicht bestätigten Vorschlägen ab (`open-questions.md`, „Runde 3“).

## 0. Gemeinsame Ausgangslage

| Merkmal | Wert |
|---|---|
| Benutzer | `alice` (aktiviert, nicht pausiert, keine Admin‑Pause) |
| Benutzerzeitzone | `Europe/Berlin` (`po_user_state.tz_iana`) |
| Gemeinsame Regelrevision | `po_user_rules_rev` **R1**: Sammlung `Fotos`, Layout `Jahr/Monat/Quelle`, `number_min_digits=2` |
| Quelle **S1** „Kamera“ | `po_source` 1, `po_source_rev` 1: `incoming`, Ordner `Eingang/Kamera`, `priority=100`, `date_strategy=exif_then_mtime`; Snapshot **K1** = (S1/rev1, R1); dauerhaft freigegeben (`po_approval` Typ `standing_source`) |
| Quelle **S2** „Messenger“ | `po_source` 2, rev 1: `incoming`, `Eingang/Messenger`, `priority=10`, `date_strategy=mtime`; Snapshot **M1**; freigegeben |
| Quelle **S3** „Altbestand“ | `po_source` 3, rev 1: `historical`, `Eingang/Altbestand`, `priority=50`; Snapshot **A1**; einmalige Plan‑Freigaben |
| Scan-Scopes (3b) | Scope **SC1** = Eingang Kamera (Quelle S1), **SC2** = Eingang Messenger (S2), **SC3** = Eingang Altbestand (S3), **SC9** = Sammlung `Fotos` (Scope `collection`, keine Quelle) |
| Notation | `inst#` = `po_file_instance.id`, `cont#` = `po_content.id`, `ref#` = `po_file_ref.id`, `prov#` = `po_provenance.id`, `op#` = `po_operation.id`. SHA‑256 gekürzt (`a1b2…`). Zeiten: `LOCALSEC` für Wanduhrzeit. |

Namensschema: `<Quelle> <YYYY-MM-DD> <HH-MM-SS> <NN>[ <Beschreibung>].<ext>`; Zielordner `Fotos/<Jahr>/<Monat>/<Quelle>`.

---

## Szenario 1 – Neuer Kameraimport
**Ausgang:** `alice` lädt `Eingang/Kamera/IMG_4711.JPG` hoch. EXIF `DateTimeOriginal = 2026:10:09 13:12:12`, kein Offset. MTime (Unix, UTC) `1791544332`.

| Schritt | Datensätze |
|---|---|
| 1 Event `NodeWritten` | `po_job` 1: `kind=change_event`, `dedup_key=nc:5001`, `state=queued`. Kein Hash im Listener (`DEC-JOB-002`). |
| 2 Beobachtung | `po_file_instance` **101**: `state=discovered`, `component_kind=image`, `name_orig_full=IMG_4711.JPG`, `first_seen_at=T0`. `po_file_ref` **201**: `storage_key=home::alice` (Original), `nc_file_id=5001`, `scope_id=SC1`, `path_rel=Eingang/Kamera/IMG_4711.JPG`, `current_key=101`, `nc_key=SHA-256(len‑präfixiert: storage_key, 5001)` (3b). |
| 3 Stabilität | Zwei Beobachtungen im Abstand ≥ Mindestalter (`sched_params`): `obs_streak=2`, `stable_since` gesetzt, `state=stable` (`DEC-STB-001/002`). |
| 4 Hash & Metadaten | `po_content` **301**: `sha256=a1b2…`; `inst101.content_id=301`, `state=hashed`; Hash‑Signatur gespeichert. `po_time_candidate`: (`exif_datetime_original`, raw `2026:10:09 13:12:12`, `local_naive=1791551532`, floating), (`mtime`, `utc_instant=1791544332`). |
| 5 Herkunft | `po_provenance` **401**: instance 101, scope SC1, source 1, `entry_ref_id=201`, `entry_kind=arrival`, snapshot K1, `origin_kind=incoming`, `priority_at_event=100`. `po_origin_decision` **A1**: content 301, provenance 401, `basis=initial`, `state=current`, `priority_used=100` (3b). |
| 6 Zeitentscheidung | `po_time_decision` **501**: `time_source=exif_datetime_original`, `local_sort_ts=1791551532`, `tz_basis=floating_local`, `tz_iana_used=NULL`, `reliability=high`. |
| 7 Kein Dedup‑Treffer | Kein aktiver `po_canonical` für 301, kein Tombstone → Import. Beschreibung: „IMG_4711“ gilt als eindeutig automatisch erzeugt → **nicht** angehängt (`DEC-NAM-011`). |
| 8 Reservierung | `po_name_reservation` **601**: `ns_key=H(fotos/2026/10/kamera ␟ kamera ␟ 1791551532)`, `seq=1`, `owner=instance 101`, `state=reserved`. |
| 9 Operation | `po_operation` **701** (`move_rename`, `actor=auto`), `intended` → `executing` → `done`; `pre_state`={Pfad, Größe, MTime, cont 301}, Soll = `Fotos/2026/10/Kamera/Kamera 2026-10-09 13-12-12 01.JPG`; `po_operation_event` Zeilen 1–5 (`intent_recorded`, `precondition_checked`, `executed`, `post_verified`, `done`). |
| 10 Referenz | ref 201 geschlossen (`valid_to`, `current_key=NULL`, `nc_key=NULL`); ref **202** neu: `scope_id=SC9` (Sammlung), `change_reason=app_move`, `op_id=701`. `inst101.scope_id=SC9`, `management_mode=organized`, `state=managed`. Der Eintritt in die Sammlung geschah durch **eigene Operation** (`op_id` gesetzt): **keine** Adoptions-Provenance (20.2). Integrität: Storage-Klasse `rename_same` nur, wenn für die Version/den Storage-Typ per Spike belegt; sonst `unknown`. Nachprüfung (Größe, MTime, `file_id`) ⇒ `po_integrity_check(post, meta, match)`, `integrity_state=meta_continuous`; ein nachgelagerter Hash-Job setzt später `hash_verified` (Szenario 20 für den Fall `unknown`). |
| 11 Kanon | `po_canonical` **801**: content 301, instance 101, `active_key=301`, `selected_by=auto`, `winning_provenance_id=401`. Reservierung 601 → `consumed`. |

**Integrität:** `meta_continuous` ist **kein** Inhaltsnachweis (Abschnitt 18); vollständig verifiziert erst nach `hash_verified`; genau ein aktives Kanon‑Exemplar (INV‑01); Event der eigenen Operation wird über `op_id`/Sollzustand erkannt und erzeugt keinen Folgejob (`DEC-NCI-005`).

---

## Szenario 2 – Messengerbild mit MTime‑Datum
**Ausgang:** `Eingang/Messenger/WhatsApp Image 2026-09-03 at 18.40.05.jpeg`; kein EXIF; MTime `1788453605` (UTC) = 2026‑09‑03 18:40:05 `Europe/Berlin`.

| Datensatz | Inhalt |
|---|---|
| `po_time_candidate` | nur `mtime`: `utc_instant=1788453605`, `plausibility=ok` |
| `po_provenance` 402 | source 2, snapshot M1, `priority_at_event=10` |
| `po_time_decision` 502 | `time_source=mtime`, `utc_instant=1788453605`, **`local_sort_ts=1788460805`**, `tz_iana_used=Europe/Berlin`, `tz_basis=mtime_user_tz`, `tz_source=app_setting`, `reliability=medium` |
| Name | `Messenger 2026-09-03 18-40-05 01.jpeg` (Originalname „WhatsApp Image …“ erkannter Messenger‑Standard → nicht angehängt; `name_orig_full` bleibt in `po_file_instance`) |
| Reservierung | `ns_key=H(fotos/2026/09/messenger ␟ messenger ␟ 1788460805)`, `seq=1` |
| Zielordner | `Fotos/2026/09/Messenger` |

**Varianten:** (a) Benutzerzeitzone nicht konfiguriert → Fallbackkette: Profil → Nextcloud‑Standard → `UTC`; `tz_source` hält die benutzte Stufe fest; bei `fallback_utc` `reliability=low` [Z `OQ-044`]. (b) MTime im Jahr 1970 → `plausibility=suspect`, trotzdem verwendbar nur wenn Fallback; Kennzeichnung statt Verwerfung (`DEC-DAT-014`).
**Integrität:** MTime ist Empfangsdatum, nicht Indexierungszeitpunkt (`DEC-DAT-003`): `first_seen_at` ist davon getrennt.

---

## Szenario 3 – Exaktes Duplikat
**Ausgang:** Szenario 1 abgeschlossen. `alice` legt dieselbe Datei als `Eingang/Kamera/Kopie/IMG_4711 (1).JPG` ab.

| Schritt | Datensätze |
|---|---|
| Beobachtung & Hash | `inst` **102** (`state=hashed`), `content_id=301` (vorhanden, kein neuer `po_content`). |
| Herkunft | `prov` 403 (instance 102, S1, K1) — Herkunft wird dokumentiert (`DEC-DUP-004`). |
| Dedup | Aktiver `po_canonical` 801 für 301 vorhanden → keine Kanon‑Wahl. `po_quarantine_item` **901**: instance 102, `kind=exact_duplicate`, `state=quarantined`, `canonical_instance_id=101`, `active_key=102`. Datei bleibt **unverändert** im Eingang (`DEC-DUP-003/005`). |
| Prüfbereich | `po_review_task`: `category=exact_duplicate`, `instance_id=102`, `dedup_key=quar:901`. |
| Später: manuelle Sammelaktion | Vor Ausführung: erneute Prüfung — Instanz 102 existiert (`file_id`), SHA unverändert, kanonische Instanz 101 aktiv und gleicher Hash, keine Gruppenabhängigkeit (`DEC-DUP-006/007`). `op` 702 (`trash`, `actor=user`), Journal; `po_deletion_event`: `subject_role=duplicate_copy`, `event_kind=trashed_by_app`, `actor=app_action`; Quarantäne 901 → `trashed_by_app`. **Kein Tombstone.** |

**Integrität:** höchstens ein kanonisches Exemplar (INV‑01); keine automatische Löschung; wiederherstellbar nur angeleitet (Journal enthält Originalpfad, Zeit, Hash; MVP‑Vorschlag).

---

## Szenario 4 – Höher priorisierte Herkunft wird nachträglich erkannt (3b überarbeitet)
**Ausgang:** Messengerbild aus Szenario 2 liegt kanonisch in `Fotos/2026/09/Messenger/Messenger 2026-09-03 18-40-05 01.jpeg` (`inst` 103, `cont` 310, `canonical` 810, `po_origin_decision` **D1** `basis=initial`, `provenance_id=402`, `priority_used=10`, kein Standort-Override). Später erscheint dieselbe Datei als `Eingang/Kamera/DSC_0099.JPG` mit EXIF `2026:09:03 18:39:58`, `OffsetTimeOriginal=+02:00`.

| Schritt | Datensätze |
|---|---|
| Beobachtung | `inst` **111** (`content_id=310`), `po_file_ref` mit Scope SC1 (Eintritt), `prov` **412** (S1, K1, `entry_kind=arrival`, `priority_at_event=100`, **eingefroren**). |
| Entscheidung | Herausforderer 100 > bisheriger Sieger `priority_used=10` (Variante F, 16.5); Herausforderer `provenance_eligible`; dauerhafte Freigabe von S1 gültig; kein Override ⇒ **neue** Zeile `po_origin_decision` **D2** (`basis=challenger_won`, `prev_decision_id=D1`, `prev_priority_used=10`, `approval_id`=Standing‑Freigabe S1); D1 → `superseded`, `current_key=NULL`. `po_provenance` 402 bleibt unverändert. |
| Zeit | `po_time_decision` **512** für prov 412: `local_sort_ts` = 18:39:58 lokal (`tz_basis=offset_local`) [Z `OQ-012`]. |
| Neuzuordnung | `po_plan_item` `reassign` (nicht-manuell, nach Freigabe), Reservierung 611 (`seq=1`), `op` 711: Move+Rename der kanonischen Instanz 103 nach `…/Kamera/Kamera 2026-09-03 18-39-58 01.jpeg`; `inst` 111 → `exact_duplicate`‑Quarantäne. Ist S1 pausiert: D2 wird trotzdem dokumentiert (`DEC-DAT-013`), die Ausführung wartet. |

**Gegenproben (3b):**
- **Nur Konfigurationsänderung:** `alice` senkt S1 später von 100 auf 5 (neue `po_source_rev`, neu freigegeben). **Keine** neue `po_origin_decision`, keine Operation: bestehende Entscheidungen bleiben (`DEC-DRY-007`); offene Plan-Items mit Komponente `priority_ctx` werden ungültig (Szenario 16).
- **Gleichstand:** Ein weiteres Herkunftsereignis mit Priorität 100 gegen `priority_used=100` ändert nichts; nur die Provenance wird dokumentiert.
- **Mit Standort-Override:** Zeile `po_origin_decision` mit `state=suppressed`; D2 wird nicht aktuell, keine Operation (`DEC-DAT-012`).
- **Adoptierter Inhalt:** Für adoptierte Sammlungsdateien bleibt `basis=adopted` aktuell; Herausforderer erzeugen `suppressed`‑Zeilen (CONFLICT-011).
**Integrität:** Move/Rename nur mit Intent, Fencing und Nachprüfung (Szenarien 14, 17, 20); Provenance wird nie geändert.

---

## Szenario 5 – pHash‑Ähnlichkeit ohne Blockierung
**Ausgang:** `IMG_5000.JPG` (cont 320) ist kanonisch importiert. `alice` legt eine leicht beschnittene Version `IMG_5000_crop.JPG` (andere Bytes, cont 321) in `Eingang/Kamera`.

| Schritt | Datensätze |
|---|---|
| Hash | `po_content` **321** (anderes SHA‑256 → **kein** exaktes Duplikat). |
| pHash | `po_phash` (cont 321, `dct64`, `status=ok`); `po_phash_band`: *b* Zeilen. Kandidatensuche über Bänder findet cont 320. |
| Paar | `po_similarity_pair`: `content_lo=320`, `content_hi=321`, `distance=<kleine Zahl>`, `state=open`. |
| Import | **Regulär** wie Szenario 1: eigener Kanon 821 für cont 321 (`DEC-PHA-002/004`). |
| Prüfbereich | `po_review_task`: `category=possible_duplicate`, `pair_id`, `dedup_key=pair:…`. |

**Integrität:** pHash löst weder Quarantäne noch Blockade noch Löschung aus; die Distanz ist Metainformation; kein quadratischer Paarvergleich (Bandindex).

---

## Szenario 6 – Manuelle Entscheidung „Kein Duplikat“
**Ausgang:** Paar (320, 321) aus Szenario 5 offen.

| Schritt | Datensätze |
|---|---|
| Entscheidung | `po_pair_decision` **1**: `(320,321)`, `decision=not_duplicate`, `active_key="320:321"`, `decided_by=alice`. Paar `state=dismissed`, `decision_id=1`. Review‑Aufgabe `resolved`. |
| Byteidentische Kopie von 321 | Später erscheint eine weitere Instanz mit cont 321. Paar `(320,321)` hat aktive Entscheidung → **kein** neues offenes Paar (`DEC-PHA-006`). |
| Anderes ähnliches Bild | Neues Bild cont 322 ist 321 ähnlich → neues Paar `(321,322)` `open`; die Entscheidung `(320,321)` wirkt **nicht** (`DEC-PHA-007`). |
| Widerruf | `revoked_at` gesetzt, `active_key=NULL`; Paar kann wieder `open` werden. |

**Integrität:** Entscheidung hängt an Inhaltsidentitäten, nicht an Pfaden oder `file_id`.

---

## Szenario 7 – RAW + JPEG + XMP‑Import
**Ausgang:** In `Eingang/Kamera` liegen `IMG_2001.CR3`, `IMG_2001.JPG`, `IMG_2001.xmp`. EXIF beider Bilder `2026:10:01 08:15:40`; keine eindeutige Metadatenreferenz.

| Schritt | Datensätze |
|---|---|
| Instanzen/Inhalte | `inst` 121/122/123, `cont` 331 (CR3), 332 (JPG), 333 (XMP). `component_kind` `raw`, `image`, `sidecar_xmp`. |
| Evidenz | Stufe 2: kompatible Dateiarten, gleicher Ordner, gleicher Basisname, plausibler Zeitstempel; keine konkurrierende Hypothese. |
| Gruppe | `po_media_group` **11** (`raw_jpeg`, `state=open→complete`, `confidence=heuristic`). `po_group_member`: (11,331,`raw`), (11,332,`jpeg`), (11,333,`xmp_sidecar`) alle `active`, `assignment=auto`, `evidence_level=2`. |
| Zeit | Zeitentscheidung der Gruppe aus dem Bild‑EXIF (Primärkomponente); `local_sort_ts=1790842540`. |
| Nummer | **Eine** Reservierung (`owner=group 11`, `seq=1`) für alle drei. |
| Operation | Elternoperation `group_move` 720; Kinder 721/722/723 mit Zielen `Kamera 2026-10-01 08-15-40 01.CR3 / .JPG / .xmp` in `Fotos/2026/10/Kamera`. |
| Kanon | je Inhalt ein `po_canonical` (komponentenweise, `DEC-GRP-008`). |

**Integrität:** Gleicher Basisname/Nummer/Ordner (`DEC-NAM-010`); Elternoperation `done` erst nach allen Kindern; fehlt später die XMP, ist die Gruppe trotzdem gültig (`DEC-GRP-005`).

---

## Szenario 8 – Live Photo mit nachträglich eintreffender MOV‑Komponente
**Ausgang:** `IMG_3001.HEIC` trägt eine Apple‑Content‑Identifier‑Referenz (Verfügbarkeit des Extraktors: **Capability**; auf der Referenz ohne ExifTool nicht gegeben – dann bleibt nur Evidenzstufe 2 bzw. `ambiguous`; hier angenommen: Extraktor vorhanden).

| Schritt | Datensätze |
|---|---|
| T0 HEIC | `inst` 131, `cont` 341; Gruppe **12** (`live_photo`), `state=waiting_complete`, `wait_until=T0+Wartezeit` (`DEC-GRP-006`). Mitglied (12,341,`still`) `active`, Evidenzstufe 1. |
| Wartezeit abgelaufen | Gruppe `open`; HEIC wird als gültiges Einzelmedium importiert (`DEC-GRP-004/005`): Reservierung `seq=1`, Name `Kamera … 01.HEIC` → Gruppe hat `name_reservation_id`. |
| T1 MOV trifft ein | `inst` 132, `cont` 342; Identifier gleich → (12,342,`live_video`) `active`, Evidenzstufe 1. |
| Plan‑gebundene Eingaben | Zeit der MOV (QuickTime, UTC) wird mit Gruppenzeit verglichen; stimmt (Toleranz) → Zeitentscheidung der Gruppe bleibt; **weicht ab** → kein stilles Umbenennen, Prüfaufgabe `group_ambiguous` (`DEC-NAM-013`). |
| Nachziehen | Op `move_rename` (Kind einer Gruppenoperation) benennt MOV nach `Kamera … 01.MOV` in denselben Ordner; Reservierung der Gruppe wird weiterverwendet (kein neues `seq`). Ist der Zielname durch Fremddatei belegt → `needs_review`, Reservierung nicht verbrannt, Prüffall. |

**Integrität:** Neue Komponente ergänzt vorhandene Gruppe (`DEC-GRP-007`, `DEC-GRP-015`); Nummer bleibt konstant.

---

## Szenario 9 – Teilweise duplizierte Mediengruppe
**Ausgang:** Kanonisch in der Sammlung: `JPG` (cont 351, Gruppe 13 mit nur diesem Mitglied, `open`, Name `Kamera … 05.JPG`). Neu in `Eingang/Kamera`: `IMG_7000.CR3` (cont 352, neu) + `IMG_7000.JPG` (byteidentisch zu cont 351).

| Schritt | Datensätze |
|---|---|
| Inhalte | JPG ⇒ cont 351 (vorhanden); CR3 ⇒ cont 352 (neu). |
| Dedup (komponentenweise) | JPG: aktiver Kanon 851 → neue Instanz 142 → `po_quarantine_item` `exact_duplicate`. CR3: neu. |
| Gruppenzuordnung | CR3 hat Evidenz Stufe 2 zu **genau einer** bestehenden Gruppe (13: gleiche Zeit/Basisname/kompatibel, kanonisches JPG liegt dort) ⇒ (13,352,`raw`) `active` (`DEC-GRP-015`). Wäre mehr als eine Gruppe plausibel ⇒ `ambiguous` + Prüfaufgabe. |
| Nummer/Name | RAW übernimmt Gruppen‑`seq=05` und Basisnamen; Operation `move_rename` des CR3 in den Ordner des kanonischen JPG. |
| Ergebnis | Kanon: JPG (alt, 851), CR3 (neu, 852); Duplikat‑JPG im Eingang quarantänisiert. |

**Integrität:** Keine Verdopplung des JPG; keine automatische Löschung der Kopie; Gruppe konsistent.

---

## Szenario 10 – Gemeinsamer JPEG‑Inhalt zweier RAW‑Gruppen (CONFLICT-008) (3b überarbeitet)
**Ausgang:** `Eingang/Kamera/Tag1/`: `IMG_0001.CR3` (cont 361), `IMG_0001.JPG` (cont **X=363**). `Eingang/Kamera/Tag2/`: `IMG_0002.CR3` (cont 362), `IMG_0002.JPG` (byteidentisch ⇒ ebenfalls cont **363**). Zeiten innerhalb der Toleranz.

**Korrektur gegenüber Runde 3:** Der erste Entwurf importierte die beiden RAW-Dateien, obwohl die Zuordnung des JPEG ungeklärt war. Das riss mehrdeutige Gruppen durch automatische Teiloperationen auseinander und widersprach `DEC-GRP-003`. Konservativer Standard jetzt: **nichts** der Konfliktmenge wird bewegt.

| Schritt | Datensätze |
|---|---|
| Instanzen | `inst` 151 (CR3‑A), 152 (JPG‑Tag1), 153 (CR3‑B), 154 (JPG‑Tag2); 152 und 154 beide `content_id=363`; alle im Scope SC1, `management_mode=none`. |
| Gruppen | Gruppe **A** (21): (21,361,`raw`), (21,363,`jpeg`; Evidenz Instanz 152). Gruppe **B** (22): (22,362,`raw`), (22,363,`jpeg`; Evidenz Instanz 154). |
| Konflikterkennung | Inhalt 363 steht in zwei Gruppen ⇒ beide Mitgliedszeilen `conflict`; beide Gruppen `needs_review`; **eine** Prüfaufgabe `conflict/group_shared_component` (`dedup_key=shared:363`) mit allen vier Bestandteilen. |
| Halten | **Alle vier** Instanzen erhalten `po_quarantine_item(kind=held_conflict)`; Dateien unverändert im Eingang. Keine Reservierung, keine Plan-Items für diese Gruppen, keine Operation, keine Kanon-Wahl (INV G3/G5). |
| Prüfung | Der Ausführer verweigert Teiloperationen an Gruppen mit `needs_review` (G5). Wird dennoch ein Plan-Item erzeugt (Fehler), schlägt die Ausführung vor dem Intent fehl. |
| Auflösung (Beispiel O1) | `alice` wählt „nur Gruppe A“: (21,363) → `active`, (22,363) → `rejected`; Gruppe A wird vollständig importiert (Reservierung `seq`, Kanon für 361 und 363, Instanz 152 kanonisch); Gruppe B (nur RAW‑B) wird danach als Gruppe ohne JPEG normal importiert (`DEC-GRP-005`: fehlende optionale Begleitdatei blockiert nicht, weil die Zuordnung jetzt geklärt ist); Instanz 154 → `exact_duplicate`‑Quarantäne. Journal: manuelle Entscheidung (`DEC-JNL-009`). |
| Alternative P2 (nicht Standard) | „RAW sofort importieren“: nur CR3‑A/CR3‑B regulär, JPEGs gehalten — bedürfte ausdrücklicher Freigabe (OQ-048, CONFLICT-012). |

**Prüfaufgaben:** (1) Auflösung der geteilten Datei (O1–O5); (2) falls O3 (Zusammenführen): Gruppen `merged`, ein `seq`.
**Integrität:** Keine Kopie, keine Löschung, kein Verschieben; höchstens ein aktives Kanon‑Exemplar je Inhalt; die Konfliktmenge ist vollständig gehalten (G5).

---

## Szenario 11 – Manuelle Umbenennung und Verschiebung einer Mediengruppe
**Ausgang:** Gruppe 11 aus Szenario 7 liegt in `Fotos/2026/10/Kamera` (`Kamera 2026-10-01 08-15-40 01.CR3/.JPG/.xmp`).

| Schritt | Datensätze |
|---|---|
| A: Umbenennung der JPG durch `alice` im Web | `NodeRenamedEvent` → `po_job` `change_event`. Journalabgleich: keine passende App‑Operation ⇒ **manuell**. Neuer Name `Kamera 2026-10-01 08-15-40 01 Sonnenaufgang.JPG` entspricht Schema + Beschreibung ⇒ `po_override` (`kind=description`, `value_text=Sonnenaufgang`, scope group 11). `po_file_ref` neue Zeile (`change_reason=rename`). |
| B: Angleichen | Nach Debounce `group_move`‑Elternoperation 730; Kinder benennen CR3/XMP nach `… 01 Sonnenaufgang.CR3/.xmp` (gleiche Nummer, `DEC-NAM-006/010`). |
| C: Verschiebung der JPG nach `Fotos/2026/Urlaub` (innerhalb Sammlung) | Event `NodeRenamed` (Pfad) ⇒ manuelle Verschiebung. `po_override` (`kind=location`, `value_text=Fotos/2026/Urlaub`, scope group 11, `set_via=move_detected`). Nach kurzer Verzögerung ziehen CR3/XMP nach (Eltern‑/Kindoperationen), Namen unverändert. |
| D: spätere Reassign‑/Reorganisationsplanung | Aktives `location`‑Override ⇒ Gruppe wird **übersprungen** (`DEC-GRP-012`, `DEC-DRY-009`). |
| E: Verschiebung der JPG **außerhalb** der Sammlung | Prüffall `outside_collection_move`; **keine** Mitnahme; kein Override, kein Tombstone (`DEC-GRP-011`, Löschereignis `moved_out_of_scope`). |
| F: Manuelle Änderung am **Präfix/Nummer** | z. B. „Kamera“→„Urlaub“: Namensschema verletzt ⇒ `po_override(kind=name_lock)` + Prüfaufgabe `name_modified`; **keine** automatische Korrektur (`DEC-NAM-012`). |

**Integrität:** Manuelle Aktionen sind nachvollziehbar (`set_via`, Referenzhistorie); Nummer 01 bleibt reserviert und unverändert.

---

## Szenario 12 – Löschung des kanonischen Originals mit verbleibender Quarantänekopie
**Ausgang:** Szenario 3 vor der Sammelaktion: kanonisch `inst` 101 (cont 301), Quarantänekopie `inst` 102 (`quarantined`).

| Schritt | Datensätze |
|---|---|
| Löschung | `alice` löscht `Fotos/2026/10/Kamera/Kamera 2026-10-09 13-12-12 01.JPG` in Nextcloud ⇒ Event `NodeDeleted` (oder Scan: `file_id` verschwunden). Papierkorb‑Prüfung: im Papierkorb (`trash_state=in_trash`) ⇒ `certainty=confirmed`. |
| Ereignis | `po_deletion_event` **1**: `instance_id=101`, `subject_role=canonical`, `event_kind=deleted_to_trash`, `actor=nextcloud_external`, `detected_via=event`, `certainty=confirmed`, `canonical_id=801`, `winning_provenance_id=401`, `group_id=NULL`. |
| Folge | `inst` 101 → `gone`; `po_canonical` 801 → `lost` (`active_key=NULL`). **`po_tombstone` 1**: content 301, `state=active`, `active_key=301`, `former_canonical_id=801`, `winning_provenance_id=401`; `po_tombstone_event` `created` + `quarantine_copy_present` (instance 102). |
| Kopie 102 | **bleibt** `quarantined`; **nicht** zum Kanon befördert (`DEC-GRP-014`). Prüfaufgabe `conflict/previously_deleted` (`dedup_key=tomb:1`), gemeinsam für Tombstone und Kopie. |
| Wiederauftauchen | Neue Instanz mit cont 301 in `Eingang/Kamera` ⇒ `po_tombstone_event reappearance_seen`; **kein** Import (`held_conflict`), **keine** Löschung, derselbe Prüffall (idempotent). |
| Wiederzulassung | `alice` bestätigt ausdrücklich: Tombstone `state=readmitted`, Event `readmitted`, `active_key=NULL`. Erst danach kann Kopie 102 (oder die neue) über einen normalen Plan importiert/als Kanon bestimmt werden; neue Reservierung, neuer Kanon. |
| Gegenprobe | Löscht `alice` nur die **Kopie** 102 ⇒ `deletion_event(duplicate_copy)`, Quarantäne `removed_external`, **kein** Tombstone. |

**Integrität:** Automatische Wiederaufnahme ausgeschlossen (`DEC-DUP-008/009`); Verlust ohne Bestätigung (z. B. `lost`) erzeugt keinen Tombstone; Papierkorb‑Rückholung durch den Benutzer ist ein offener Sonderfall (CONFLICT-009, `OQ-046`).

---

## Szenario 13 – Regeländerung während eines teilweise ausgeführten Importplans
**Ausgang:** Historische Quelle S3, Plan **P1** (`kind=historical_once`, Snapshot A1) mit 1000 Items; `alice` hat den Plan freigegeben. 400 Items `done` (Journal vorhanden), 599 `approved`, 1 `executing` (Item 415). Items vom Typ `adopt` kommen seit Runde 3b nur noch als *Anzeige einer Indexierungsvorschau* vor; die Sammlungsindexierung selbst erzeugt keine Plan-Items (Abschnitt 15). Hier im Beispiel hängen sie, soweit vorhanden, nur an `scope`; `move_rename`‑Items an `date`, `naming`, `target`, `scope`.

| Schritt | Datensätze |
|---|---|
| Änderung 1: Beschreibungsregel (Namen) | Neue `po_source_rev` 2 (nur `naming_params` geändert) + neuer Snapshot **A2** mit neuem `naming_fp`; `date_fp`, `target_fp`, `scope_fp`, `group_fp` unverändert. `po_source.current_rev_id=2`, `effective_rev_id` bleibt 1 bis zur neuen Freigabe. |
| Selektive Invalidierung | Job vergleicht `po_plan_item_dep`: `move_rename`‑Items mit Komponente `naming` ⇒ `invalidated_by_rule_change`; `adopt`‑Items (nur `scope`) **bleiben** `approved`. Item 415: Operation bereits `intended` unter gültiger Freigabe ⇒ wird per Roll‑forward beendet; vor dem **nächsten** Item prüft der Executor die Gültigkeit erneut (Item‑Ebene). |
| Abgeschlossene Operationen | 400 `done` bleiben unverändert (`DEC-DRY-007`). |
| Neue Planung | Plan **P2** (Snapshot A2) nur für invalidierte Items, eigener Dry‑Run, neue Freigabe. |
| Änderung 2: `sched_params.min_age` ändern | **Keine** Revision, keine Invalidierung (nicht fingerprintet). |
| Änderung 3: Quelle pausieren | `po_source.source_paused=true`; keine Revision, keine Invalidierung, keine Neuzuordnung; **Soll‑Orte** (provenance‑basiert) unverändert (`DEC-DRY-014`, `DEC-DAT-013`). |
| Änderung 5 (3b): Nummernformat von 2 auf 3 Stellen | Neue `po_user_rules_rev`; `namepat_fp` ändert sich (Stellenzahl ist Teil von `naming`) ⇒ offene `move_rename`‑Items ungültig, da der angezeigte Name (`05` → `005`) sich ändern würde. Bereits vergebene Nummern und angelegte Namen bleiben (keine Umnummerierung). |
| Änderung 4: Benutzerzeitzone geändert (gemeinsame Regel) | Neue `po_user_rules_rev` R2 ⇒ nur `tz_fp` ändert sich ⇒ `date_fp` aller Quellen ändert sich, `naming_fp`/`target_fp` nicht (`namepat_fp`, `collection_fp` unverändert): nur Items mit Komponente `date` werden ungültig. |
| Priorität: S1 hoch → niedriger als S3 | `priority_ctx` für Items, die zwischen S1 und S3 konkurrieren, ändert sich; Items ohne konkurrierende Herkunft bleiben gültig. |

**Integrität:** Freigaben werden nur dort ungültig, wo verwendete Komponenten sich änderten; Pausen sind keine Regeländerung.

---

## Szenario 14 – Crash nach der ersten von mehreren Dateioperationen
**Ausgang:** Gruppenoperation 740 (`group_move`) mit Kindern 741 (CR3), 742 (JPG), 743 (XMP); Gruppen‑Reservierung 640 `reserved`. Worker W1 hält `po_lease` `user:alice` mit `fence=7`.

| Zeitpunkt | Zustand |
|---|---|
| t0 | 740/741/742/743 `intended` (Intents committet), `pre_state` gespeichert. |
| t1 | 741: `executing` → Move erfolgreich → Nachzustand verifiziert → `done`; Ref neue Zeile; Kanon‑Daten fortgeschrieben. |
| t2 | 742: `executing` (Event `executed` noch **nicht** geschrieben). **W1 stürzt ab** (Prozess beendet). Unklar, ob der Move der JPG stattfand. |
| t3 | Lease `expires_at` überschritten. W2 übernimmt: `fence=8`. Spätere **Datenbank**‑Schreibversuche von W1 mit `fence=7` werden abgelehnt. Ob W1 (falls nicht abgestürzt, sondern nur blockiert) noch einen Storage‑Aufruf absetzt, regelt das Fencing **nicht** — siehe Szenario 17. |
| Recovery 742 | Beobachtung per interner Instanz → aktuelle `po_file_ref`/Knoten: **Fall A** Datei liegt am Sollort ⇒ `done` (`recovered`), Referenz nachgetragen. **Fall B** Datei liegt am Vorzustand ⇒ Operation erneut (Revalidierung → Move). **Fall C** weder noch ⇒ `needs_review`, Prüfaufgabe `integrity`, Quelle pausiert (`DEC-JOB-011`). **Fall D** Zielname fremd belegt ⇒ Reservierung `burned(foreign_collision)` oder Prüffall; nie überschreiben. |
| 743 | `intended`, nicht gestartet ⇒ nach Recovery regulär; Roll‑forward, **kein** Rollback von 741. |
| Abschluss | Alle Kinder `done` ⇒ 740 `done`, Reservierung 640 `consumed`. Bis dahin: Gruppe im erkennbaren Zwischenzustand (Eltern‑Op nicht `done`); das Ereignis‑Matching der laufenden Operationen verhindert, dass die Teilbewegung als **manuelle Verschiebung** gedeutet wird (`DEC-NCI-005`). |

**Integrität:** Intent vor Mutation; Idempotenz über `idem_key`; Dateiinhalt nie bewusst verändert; keine Nummern wiederverwendet; abgestürzter Worker kann nach Lease‑Übernahme **nichts mehr in die Datenbank schreiben**. Recovery‑abgeschlossene Operationen (Fall A) gelten als `copy_possible` ⇒ Neuhash des Ziels (Abschnitt 18.3), weil der Zustand beim Absturz unbekannt ist.

---

## Szenario 15 – Erster Indexlauf einer bereits vorhandenen Fotosammlung (3b)
**Ausgang:** `alice` besitzt `Fotos/` mit bereits organisierten Dateien (Beispiel: `Fotos/2019/07/Urlaub/IMG_1.JPG`, `IMG_2.JPG`, eine Kopie `Fotos/Sicherung/IMG_1_copy.JPG`, ein RAW+JPEG‑Paar). Sie konfiguriert **keine** Eingangsquelle; sie aktiviert die Sammlungsindexierung.

| Schritt | Datensätze / Zustandsänderungen |
|---|---|
| Scope | `po_scan_scope` **SC9**: `scope_kind=collection`, `source_id=NULL`, `collection_key=alice`, `index_state=not_indexed → indexing`. Snapshot `snapshot_kind=collection` (`source_rev_id=NULL`, `snap_key` aus Revision R1). |
| Vorschau | Bericht: Dateizahl, erkannte exakte Duplikate, erkannte Gruppen (mit Evidenzstufe), nicht lesbare Dateien. Keine Dateiänderung, keine Freigabe nötig (Indexierung ist rein lesend). |
| Fortschritt | `po_scan_cursor`: `scope_id=SC9`, `scan_kind=full`, `trigger=first_index`, `state=running`, `active_key="SC9:full"`. |
| Instanzen/Referenzen | je Datei `po_file_instance` (`management_mode=adopted`, `state=hashed → managed`), `po_file_ref` (`scope_id=SC9`, `nc_key` aus Originalspalten), `po_content` (SHA‑256). |
| Herkunft | `po_provenance`: `origin_kind=collection_adopted`, `entry_kind=adoption`, `scope_id=SC9`, `source_id=NULL`, `priority_at_event=NULL`, `entry_ref_id`= erste Referenz. |
| Herkunftsentscheidung | `po_origin_decision`: `basis=adopted`, `priority_used=NULL`, `state=current`. |
| Kanon | je Inhalt `po_canonical(selected_by=adopt)`. Für `IMG_1.JPG`/`IMG_1_copy.JPG` (gleicher Inhalt): kanonisch die Instanz mit der kleinsten `id`; die Kopie → `po_quarantine_item(kind=collection_duplicate)`, **keine** Verschiebung, **keine** Sammelaktion. |
| Gruppen | Das RAW+JPEG‑Paar im selben Ordner: Evidenzstufe 2 ⇒ nur als **Vorschlag**/`ambiguous` ohne Umbenennung (15.5 Punkt 4); Metadatenreferenz (Stufe 1) ⇒ `active`. |
| Abschluss | Cursor `completed`, `active_key=NULL`; `index_state=indexed`. |

**Constraints:** Unique `(collection_key)` (ein Sammlungs‑Scope je Benutzer); Unique `(active_key)` der Cursor; Unique `(instance_id, scope_id, entry_ref_id)` (Provenance); INV‑12 (adoptiert ⇒ nicht bewegen).
**Recovery:** Abbruch mitten im Lauf ⇒ `cursor_token` wiederaufnehmbar; bereits angelegte Instanzen/Referenzen sind idempotent (Unique `nc_key`, `(instance,scope,entry_ref)`).
**Prüfaufgaben:** `collection_duplicate` (gesammelt, niedrige Dringlichkeit); mehrdeutige Gruppen; nicht lesbare Dateien (`integrity`/`unsupported_media`).
**Täglicher Abgleich** danach: `scan_kind=reconcile`, `trigger=schedule`; neue Datei in der Sammlung (ohne App‑Operation) ⇒ Adoption; fehlende ⇒ `missing`; verschobene ⇒ neue Referenz, kein neuer Eintritt (gleicher Scope).

---

## Szenario 16 – Änderung der Quellenpriorität ohne neue Mediendateien (3b)
**Ausgang:** Wie Szenario 4 nach D2 (Kamera gewann gegen Messenger). Viele weitere Medien haben `po_origin_decision` aus beiden Quellen. `alice` ändert die Priorität von S1 „Kamera“ von 100 auf 5 und gibt die Änderung frei.

| Schritt | Datensätze / Zustandsänderungen |
|---|---|
| Revision | `po_source_rev` 2 (nur `priority`; `scope_fp`/`date_fp`/`naming_fp`/`target_fp`/`group_fp` unverändert); neuer Snapshot K2; `po_source.current_rev_id=2`. |
| Freigabe | Neuer Dry‑Run zeigt: **keine** Verschiebung bestehender Medien (da keine neuen Herkunftsereignisse). Freigabe ⇒ `effective_rev_id=2`, neue `po_approval(standing_source)`, alte `superseded`. |
| Herkunftsentscheidungen | **Keine** Änderung an `po_origin_decision`; **keine** Operation; `po_provenance` unverändert (`priority_at_event=100` bleibt als historische Tatsache). |
| Offene Plan-Items | Items mit Komponente `priority_ctx`, deren Rangfolge sich zwischen S1 und anderen änderte, ⇒ `invalidated_by_rule_change`. |
| Künftige Ereignisse | Neue Kamera‑Datei (Priorität 5) gegen bestehenden Sieger Messenger (`priority_used=10`): 5 < 10 ⇒ kein Wechsel. Ein künftiges Messenger‑Ereignis (10) gegen bestehenden Kamera‑Sieger (`priority_used=100` eingefroren): Variante F ⇒ kein Wechsel; Variante C (aktueller Rang 5) ⇒ Wechsel — offen, OQ‑041. |
| Allgemeine Neubewertung | Nur über `plan.kind=reassign_batch`/`reorganize`: eigener Dry‑Run, eigene Freigabe, `basis=batch_plan` bei Ausführung. Standort‑Overrides werden respektiert (`DEC-DAT-012`). |

**Constraints:** INV‑13; Unique `po_origin_decision.current_key`.
**Recovery:** keine Dateioperationen betroffen.
**Prüfaufgabe:** optional Hinweis „Neue Priorität gilt nur für künftige Importe; Neuorganisation anbieten“ (kein Zwang).

---

## Szenario 17 – Lease-Ablauf bei noch aktivem Worker unmittelbar vor einem Move (3b)
**Ausgang:** Operation 760 (`move`, Instanz 160, `intended`, `active_key=160`, `storage_class=rename_same`). Worker W1 hält `po_lease user:alice`, `fence=7`.

| Variante | Ablauf | Ergebnis |
|---|---|---|
| **a** Lease läuft *vor* dem Claim ab | W1 versucht den Claim; die Bedingung „Lease‑Fence stimmt und Ablauf liegt mindestens einen Sicherheitsabstand in der Zukunft“ ist falsch ⇒ 0 Zeilen | W1 darf nicht mutieren; Operation bleibt `intended`; W2 (`fence=8`) übernimmt |
| **b** Claim erfolgt; W1 stockt (Pause des Prozesses), Lease läuft ab, W2 übernimmt | W2 sieht Operation `executing` mit `executor_id=W1`, `exec_fence=7`, `claim_deadline` überschritten. W2 **bricht nicht ab** (INV‑11), sondern führt die Recovery: Beobachtung der Instanz 160 | Beobachtung *Vorzustand*: W2 setzt die Operation fort (Claim erneut mit `exec_fence=8` durch bedingtes Update auf `executor_id=W1`, `exec_fence=7`) und führt den Move aus. Setzt W1 danach fort, ist sein Ziel identisch (Ziel steht im Intent); der Aufruf scheitert am vorhandenen Ziel oder ist wirkungslos — **Verhalten ist per Spike zu verifizieren**. W1s DB‑Schreibversuche mit `fence=7` werden abgelehnt |
| **c** Lease läuft *während* des Storage‑Aufrufs ab | Aufruf läuft durch; Ergebnisschreiben mit `exec_fence=7` abgelehnt; Operation bleibt `executing` | W2 beobachtet Datei am Ziel ⇒ `recovered`; Integritätsklasse `copy_possible` für Recovery ⇒ Neuhash |
| **d** Zwei Worker versuchen, dieselbe Datei in zwei Operationen zu bearbeiten (z. B. `move` und `reassign`) | Zweiter Intent scheitert an Unique `po_operation.active_key=160` | Nur eine aktive Operation je Instanz |
| **e** Planänderung während `intended` | Abbruch `intended → aborted_precondition` per bedingtem Update gegen Claim eines Workers: genau einer gewinnt | Gewinnt der Claim, wird die Operation zu Ende geführt und nachträglich als „unter nicht mehr gültiger Regel“ zur Prüfung gestellt (Undo möglich) |

**Nicht schließbare Fenster (offen benannt):** (1) zwischen letzter Vorzustandsprüfung und `Node::move()`; (2) externer Eingriff in derselben Zeit; (3) Verhalten bei vorhandenem Ziel ohne Spike unbekannt. Sicherheitsabstand und Frist sind Konfigurationswerte, auf der Referenz zu messen.
**Prüfaufgaben:** `integrity` bei Abweichung; Hinweis „Operation nach Regeländerung beendet“.
**Constraints:** Unique `po_operation.active_key`; Claim‑Update mit Lease‑Bedingung; `po_lease.fence`.

---

## Szenario 18 – Erneuter Eingang derselben Dateiinstanz unter unveränderter Regelrevision (3b)
**Ausgang:** Instanz 170 (Kamera, Scope SC1, Referenz 270 = Eintritt, Provenance **P170**), Regelrevision unverändert (Snapshot K1).

| Variante | Ablauf | Datensätze |
|---|---|---|
| **a** Doppelte Zustellung desselben Events | zweiter Job verarbeitet dieselbe Referenz | Unique `(instance_id=170, scope_id=SC1, entry_ref_id=270)` verletzt ⇒ „bereits erfasst“; keine neue Provenance |
| **b** Neuer Scan, Datei unverändert | Scan findet 170 mit gleicher Signatur | keine neue Referenz, keine Provenance, nur `last_seen_at` |
| **c** Datei wird von der App in die Sammlung verschoben, dann vom Benutzer **zurück in den Eingang** gezogen | neue Referenz 271 mit `scope_id=SC1` nach Referenz im Scope SC9 ⇒ Scope‑Wechsel ⇒ **neuer Eintritt**: `po_provenance` **P170b** (`entry_kind=reentry`, `entry_ref_id=271`, gleicher Snapshot K1 — nur Unique `(instance, scope, entry_ref)` unterscheidet). `po_canonical` für den Inhalt → `retracted` (Grund `returned_to_incoming`), Prüfaufgabe `outside_collection_move`/„Verwaltete Datei zurück im Eingang“; **keine** automatische Weiterverarbeitung (Standard [Z, OQ-053]). Bestätigt `alice` „als erneuten Import behandeln“, läuft der normale Ablauf (neue Plan-Items, Reservierung, ggf. Herkunftsentscheidung). |
| **d** Neues physisches Exemplar desselben Inhalts | andere Datei, gleicher SHA‑256 | neue Instanz 171, neue Provenance (`arrival`); Dedup: `exact_duplicate`‑Quarantäne |
| **e** Verschiebung innerhalb des Eingangs (Unterordner) | neue Referenz, **gleicher** Scope | **kein** neuer Eintritt (20.1) |
| **f** Neue Regelrevision, Datei unverändert | – | keine Provenance; Neubewertung über Plan‑Items |

**Constraints:** INV‑16; Unique `(instance_id, scope_id, entry_ref_id)`; `po_file_ref.scope_id` als historische Tatsache.
**Recovery:** idempotent; Wiederholung nach Absturz erzeugt höchstens dieselbe Zeile.
**Prüfaufgaben:** nur bei c.

---

## Szenario 19 – Aktualisierung eines pHash-Algorithmus (3b)
**Ausgang:** `po_phash_algo` Zeile **1**: `dct64`/`v1`, `band_count=3`, `band_widths=21,21,22`, `probe_radius=2`, `guarantee_radius=8`, `state=active`, `active_key=dct64`. 300.000 Inhalte haben `po_phash`‑Zeilen unter `algo_row_id=1`. Zwischen Inhalten 320 und 321 existiert `po_pair_decision` (`not_duplicate`).

| Schritt | Datensätze / Zustandsänderungen |
|---|---|
| Registrierung | Zeile **2**: `dct64`/`v2` (geänderte Vorverarbeitung), neues Layout `band_count=4`, `band_widths=16,16,16,16`, `probe_radius=2`, `guarantee_radius=11`, `state=building`. Unique `(algo_id, algo_ver)` verhindert Doppelregistrierung; `active_key` bleibt bei Zeile 1. |
| Neuindizierung | Hintergrundjob (fortsetzbar, gedrosselt) berechnet für jeden Inhalt `po_phash(algo_row_id=2)` und `po_phash_band(algo_row_id=2, …)`. Vorhandene Zeilen unter `algo_row_id=1` bleiben unverändert. Neue Bilder erhalten beide Versionen. |
| Umschalten | Abdeckung ausreichend ⇒ Transaktion: Zeile 1 → `retired`, `active_key=NULL`; Zeile 2 → `active`, `active_key=dct64`. Das Unique auf `active_key` verhindert zwei aktive Versionen. |
| Befunde | Offene `po_similarity_pair(algo_row_id=1)` ⇒ `stale`, falls Version 2 sie nicht findet; neue Befunde unter `algo_row_id=2`. Die Prüfliste zeigt je `(lo,hi)` einen Befund (aktiver Algorithmus bevorzugt). |
| Manuelle Entscheidungen | `po_pair_decision(320,321)` bleibt unverändert und wirksam; findet Version 2 das Paar erneut, entsteht **kein** offener Befund. |
| Aufräumen | Bandzeilen von Zeile 1 nach Karenz löschbar; Hashes bleiben. |

**Constraints:** Unique `po_phash(user_id, content_id, algo_row_id)`; PK der Bandtabelle trägt `algo_row_id`.
**Recovery:** Neuindizierungsjob ist idempotent über die Unique‑Constraints; Abbruch ⇒ Fortsetzung per Cursor.
**Prüfaufgaben:** keine zwingenden; Hinweis „Suche unvollständig“ bei gekappten Suchen (19.3).
**Offen:** Layout und Kappungsgrenze erst nach Benchmark (19.6, OQ‑054).

---

## Szenario 20 – Storage‑Move mit unklarer Inhaltsintegrität (3b)
**Ausgang:** Operation 780 (`move`, Instanz 180, Inhalt 380 mit gespeichertem SHA‑256 `h380`). Der Storage‑Typ lässt sich nicht als lokal erkennen (oder das Spike‑Profil fehlt) ⇒ `storage_class=unknown`.

| Schritt | Datensätze / Zustandsänderungen |
|---|---|
| Vorab | Gespeicherte Hash‑Signatur älter als konfigurierte Frist ⇒ Vorab‑Neuhash: `po_integrity_check(phase=pre, method=sha256, result=match)`. |
| Move | Claim (`exec_fence`), Vorzustandsprüfung, `Node::move()`. |
| Nachprüfung | `po_integrity_check(phase=post, method=meta)`: `file_id` geändert, Größe gleich, MTime gleich. Wegen `unknown` zusätzlich `post, method=sha256`. |
| **Fall A** Hash gleich | Operation `done`, `integrity_state=hash_verified`. |
| **Fall B** Hash weicht ab | `result=mismatch`, Instanz `integrity_state=failed`, Operation `needs_review`, Prüfaufgabe `integrity` (kritisch), Quelle pausiert (`DEC-JOB-011`); der Vorab‑Hash (pre) belegt, dass der Inhalt *vor* dem Move noch `h380` war ⇒ Ursache liegt im Move‑Pfad; **keine** Papierkorb‑Aktion auf Duplikaten, kein weiterer Move des Inhalts. |
| **Fall C** Ziel nicht lesbar / Zeitüberschreitung | `result=unreadable`, `integrity_state=unconfirmable`, Wiederholung mit Backoff (Auftrag 4); bleibt sie erfolglos: Prüfaufgabe; nichts wird als verifiziert geführt. |
| **Fall D** `file_id` unerwartet verschwunden | Recovery per Beobachtung (Referenz‑Wiedererkennung nur bei Eindeutigkeit, 5.3); sonst Prüfaufgabe. |
| Folgeoperation | Eine Duplikat‑Sammelaktion für Kopien von 380 ist gesperrt, solange die kanonische Instanz nicht `hash_verified` ist (INV‑14). |

**Constraints:** INV‑14; `po_integrity_check` append‑only; Unique `po_operation.active_key`.
**Recovery:** Stürzt der Worker nach dem Move ab, behandelt die Recovery die Operation als `copy_possible` (Neuhash, 18.3).
**Hinweis:** `rename_same` mit belegtem Spike‑Profil würde nur `meta_continuous` setzen und den Hash später nachholen — nicht als vollständig verifiziert geführt, bis der Hash vorliegt.

---

## Zusammenfassung der geprüften Integritätsbedingungen
| Bedingung | Szenarien |
|---|---|
| Ein aktives Kanon‑Exemplar je Inhalt | 1, 3, 4, 9, 10, 12, 15 |
| Keine automatische Löschung / Datenverdopplung | 3, 10, 12 |
| Tombstone nur aus kanonischer Löschung; Kopie/Verlust nicht | 3, 12 |
| Pausen ändern keine Herkunft/Freigabe | 4, 13, 16 |
| Konfigurationsänderung ordnet nichts um | 4, 13, 16 |
| Überprüfung der Plan‑Eingaben vor Ausführung | 8, 13 |
| Nummern nie wiederverwendet; gemeinsam je Gruppe | 1, 4, 7, 8, 9, 14 |
| Journal, Intent‑Recovery, Fencing | 1, 14, 17 |
| Fencing schützt nur die Datenbank; Storage‑Restfenster benannt | 14, 17 |
| Integrität über Hash, nicht über Metadaten allein | 1, 14, 20 |
| Manuelle Overrides haben Vorrang | 4, 11, 13 |
| Zeitfelder getrennt | 1, 2, 4 |
| Unsichere Gruppen werden nicht auseinandergerissen | 10 |
| Sammlung wird indexiert, nicht importiert oder umgeordnet | 15 |
| Provenance idempotent und vollständig | 18 |
| pHash‑Versionierung ohne Verlust manueller Entscheidungen | 19 |

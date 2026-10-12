# Crash-Recovery, Konkurrenz und Fehlerszenarien

Stand: 2026-10-09 · Runde 4b (Auftrag 4b: Szenarien gegen die korrigierten Automaten geprüft) · Status: **Entwurf [T]**, Vorschläge **[Z]**. Kein Code. Voraussetzung: `job-processing.md` (Zustandsautomaten, Ablauf P0–P14 mit endgültiger Reihenfolge `intended → preverified → claimed → executing`, Übergänge T1–T21, Notation TX/SC/HC) und `data-model.md`.

Wichtig: Verhalten von `Node::move()` (bei vorhandenem Ziel, `file_id`-Erhalt, Atomizität), Dateisperren und Papierkorb ist für Nextcloud 34/35 **nicht verifiziert** (Spikes SP-A bis SP-F). Wo ein Szenario davon abhängt, steht „Spike“.

## 1. Grundsätze

1. **Eine Datenbanktransaktion ist keine atomare Dateisystemtransaktion.** Datenbank und Storage können nach einem Absturz verschiedene Stände zeigen. Beide Seiten sind Zeugen, keiner ist allein Wahrheit.
2. **Beobachten vor Handeln.** Jede Entscheidung in der Recovery stützt sich auf eine frische Beobachtung des Knotens (per `file_id`, danach per Referenz) und, wo gefordert, einen Hash.
3. **Ruhefrist.** Eine unklare Operation wird erst bewertet, wenn der frühere Besitzer sicher nichts mehr beginnen kann: Lease abgelaufen **und** `now > claim_deadline` **und** `now > quiet_until` (Sicherheitsabstand). Ablauf einer Frist ist nur die *Erlaubnis zu beobachten*, nie ein Schluss.
4. **Derselbe Intent.** Der neue Besitzer vollendet exakt das im Intent Festgelegte (Ziel, Name, Nummer). Er wechselt nie die Absicht, das Ziel oder die Reservierung.
5. **Kein blindes Wiederholen.** Wiederholt wird eine Mutation nur, wenn eine Beobachtung *nach* der Ruhefrist positiv zeigt, dass die Datei unverändert am Vorzustand liegt, und ein neuer Anspruch mit höherem Fencing erteilt wurde.
6. **Kein automatischer Rollback.** Weder Teilergebnisse einer Gruppe noch einzelne Verschiebungen werden von der App zurückgedreht (`DEC-JNL-008`). Rückgängig machen ist eine neue, freigegebene/ausgelöste Operation (`DEC-JNL-006`).
7. **Fehlen Beweise, gewinnt der Mensch:** Zustand `needs_review`, Prüfaufgabe, kein weiterer Zugriff auf diesen Inhalt (`DEC-JOB-011`).

## 2. Was Fencing und Lease leisten — und was nicht

| Schutz | Wirkung |
|---|---|
| Lease + Fencing-Token | Ein Worker, dessen Lease abgelaufen ist, kann **keine Datenbankzustände mehr ändern** (T8). |
| Exklusiver Operationsanspruch (`active_key`, Claim per bedingtem Update) | Zwei Worker können nicht dieselbe Operation oder dieselbe Instanz gleichzeitig beanspruchen. |
| Ziel im Intent | Ein später doch ausgeführter alter Aufruf führt dieselbe Zielverschiebung aus wie der neue Besitzer. |
| Nextcloud-Dateisperre (falls verfügbar) | Zusätzlicher Schutz gegen gleichzeitigen Zugriff innerhalb von Nextcloud; Wirksamkeit gegen andere Clients unverifiziert. Korrektheit hängt nicht davon ab. |
| Ruhefrist + Beobachtung | Der neue Besitzer wiederholt nur nach positivem Befund. |
| Pre-/Post-Hash | Abweichungen des Inhalts werden erkannt, nicht verhindert. |

**Nicht geschützt durch Fencing (ausdrücklich):** Ein Prozess, der nach seiner letzten Prüfung angehalten wird (Debugger, Swap, Container-Freeze, blockierter Netzwerk-Storage) und später `Node::move()` aufruft, obwohl ein anderer Worker die Operation übernommen hat. Das Fencing-Token betrifft nur die Datenbank (T8).

**Was stattdessen die Sicherheit trägt (4b, fail-closed, `DEC-ARC-010`):**
1. **Keine Automatik ohne verifiziertes „Kein Überschreiben“:** Ist für das erkannte (Version, Storage)-Tupel `move_no_overwrite` nicht verifiziert, laufen keine Moves (`job-processing.md` §6.3). Das Restrisiko „verspäteter Aufruf überschreibt etwas“ besteht dann nicht, weil die App nicht verschiebt.
2. **Mit verifiziertem `move_no_overwrite` und `move_source_by_path`:** Ein verspäteter zweiter Aufruf nach erfolgreichem ersten scheitert (Quelle am erwarteten Pfad fehlt); ein verspäteter Aufruf vor dem Aufruf des neuen Besitzers verschiebt dieselbe Datei an dasselbe Ziel; der zweite scheitert dann am vorhandenen Ziel. Ziel und Absicht sind im Intent festgelegt; der neue Besitzer wechselt sie nie.
3. **Ruhefrist, Beobachtung, Post-Hash** machen Abweichungen sichtbar; Nextcloud-Sperren sind zusätzlich.
4. **Rest:** Ein spät laufender Prozess kann eine Verschiebung ausführen, die der neue Besitzer nach einer Beobachtung „Datei am Vorzustand“ gerade selbst beginnt; wer zuerst ist, gewinnt, der andere scheitert (`target_exists`/`source_changed`). Mit dem Intent unveränderten Ergebnis. Eine Änderung, die zwischen P7 und dem Aufruf am Quellnamen erfolgt, ist ohne `move_source_by_path` weder erkennbar noch rekonstruierbar (§5.2).

Dieses Risiko ist nur nach Spikes SP-A1…A6, SP-I, SP-E, SP-F bewertbar; bis dahin gilt der Modus „Nur Indexierung und Analyse“ (OQ-055, OQ-069).

## 3. Recovery-Algorithmus

Auslöser: Dispatcher-Lauf (vor neuer Arbeit), Kontrollscan, Lease-Übernahme. Je Benutzer höchstens eine Recovery (über die `user:<id>:mut`-Lease). Kandidaten: Operationen in `intended`, `claimed`, `preverified`, `executing`, `executed`, `verifying` und Eltern in `executing`/`partial`, deren Besitzer ruht.

### 3.1 Datenquellen des Recovery-Workers
| Quelle | Inhalt |
|---|---|
| `po_operation` | Zustand, `pre_state`, Soll (`target_dir`, `target_name`), `executor_id`, `exec_fence`, `claim_deadline`, `quiet_until`, `recovery_epoch`, `parent_id` |
| `po_operation_event` | lückenlose Ereignisfolge (`intent_recorded`, `claimed`, `precondition_checked`, `execute_started`, `executed_observed`, `post_verified`, …) — letztes Ereignis zeigt, wie weit der Besitzer kam |
| `po_integrity_check` | vorhandene Pre-/Post-Hashes und Signaturen |
| `po_file_ref` / `po_file_instance` | aktuelle und frühere Referenz, `integrity_state` |
| `po_name_reservation` | `reserved`/`consumed`/`burned` |
| `po_lease` | Besitzer, Fence, Ablauf |
| Plan/Freigabe/Gruppe | Gültigkeit, `version`, Overrides, Pausen/Holds |
| Storage (Beobachtung) | Knoten per `file_id`: Existenz, Pfad, Name, Größe, MTime, ETag, Eigentümer; Existenz des Ziels; ggf. Papierkorbeintrag; Hash |

### 3.2 Ablauf je Operation
```
1. Kandidat gefunden → Ruhefrist prüfen (Lease, claim_deadline, quiet_until). Nicht erreicht → später.
2. Anspruch übernehmen: bedingtes Update (WHERE state=<gelesen> AND exec_fence=<alt>), neuer Fence, `recovery_epoch+1`. Ausführbar sind nur die Übergänge T7, T11, T12, T13, T14 (job-processing.md 5.8); **nie** ein Übergang nach `done` außer T15 durch den Post-Verifier.
   0 Zeilen → anderer war schneller; abbrechen.
3. Beobachten (nur Lesen): Knoten per file_id; Ziel; ggf. Papierkorb; Hash nach Bedarf.
4. Klassifizieren (Matrix 3.3), Ergebnis als Journal-Ereignis.
5. Handeln gemäß Matrix: fortsetzen, verifizieren oder zur Prüfung.
```
### 3.3 Entscheidungsmatrix für `executing` (Ausgang unbekannt)
| Beobachtung (nach Ruhefrist) | Schluss | Aktion |
|---|---|---|
| Knoten per `file_id` liegt exakt am Soll (Pfad, Name), Größe/MTime = `pre_state` | ausgeführt | Referenz nachtragen (P10) → Post-Verifikation (P12). Operation `executed`. |
| Knoten liegt am Vorzustand (Pfad/Name = `pre_state`), Signatur unverändert, Ziel frei | **nicht ausgeführt** (zum Beobachtungszeitpunkt) | **T12:** `executing → intended`, `outcome=known_not_executed`, nur wenn `move_no_overwrite` für das Tupel verifiziert ist; danach der **volle** Ablauf (neuer Pre-Hash, Claim, P7…) — nie direkt `executing`. Ohne verifizierte Fähigkeit: `needs_review`. |
| Knoten per `file_id` nicht auffindbar; genau eine Datei am Zielpfad mit gleicher Größe | `file_id` gewechselt (Kopieren/Löschen möglich) oder Löschung+Neuanlage | Hash der Zieldatei; gleich `content.sha256` ⇒ `executed` mit `recognition=operation`, Flag `file_id_changed`, Post-Verifikation; sonst `needs_review` |
| Knoten am Vorzustand **und** Datei am Zielpfad vorhanden | Kopie ohne Löschung oder fremde Datei am Ziel | `needs_review`; **nichts löschen**, beide hashen und anzeigen |
| Knoten an dritter Stelle (anderer Pfad/Name) | Benutzer oder anderer Prozess hat verändert | `needs_review`; Journal enthält Vorzustand |
| Knoten nicht auffindbar, Ziel leer | Datei gelöscht/verloren | `po_deletion_event` prüfen (Papierkorb); `needs_review`; kein Tombstone ohne Bestätigung |
| Storage nicht erreichbar | unbestimmt | Beobachtung später wiederholen (Backoff); **keine** Aktion, keine Wiederholung der Mutation |
| Signatur (Größe/MTime/ETag) weicht ab, Pfad unverändert | Inhalt geändert | nicht ausgeführt, aber Drift ⇒ Instanz `superseded` / M2 neu; Operation `aborted_precondition` |

### 3.4 Weitere Zustände
| Zustand | Aktion |
|---|---|
| `intended` | nichts passiert; nach Ruhefrist unverändert nutzbar; vor dem Pre-Hash und vor dem Claim Revalidierung (P1) |
| `preverified` | Pre-Hash gültig bis `pre_valid_until`; danach T5 `→ intended` (neuer Pre-Hash) |
| `claimed` | Storage sicher unberührt (kein `executing` committet) ⇒ T7 `→ preverified` (bei verstrichener Gültigkeit anschließend T5) |
| `executed` | Referenz prüfen/nachtragen; Post-Verifikation starten |
| `verifying` | Post-Hash fortsetzen (3.6 H2 nur mit gültigem Umschlag) oder neu beginnen; erst bei vollständiger Gleichheit T15 `done` |
| `needs_review` | **keine automatische Aktion.** Ausgänge nur durch Benutzer: T18 (`→ intended`), T19 (`→ verifying`, Hash wird **neu** berechnet), T20 (`→ closed_unverified`), T21 (`→ cancelled`). `needs_review → done` ist verboten; manuelle Entscheidung ersetzt die Verifikation nie. |
| Eltern `executing`/`partial` | je Kind nach obiger Matrix; Fortsetzung erst nach §5.1-Bedingungen |

### 3.5 Pausen und Recovery (4b)
Nach `job-processing.md` §5.12: Bei **Benutzer-, Quellen- und Benutzer-Admin-Pause** darf die Recovery **nur Metadaten** beobachten (Existenz, Pfad, Größe, MTime, ETag per `file_id`/Referenz) und das Ergebnis protokollieren; sie liest keine Inhalte, hasht nicht, beansprucht nicht, führt nichts aus. Im **globalen Wartungsmodus** findet auch keine Beobachtung statt. Operationen im Zustand `executing`/`executed`/`verifying` bleiben stehen und werden nach Aufhebung gemäß §3 behandelt. Ein kritischer Hold sperrt die Fortsetzung der Operationen des betroffenen Scopes.

## 4. Verifikation

### 4.1 Pre-Verifikation (App-veranlasste Verschiebung, `DEC-IMM-008`)
Vollständiger SHA-256 vor der Mutation; Gleichheit mit dem gespeicherten Hash; Größe/MTime/ETag vor und nach dem Hash gleich. Ergebnis hat Gültigkeit `pre_valid_until`. Abweichung des Hashes bei gleicher Signatur ⇒ kritischer Fehler (`failed`); Abweichung mit geänderter Signatur ⇒ Inhaltsänderung (Supersede), kein Integritätsfehler.

### 4.2 Post-Verifikation
Vollständiger SHA-256 des Zielknotens; Gleichheit mit Pre-Hash und gespeichertem Hash; Signatur des Zielknotens gegen die Beobachtung in P9. Erst danach `done`.

### 4.3 Vergleichsmatrix
| Pre | Post | Signatur nach Move vs. P9 | Ergebnis |
|---|---|---|---|
| = gespeichert | = gespeichert | gleich | `done`, `hash_verified` |
| = gespeichert | ≠ | gleich | **kritisch:** `failed`, Hold, Prüfaufgabe (Inhalt durch Move/Storage verändert, keine Reparatur) |
| = gespeichert | ≠ | geändert | `drifted`: nach dem Move extern verändert; neue Instanz/Review, niedrigere Dringlichkeit |
| ≠ gespeichert, Signatur zuvor geändert | – | – | Inhaltsänderung **vor** dem Move: Operation abgebrochen (`aborted_precondition`), Supersede |
| Post nicht lesbar/Zeitüberschreitung | – | – | `unconfirmable`; Wiederholung mit Backoff, bis Ergebnis oder Review; nie „verifiziert“ |

### 4.4 Was Hashes nicht leisten
Ein Hash belegt Bytes zum Lesezeitpunkt. Kein Beweis über Storage-interne Zustände oder spätere Verfälschung. Vor dem Move beobachtete Gleichheit schließt Veränderungen zwischen Pre-Hash und Move nicht aus; der Post-Hash erkennt sie.

## 5. Mediengruppen

### 5.1 RAW + JPEG + XMP: Absturz nach teilweiser Ausführung

**Ausgangszustand vor dem Absturz.** Gruppe 31 (`raw_jpeg` mit Sidecar), Elternoperation 900 (`group_move`), Kinder 901 (RAW), 902 (JPEG), 903 (XMP), gemeinsame Reservierung `seq=3` (`reserved`). Alle Intents mit Sollnamen `Kamera 2026-10-01 08-15-40 03.CR3 / .JPG / .xmp` in `Fotos/2026/10/Kamera` committet. Worker W1 (`exec_fence=7`).

**Zeitverlauf.**
- 901 (RAW): vollständig durchlaufen, `done` (Pre- und Post-Hash gleich). Referenz neu. Kanon fortgeschrieben.
- 902 (JPEG): Move ausgeführt, Referenz in TX-E geschrieben, Zustand `executed`; **Post-Hash nicht beendet** (Absturz während `verifying`) — oder alternativ: `executing` committet, Move möglicherweise ausgeführt, Absturz vor TX-E.
- 903 (XMP): `intended`, nicht angefasst, liegt noch im Eingang.
- W1 stürzt ab.

**Was der Recovery-Worker (W2, `fence=8`) in der Datenbank sieht (Fall JPEG `verifying`):**
| Objekt | Zustand |
|---|---|
| 900 Eltern | `executing` oder `partial`, kein Kind in Bearbeitung nach Lease-Ablauf |
| 901 | `done`, `integrity_state=hash_verified` |
| 902 | `verifying`, Referenz am Soll geschrieben, Pre-Check `match`, Post-Check fehlt oder unvollständig (`progress`) |
| 903 | `intended`, `active_key` gesetzt |
| Reservierung | `reserved` (noch nicht `consumed`, weil Eltern nicht `done`) |
| Lease | W1 abgelaufen |
| Ereignisse | letztes Ereignis von 902: `executed_observed` (bzw. `execute_started` im Alternativfall) |

**Was er im Storage beobachten kann:** RAW am Ziel; JPEG am Ziel (oder im Alternativfall: entweder am Ziel, am Vorzustand oder an keinem von beiden); XMP am Quellort.

**Prüfungen in dieser Reihenfolge:**
1. Ruhefrist für W1 (Lease, `claim_deadline`, `quiet_until`).
2. Anspruch auf 900 und die Kinder übernehmen (neuer Fence).
3. **902 zuerst klären** (jede nicht abgeschlossene Mutation vor der nächsten): 
   - *Fall `verifying`:* Post-Hash fortsetzen/neu; Signatur gegen die Beobachtung aus P9. Gleich ⇒ `done`. Abweichung ⇒ Matrix 4.3.
   - *Fall `executing`:* Matrix 3.3 (Ziel / Vorzustand / dritte Stelle).
4. 901 bleibt unberührt (`done`, verifiziert); stichprobenhaft nur Existenz am Soll prüfen.
5. Erst wenn **alle bereits begonnenen Kinder geklärt und `done`** sind (oder bekannt nicht ausgeführt), darf der Rest der Gruppe fortgesetzt werden.

**Bedingungen für die Fortsetzung mit 903 (XMP):**
- Kein Kind im Zustand `needs_review`/unbekannt.
- Gruppenversion unverändert (`pb_group_rev`): keine neue Komponente, kein Trennen/Zusammenführen.
- Keine manuelle Änderung an Komponenten seit dem Intent (Beobachtung aller Komponenten: Pfad, Name; kein neues Override; kein Namensänderungsereignis ohne passende Operation).
- Freigabe/Plan-Item gültig für die **Komponenten, die der verbleibende Teil nutzt** (selektive Fingerprints). Wurde zwischenzeitlich eine relevante Regel geändert: **nicht automatisch fortsetzen**; Prüfaufgabe „Gruppe teilweise verschoben, Regelwerk geändert“ mit Auswahl *Mit ursprünglichem Plan beenden* (neue Freigabe nötig) oder *Neu planen* (neuer Dry-Run für die Restkomponenten). Kein Zurückverschieben.
- Benutzer/Quelle nicht pausiert, kein kritischer Hold.
- Reservierung noch `reserved`, Zielname frei oder unverändert belegt von **eigenen** Komponenten.
- XMP unverändert am Vorzustand (Signatur, Pfad), Pre-Hash gültig oder neu.
- Ein mutierender Worker (Lease).

**Wenn eine Bedingung nicht erfüllt ist:** Eltern `needs_review`, Gruppe `needs_review` (G5: keine weiteren Teiloperationen), Prüfaufgabe mit allen Komponenten, Pfaden, Zuständen und Journalverweisen. Die Gruppe bleibt im Zwischenzustand (RAW+JPEG am Ziel, XMP am Quellort), **ohne Rollback**. Der Benutzer entscheidet.

**Zustände nach 4b:** RAW `done`; JPEG je nach Lage `executing` (Ausgang unbekannt), `executed` oder `verifying`; XMP `intended` oder `preverified`. Ein Kind in `closed_unverified` oder `needs_review` macht die Eltern `needs_review`; ein Kind wird nie über `needs_review → done` abgeschlossen. Operationstyp ist `import` (bzw. `reassign`/`reorganize`); Fortsetzung verlangt für jedes weitere Kind erneut die Typ-Autorisierung (job-processing.md 6.2).

**Selbstereignisse:** Die Beobachtungsjobs gleichen jede Komponente gegen ihre Operation ab. Die Teilbewegung wird dadurch nicht als manuelle Verschiebung der Gruppe gedeutet (kein `location`-Override).

**Ergebnis:** Der Recovery-Worker setzt entweder die Gruppe zu Ende (jede Komponente einzeln durch P3–P13) oder stoppt mit klarer Prüfaufgabe. Er verschiebt nie zurück, löscht nie, überschreibt nie.

### 5.2 Live Photo: manuelle Verschiebung der einen Komponente, gleichzeitige Umbenennung der anderen (4b überarbeitet)

**Lage.** Live Photo = HEIC (A) + MOV (B), Gruppe 40, beide in `Fotos/2026/10/Kamera`, `Kamera … 05.HEIC/.MOV`. Der Benutzer verschiebt **A** manuell nach `Fotos/2026/Urlaub`. Nach der Entprellung (`DEC-GRP-010`) will der Organizer **B** nachziehen (Operationstyp `group_follow`). Gleichzeitig benennt der Benutzer **B** um.

**Prinzip:** Manuelle Benutzeraktionen haben Vorrang vor automatischer Nachführung. Die App überschreibt keinen vom Benutzer gesetzten Namen, soweit sie ihn wahrnehmen kann, und verspricht nicht, was sie nicht belegen kann.

**Ablauf.**
1. Beobachtung A: Referenz geändert, keine passende Operation ⇒ manuell. `po_override(location, group 40)`; `group_follow` für B mit Autorisierung `user_action` und `pre_state` (Pfad, Name, Größe, MTime, ETag, `file_id`, `observed_at`) von B zum Planungszeitpunkt; Ziel = Zielordner von A, **Name von B bleibt wie er ist**.
2. Voraussetzung: Fähigkeiten verifiziert (sonst Prüffall „Nachziehen nicht möglich“, nichts geschieht).
3. In P1 und P3 wird B neu geladen. **Nachweislich beobachtet:** Pfad oder Name ≠ `pre_state` ⇒ T3/T8: keine Operation, die Umbenennung wird als manueller Zustand übernommen (`rename_detected`, Override/`name_lock`), Prüfaufgabe `group_manual_conflict` (Fakten: *A verschoben nach …*, *B umbenannt zu …*), Gruppe `needs_review` (G5), Nachführen ausgesetzt.
4. **Race zwischen P7 und dem Aufruf (P9):**
   - Mit verifiziertem `move_source_by_path`: Der Aufruf adressiert B über den in P7 belegten Pfad; ist B inzwischen umbenannt, schlägt er mit `source_changed` fehl ⇒ nichts überschrieben, Operation `aborted_precondition`, Prüfaufgabe (**nachweislich** geändert, Wert lesbar). 
   - Ohne diese Eigenschaft (Spike SP-I negativ) oder bei Umbenennung genau im Aufrufmoment bleibt ein Restfenster: Der Aufruf verschiebt B zum Sollnamen des Intents. Die Benutzerumbenennung ist dann **nicht rekonstruierbar**. Erkennbar höchstens als **Indiz** (Anomalie `event_during_op`: Änderungsereignis mit `hint=rename` im Fenster, ETag/MTime-Drift). Dann: Prüfaufgabe `possible_change_overwritten` mit Text „Möglicherweise wurde ein Name überschrieben; der ursprüngliche Wert ist unbekannt.“ — **kein Wert wird behauptet**.
5. **Undo:** bietet ausschließlich die Wiederherstellung des zuletzt belegten Zustands (P7-Name mit `observed_at`) an und weist darauf hin, dass ein später vom Benutzer vergebener Name nicht bekannt ist. Undo verspricht nichts, dessen ursprünglicher Wert unbekannt ist (`DEC-JNL-010`).
6. Umgekehrt: Umbenennung **nach** T9 (`executing`) ⇒ P10/T10 sieht Name ≠ Soll ⇒ `needs_review`, kein Überschreiben.
7. **Optionen für den Benutzer** (jeweils eigene Aktion): B an den Ort von A verschieben und den Namen von B beibehalten; B verschieben und auf den Gruppennamen angleichen (bewusst, mit Undo); nichts tun (Gruppe bleibt getrennt dokumentiert); Gruppe trennen.
8. **Konservative Zusatzregel (ohne `move_source_by_path`):** `group_follow` und Moves in der Sammlung werden erst nach einer Ruhezeit ausgeführt, in der an der Zielkomponente keine Änderungsereignisse eintrafen (Konfiguration).

**Zusammenfassung:** (a) Nachführen nur mit belegtem `pre_state` der Zielkomponente, (b) nachweisliche Abweichung ⇒ abbrechen und fragen, (c) nur Vermutetes ⇒ Warnung ohne Wertbehauptung, (d) nie überschreiben, nie zurückdrehen, (e) Gruppe `needs_review`, (f) Restfenster benannt.

## 6. Fehler- und Konkurrenzszenarien

### 6.0 Revisionsstand der 26 Szenarien (4b)
| Szenario | Status | Änderung |
|---|---|---|
| S1 Crash vor Intent | unverändert geprüft | – |
| S2 nach Intent | **überarbeitet** | Zustandsreihenfolge, Pre-Hash vor Claim |
| S3 Reservierung/Fremddatei | geprüft, redaktionell | – |
| S4 Pre-Hash | **überarbeitet** | Pre-Hash ist R-Job vor dem Claim |
| S5 vor `Node::move()` | **überarbeitet** | T9/T12, Fähigkeitsbedingung |
| S6 während `Node::move()` | geprüft, ergänzt | Fähigkeitsvermerk |
| S7 nach Move vor Journal | geprüft | T11 |
| S8 Hash-Nachprüfung | geprüft | H2-Regeln (3.6) |
| S9 Lease-Ablauf lang | **überarbeitet** | Fähigkeit, Ruhefrist |
| S10 zwei Worker | **überarbeitet** | Aufgaben der Zustände |
| S11 Datei verändert | geprüft | – |
| S12 Ziel existiert | **überarbeitet** | fail-closed, keine Plattformannahme |
| S13 Event während Operation | **überarbeitet** | Änderungszähler |
| S14 Benutzerpause | **überarbeitet** | Pausensemantik 4b |
| S15 Admin-Pause global | **überarbeitet** | Wartungsmodus |
| S16 Komponente gelöscht | geprüft, ergänzt | Eltern-Zustände |
| S17 Regeländerung | **überarbeitet** | Gruppe halb ausgeführt |
| S18 Storage nicht erreichbar | geprüft | – |
| S19 Papierkorb unklar | **überarbeitet** | Pre-Delete beider Exemplare |
| S20 Umbenennung | **überarbeitet** | Wissensklassen, Quelle über Pfad |
| S21 Teilweise Gruppe | geprüft (§5.1) | Typ `import`/`group_follow` |
| S22 Post-Hash weicht ab | **überarbeitet** | kein `done` über manuelle Entscheidung |
| S23 Crash TX-E → Hash | geprüft | – |
| S24 DB-Ausfall in TX-G | geprüft | – |
| S25 Hash-Kontext | **überarbeitet** | Stufen H1–H3 |
| S26 `file_id` neu | geprüft | – |
Querschnitt: In keinem Szenario wird ein nicht eindeutig geklärter Mutationszustand automatisch wiederholt; wo die Sicherheit nicht ausreicht, geht die Operation in `needs_review`.

Notation: **Persistiert** = Datenbankzustand zum Absturzzeitpunkt; **Storage** = mögliche tatsächliche Dateilage; **Recovery** = sichere Aktion; **Prüfung** = Bedingung für manuelle Prüfung. Schritt-Nummern P0–P14 aus `job-processing.md` §6.

### S1 – Crash vor dem Intent (P0–P1 oder TX-A nicht committet)
- **Ausgang:** Plan-Item `approved`, Worker revalidiert.
- **Unterbrechung:** Prozess stirbt vor Commit von TX-A.
- **Persistiert:** kein Operationsdatensatz, keine Reservierung (TX-A war atomar; Rollback).
- **Storage:** unverändert.
- **Recovery:** Keine. Das Item ist weiterhin `approved`; der nächste Lauf beginnt neu.
- **Prüfung:** nie.

### S2 – Crash nach dem Intent (TX-A committet, vor Pre-Hash)
- **Persistiert:** Operation `intended`, Reservierung `reserved` (Teil derselben TX), `active_key` gesetzt.
- **Storage:** unverändert.
- **Recovery:** Nach Ruhefrist ist nichts passiert; Operation bleibt `intended`. Revalidierung (P1). Gültig ⇒ R-Job `preverify` (T4); ungültig ⇒ T3 `aborted_precondition`, Reservierung `burned(aborted)`.
- **Prüfung:** nur wenn Revalidierung scheitert.

### S3 – Crash nach Zielnamenreservierung; Zielname inzwischen von Fremddatei belegt
- **Ausgang:** Reservierung `reserved`, Operation `intended` (S2), Zeit vergeht; Benutzer legt eine Datei mit dem Sollnamen im Zielordner an.
- **Persistiert:** wie S2.
- **Storage:** Sollname durch fremde Datei belegt.
- **Recovery:** Revalidierung (P3) erkennt Ziel belegt ⇒ Operation `aborted_precondition`, Reservierung `burned(foreign_collision)`, Neuplanung mit nächster Nummer. Kein Überschreiben (`DEC-JNL-008`).
- **Prüfung:** nein, sofern neue Nummer vergeben werden kann; bei Gruppen: Gruppe wartet bis zur Entscheidung, ob alle Komponenten umgeplant werden.

### S4 – Crash während des Pre-Hash (P3, R-Job)
- **Persistiert:** Operation `intended` (noch kein Claim, kein `preverified`); Job-Fortschritt (Umschlag) oder keiner; kein `pre`-Check.
- **Storage:** unverändert.
- **Recovery:** Job-Lease verfällt ⇒ Job zurück in die Queue; Pre-Hash wird mit gültigem Umschlag fortgesetzt oder neu begonnen (3.6). Es gibt keinen Anspruch und keine Operations-Lease; keine Mutation. T4 erst nach vollständigem Pre-Hash.
- **Prüfung:** nein.

### S5 – Crash unmittelbar vor `Node::move()` (nach TX-D, T9)
- **Persistiert:** Operation `executing`, `exec_fence`, `claim_deadline`, `exec_seen`.
- **Storage:** Datei am Vorzustand (Aufruf nicht gemacht) — oder, bei knapper Folge, Aufruf begonnen (siehe S6).
- **Recovery:** Nach Ruhefrist beobachten (nur über erlaubte Wege, 3.5). Datei am Vorzustand, Signatur gleich, Ziel frei **und** `move_no_overwrite` verifiziert ⇒ **T12** `→ intended` (`known_not_executed`), dann voller Ablauf mit **neuem Pre-Hash**. Fehlt die Fähigkeit: `needs_review` (die App verschiebt nicht). Kein Direktsprung nach `executing`.
- **Prüfung:** Signatur geändert, Ziel belegt, Datei woanders.

### S6 – Crash während `Node::move()`
- **Persistiert:** `executing`.
- **Storage:** denkbar: (a) nicht begonnen, (b) vollständig ausgeführt, (c) Teilzustand je nach Storage (Dateisystemoperation vollendet, Cache nicht aktualisiert; bei Kopie+Löschung: Ziel existiert, Quelle noch da). Welche Teilzustände möglich sind, ist ohne Spike SP-B/SP-C **unbekannt**.
- **Recovery:** Matrix 3.3 mit zusätzlichen Prüfungen: Hash beider Orte bei Doppelbefund. (a) ⇒ nicht ausgeführt, Wiederholung nach Ruhefrist; (b) ⇒ `executed` + Post-Verifikation; (c) ⇒ `needs_review`.
- **Prüfung:** Doppelbefund, abweichender Hash, Cache/Dateisystem inkonsistent (Nextcloud-Scan-Bedarf).

### S7 – Crash nach dem Move, vor dem Journal-Update (P8–P10)
- **Persistiert:** `executing`, alte Referenz aktuell.
- **Storage:** Datei am Ziel.
- **Recovery:** Beobachtung findet Datei am Soll ⇒ `executed` (Referenz nachtragen, `recognition=operation`), Post-Verifikation. Das Event-Ergebnis (falls eingereiht) wird als erwartet abgeglichen. Reservierung bleibt `reserved` bis `done`.
- **Prüfung:** nur bei Hash-Abweichung (S22).

### S8 – Crash während der Hash-Nachprüfung (P12)
- **Persistiert:** `verifying`, Referenz neu, Fortschritt.
- **Storage:** Datei am Ziel.
- **Recovery:** R4: Hash fortsetzen oder neu; **kein** erneuter Move. `done` erst nach vollständigem Vergleich. Zwischen Absturz und Abschluss ist die Instanz `unverified`, Folgeoperationen auf dem Inhalt (insbesondere Duplikat-Papierkorb) gesperrt.
- **Prüfung:** Hash weicht ab (S22) oder Datei zwischenzeitlich geändert (Drift).

### S9 – Lease-Ablauf während eines langen Moves
- **Ausgang:** W1 hat T9 committet und ruft `Node::move()` (langsamer Storage). Lease läuft ab; W2 übernimmt die Benutzer-Lease.
- **Persistiert:** `executing` von W1 (Fence 7); W2 (Fence 8) sieht die Operation noch nicht ruhend.
- **Storage:** Move in Arbeit oder vollendet.
- **Recovery:** W2 darf nicht parallel ausführen: Übernahme erst nach Lease-Ablauf, `claim_deadline` und `quiet_until`. W1s TX-E scheitert an `exec_fence`. Dann beobachtet W2 (T11 oder T12/T13). Mit verifizierten `move_no_overwrite`/`move_source_by_path` kann ein später doch noch laufender zweiter Aufruf nichts überschreiben (scheitert). **Wird die Ruhefrist kürzer gewählt als ein Move dauern kann**, bleibt RR-1.
- **Prüfung:** Doppelbefund oder Zwischenzustand nach der Ruhefrist.

### S10 – Zwei Worker beanspruchen dieselbe Datei
- **Ausgang:** Plan-Item A (`import`) und Reassign-Item B (`reassign`) verweisen auf dieselbe Instanz.
- **Race:** zwei TX-A gleichzeitig oder zwei Claims (T6).
- **Persistiert:** Unique `active_key` lässt nur eine Operation zu (T1 des Verlierers scheitert, einschließlich Reservierung). Beim Claim gewinnt genau ein CAS `preverified → claimed`.
- **Storage:** durch die Verliererin unverändert.
- **Recovery:** Verlierer liest neu; Item wartet; nach Abschluss der ersten Operation Revalidierung (Instanz hat anderen Zustand, Typregeln 6.2 neu geprüft). Außerdem: zwei Worker desselben Benutzers dürfen ohnehin nicht mutieren (`user:<id>:mut`).
- **Prüfung:** nein.

### S11 – Datei wird während der Vorbereitung verändert
- **Ausgang:** Pre-Hash läuft; Benutzer lädt eine neue Version derselben Datei hoch.
- **Race:** Signatur nach dem Hash ≠ Signatur davor.
- **Persistiert:** Operation `claimed`/`intended`.
- **Storage:** Datei neu.
- **Recovery:** Pre-Hash verworfen, Operation `aborted_precondition`; Instanz `superseded` oder M2 neu (Inhaltsidentität neu); Reservierung `burned(aborted)`; Gruppe prüfen (G5).
- **Prüfung:** nein (erwartete Benutzeraktion). Änderung nach Move: Drift (Matrix 4.3).

### S12 – Ziel existiert plötzlich
- **Ausgang:** Ziel wird belegt (a) vor P7, (b) zwischen P7 und P9.
- **Persistiert:** (a) `claimed`; (b) `executing`.
- **Recovery (a):** P7 erkennt ⇒ T8 `aborted_precondition`, Reservierung `burned(foreign_collision)`, Neuplanung.
- **Recovery (b), fail-closed:** Mit verifiziertem `move_no_overwrite` liefert der Aufruf `target_exists`; die Beobachtung zeigt Quelle am Vorzustand, Fremddatei unverändert ⇒ T12/Abbruch, **nichts überschrieben**. Ohne verifiziertes `move_no_overwrite` wird gar nicht verschoben (Modus „Nur Indexierung“); der Fall kann nicht eintreten. Es gibt **keine** Annahme über Plattformverhalten.
- **Prüfung:** Ergebnis `error(unknown)` ohne klare Beobachtung ⇒ `needs_review`.

### S13 – Nextcloud-Event trifft während der eigenen Operation ein
- **Ausgang:** Operation `executing`; Move löst `NodeRenamed` aus (synchron oder verzögert); parallel läuft ein Beobachtungsjob für dieselbe Datei.
- **Race:** Listener während P9–P11; der Beobachtungsjob liest, während ein weiterer Event eintrifft.
- **Persistiert:** Event-Job mit `event_gen`; Operation `executing`.
- **Recovery:** Listener erhöht `event_gen` (oder legt den Job an). Der Beobachter gleicht den Knoten gegen die Operation ab (Ziel, `file_id`): erwarteter Zustand ⇒ kein Override, kein Folgejob, keine Reaktion. Schreibt er nichts, was der Operation zusteht (TX-E gehört dem Besitzer): wartet (`retry_wait`). Stieg `event_gen` während der Beobachtung (≠ `seen_gen`), schlägt der Abschluss des Jobs fehl, der Job bleibt `queued` und beobachtet erneut; dann entspricht der Zustand dem Soll der abgeschlossenen Operation ⇒ `done`. Kein Bedarf geht verloren, kein unnötiger zusätzlicher Job entsteht (§4.2).
- **Anomalie:** Ein Rename-Hint im Fenster P4–P11 setzt `anomaly=event_during_op` (nur Indiz, 5.2).
- **Prüfung:** nein (außer Anomalie ⇒ Warnung).

### S14 – Benutzer pausiert während eines Moves
- **Race:** `user_paused=true` zwischen T9 und T10.
- **Persistiert:** `executing`.
- **Recovery:** Die laufende Einzeloperation wird beendet (Aufruf läuft durch; Beobachtung P10 und TX-E sind Teil der „begonnenen Einheit“). Der **Post-Hash** ist Inhaltslesen und wird bei Pause **nicht** begonnen: Operation bleibt `executed`/`verifying`; keine Duplikat-Papierkorb-Aktion auf dem Inhalt. Rein lesende **Metadatenbeobachtung** für die Recovery ist während der Benutzerpause erlaubt (§3.5), Hash und Anspruch nicht. Nach Fortsetzen: T14/T17/T15.
- **Prüfung:** nein.

### S15 – Admin pausiert global (Wartungsmodus) während eines Imports
- **Ausgang:** Eltern 900 `executing`, Kind 901 `done`, 902 `claimed`, 903 `intended`.
- **Persistiert:** wie angegeben.
- **Recovery:** Das laufende Kind (902) schließt die **begonnene Einheit** sicher ab (T9 nicht mehr begonnen, wenn noch `claimed`: T7 `→ preverified`; war `executing`, endet der Aufruf; P10/TX-E gehören zur Einheit). Danach **keine** Storage-Lese- oder Schreibzugriffe durch die App, auch keine Beobachtung. Eltern wird `partial`. Events werden nur minimal ohne Storage-Zugriff erfasst; `gap_since` wird gesetzt. Nach Aufhebung: Kontrollscan für Scopes mit `gap_since`, danach Recovery (§3) und Fortsetzung unter den Bedingungen von §5.1.
- **Prüfung:** wenn nach der Pause Bedingungen (Gruppenversion, Overrides, Regeländerung) abweichen.

### S16 – Benutzer löscht eine Mediengruppen-Komponente
- **Ausgang:** Gruppenoperation läuft; Benutzer löscht die noch nicht verschobene XMP (903) im Eingang.
- **Race:** Löschung zwischen Intent und Claim von 903.
- **Persistiert:** 903 `intended`.
- **Storage:** XMP im Papierkorb.
- **Recovery:** Pre-Verifikation von 903 findet den Knoten nicht ⇒ `aborted_precondition`; `po_deletion_event(subject_role=group_component)`; **kein** Tombstone (nicht kanonisch), **keine** Löschung anderer Komponenten (`DEC-GRP-013`); Gruppenmitgliedschaft `removed`; Reservierung bleibt für 901/902; Eltern wird mit den übrigen Kindern abgeschlossen (da fehlende optionale Komponente nicht blockiert, `DEC-GRP-005`) **oder** bei Zweifel `needs_review`, wenn die gelöschte Komponente die Primärkomponente war.
- **Prüfung:** Primärkomponente gelöscht; Löschung nach `executing` (Beobachtung unklar).

### S17 – Regeländerung während eines freigegebenen Plans
- **Ausgang:** Plan P mit Items; Operationen `intended`/`preverified`; Gruppe 31 mit Eltern 900 `partial` (RAW `done`); Benutzer ändert Namensregeln.
- **Race:** Invalidierungsjob parallel zum Ausführer.
- **Persistiert:** neue Revision/Snapshot; Items mit `naming` ⇒ `invalidated_by_rule_change`; bereits beanspruchte Operationen (`claimed`…`verifying`) unverändert (INV-11).
- **Recovery:** Ausführer prüft **vor jedem Claim** (T6) die Gültigkeit: `intended`/`preverified` ⇒ T3 `aborted_precondition`. Beanspruchte werden zu Ende geführt. Gruppe halb ausgeführt: **keine** automatische Fortsetzung der Restkomponenten unter geänderter Regel; Prüfaufgabe mit Wahl *Mit ursprünglichem Plan beenden (neue Freigabe)* oder *Neu planen*; **kein Rollback** (§5.1).
- **Prüfung:** Gruppe halb ausgeführt und Regelwerk geändert.

### S18 – Temporär nicht erreichbarer Storage
- **Race:** Netzwerk-/Objektspeicherausfall vor, während oder nach P8.
- **Persistiert:** je nach Schritt `claimed`, `executing`, `verifying`.
- **Recovery:** Vor P8: Operation zurück `intended`, Backoff. Nach `executing`: **Beobachtung nicht möglich ⇒ Zustand bleibt `executing`, keine Wiederholung**, erneuter Beobachtungsversuch mit Backoff. Wiederholte Ausfälle: Hold „systematisch“ für den Scope (Circuit Breaker). Nach Rückkehr: Beobachtung, Matrix 3.3. In `verifying`: Hash fortsetzen.
- **Prüfung:** nach Rückkehr, falls der Befund unklar ist; kritischer Hold bei Integritätsabweichung.

### S19 – Papierkorboperation mit unklarem Ergebnis
- **Ausgang:** Vom Benutzer bestätigte Duplikat-Sammelaktion, Operation `trash` für Duplikatkopie `inst` 102. **Pre-Delete bestanden**: beide Exemplare (102 und kanonische 101) unmittelbar zuvor **vollständig neu gehasht**, gleich; Gruppenschutz, Tombstone, `trash_verified` geprüft (`job-processing.md` §6.4).
- **Persistiert:** `executing`.
- **Storage:** Kopie noch am Ort, im Papierkorb, oder endgültig gelöscht (Spike SP-J).
- **Recovery:** Beobachten. Knoten am Ort ⇒ nicht ausgeführt ⇒ T12, **voller Ablauf neu** inklusive **neuem Pre-Delete beider Exemplare**. Knoten fehlt, Papierkorbeintrag auffindbar ⇒ Abschluss `trashed_by_app` (wiederherstellbar). Knoten fehlt, Papierkorbstatus nicht belegbar ⇒ `needs_review`, nie als Erfolg gewertet und nie wiederholt. Die kanonische Instanz wird nach dem Vorgang auf Existenz und Signatur geprüft; Abweichung ⇒ kritischer Hold.
- **Prüfung:** immer bei unklarem Papierkorbstatus. Bei jeder Abweichung im Pre-Delete (Hash, Gruppe, Tombstone, Signatur) wird die Aktion für dieses Element verweigert (`trash_refused`).

### S20 – Benutzer wechselt währenddessen den Dateinamen
Wissensklassen nach `job-processing.md` §6.5:
- **(a) Umbenennung vor P7 (nachweislich beobachtet):** P7 sieht `pre_state` verändert ⇒ T8, Änderung als manueller Zustand übernommen (`name_lock`, Prüfaufgabe `name_modified`, `DEC-NAM-012`); nichts überschrieben.
- **(b) Umbenennung zwischen P7 und P9:** Mit verifiziertem `move_source_by_path` scheitert der Aufruf mit `source_changed` ⇒ `aborted_precondition`, der geänderte Name ist lesbar und wird übernommen. Ohne diese Eigenschaft ist der Vorwert **nicht rekonstruierbar**: der Move schreibt den Sollnamen. Erkennbar höchstens als Indiz (`anomaly=event_during_op`) ⇒ Prüfaufgabe `possible_change_overwritten` ohne Wertbehauptung. Undo bietet nur den **zuletzt belegten** Zustand (P7, mit Zeitstempel) an und sagt, dass ein später gesetzter Name unbekannt ist.
- **(c) Umbenennung nach T9:** P10/TX-E sieht Name ≠ Soll ⇒ `needs_review`, kein Überschreiben.
- **Persistiert / Storage / Recovery:** je nach Fall; **Prüfung:** (b) ohne Fähigkeit, (c).

### S21 – Teilweise verschobene Mediengruppe
- **Siehe §5.1** (RAW done, JPEG unverifiziert, XMP im Eingang). Ergebnis: Recovery klärt zuerst die unklare Komponente, setzt nur bei erfüllten Bedingungen fort, sonst `needs_review` ohne Rollback.

### S22 – Post-Hash weicht ab (Integritätsfehler)
- **Persistiert:** `verifying`; Pre-Check `match`; Post-Check `mismatch`; Signatur unverändert; Mismatch eines fortgesetzten Hashes wurde einmal **vollständig neu berechnet** und bestätigt (3.6).
- **Storage:** Datei am Ziel mit abweichendem Hash.
- **Recovery:** T16 ⇒ `needs_review`, Instanz `failed`, Hold „kritisch“, Prüfaufgabe kritisch. Keine Reparatur, kein Zurückverschieben, keine Papierkorb-Aktion auf Duplikaten dieses Inhalts. **Manueller Ausgang:** T18/T19/T20/T21 nur durch den Benutzer. **`needs_review → done` ist ausgeschlossen**; T19 verlangt einen neuen vollständigen Post-Hash; T20 (`closed_unverified`) lässt `integrity_state` auf `failed` und sperrt Folgeaktionen. Journal und Hashes bleiben als Beleg.
- **Prüfung:** immer.

### S23 – Crash zwischen TX-E und Beginn des Post-Hash
- **Persistiert:** `executed` (Referenz geschrieben).
- **Recovery:** R3: Verifikation starten (Zustand `verifying`), kein Move. Nichts anderes nötig.
- **Prüfung:** nein.

### S24 – Datenbankverbindung fällt in der Abschluss-TX (TX-G) aus
- **Race:** Verbindung bricht während Commit.
- **Persistiert:** entweder TX-G vollständig (alles) oder gar nicht (Operation bleibt `verifying`, Reservierung `reserved`). Eine teilweise TX gibt es nicht (Transaktionssemantik der Datenbank).
- **Unsicher:** Ob der Commit durchging, ist dem Worker evtl. unbekannt ⇒ nach Wiederverbindung **Zustand lesen** (kein Annehmen).
- **Recovery:** Zustand `verifying` ⇒ Post-Check vorhanden? ⇒ TX-G wiederholen (idempotent, bedingt auf `state=verifying`); `done` ⇒ nichts.
- **Prüfung:** nein.

### S25 – Hash-Kontext nicht fortsetzbar / sehr großes Video
- **Ausgang:** 20-GB-Video, Pre-Hash über mehrere Dispatcher-Läufe; Job-Lease zwischendurch verloren; persistierter Kontext fehlt, Prüfsumme falsch, PHP-Version gewechselt oder Dateisignatur geändert.
- **Recovery:** Stufe H2 verworfen ⇒ Neubeginn mit H1 (prozessinterne Schleife mit Heartbeat). Passt die Datei nicht in die Möglichkeiten der Umgebung (Zeitbudget, Gesamtlimit `hash_max_age`, kein Fortschritt über *N* Einheiten) ⇒ H3: Instanz `held`, Prüfaufgabe `hash_not_possible`; **kein Ersatzhash**, keine Chunk-Prüfsumme als SHA-256. Eine Operation für diese Datei entsteht nicht (kein Pre-Hash möglich). Fortschrittsloses Wiederholen ist durch `no_progress_count` begrenzt.
- **Beobachtung:** Einzelbitfehler im persistierten Kontext können einen falschen Digest ergeben (lokal gemessen); deshalb Prüfsumme und Neuberechnung bei Mismatch.
- **Prüfung:** bei H3 oder dauerhafter Nichterreichbarkeit.

### S26 – Operation `executed`, aber `file_id` fehlt oder ist neu
- **Persistiert:** `executing`.
- **Storage:** Datei am Ziel, **andere** `nc_file_id`.
- **Recovery:** Wiedererkennung über Operation (Soll-Pfad, Größe), Hash; gleich ⇒ `executed` mit `file_id_changed` (Hinweis auf Kopieren/Löschen im Storage ⇒ erhöhte Aufmerksamkeit bei Post-Hash); mehrdeutig ⇒ `needs_review`.
- **Prüfung:** mehrdeutig oder Hash abweichend.

## 7. Zusammenfassung der Ergebnisse

| Frage | Ergebnis |
|---|---|
| Wird in einem Szenario automatisch zurückgerollt? | Nein. |
| Wird eine ungeklärte Mutation blind wiederholt? | Nein; Wiederholung erst nach Ruhefrist, positivem Befund und neuem Anspruch. |
| Wird in einem Szenario überschrieben oder gelöscht? | Nein durch die App; Rest-Risiko S12/S9 bei plattformseitigem Überschreiben (Spike SP-A). |
| Wo ist manuelle Prüfung zwingend? | Doppelbefund, dritte Stelle, Hash-Abweichung, unklarer Papierkorbstatus, mehrdeutige `file_id`-Wiedererkennung, Gruppenfortsetzung bei geänderten Regeln, Primärkomponente gelöscht. |
| Welche Szenarien hängen an Plattformverhalten? | S5, S6, S9, S12, S19, S26 (SP-A, SP-B, SP-C, SP-E). |

## 8. Verbleibende Risiken (geordnet nach möglichem Datenverlust)

| Rang | Nr. | Risiko | Möglicher Verlust | Begrenzung |
|---|---|---|---|---|
| 1 | RR-11 (neu) | Plattform überschreibt ein vorhandenes Ziel beim Move, ohne dass die Spikes das erkannt haben (Race-Harness unzureichend) | **Inhalt einer fremden Datei** | Fail-closed: ohne verifiziertes `move_no_overwrite` keine Automatik; Allowlist; große Wiederholungszahl (OQ-069) |
| 2 | RR-9 | Papierkorb-Verhalten von `Node::delete()` unverifiziert; endgültiges Löschen eines Duplikats | Duplikat (Inhalt bleibt in der kanonischen Datei) | `trash_verified` Pflicht, Neuhash beider Exemplare unmittelbar davor, Prüffall bei Abweichung |
| 3 | RR-1 | Verspäteter `Node::move()`-Aufruf nach Lease-Verlust | Ortsänderung (kein Inhaltsverlust bei verifiziertem `move_no_overwrite`) | gleicher Intent/Ziel, Ruhefrist, Gateway-Fehlerergebnisse, Post-Hash |
| 4 | RR-3 | Benutzerumbenennung im Restfenster zwischen letzter Prüfung und Aufruf wird überschrieben | **Benutzername/Metadatum**, nicht Inhalt | `move_source_by_path`, Anomalie-Indiz, Warnung ohne Wertbehauptung |
| 5 | RR-4 | Unbekannte Teilzustände interner Storage-Operationen (Kopie ohne Löschung) | unbekannt; Doppelbefund | Doppelbefund-Regel, Hash, `needs_review` |
| 6 | RR-2 | Veränderung der Datei zwischen Pre-Hash und Move | veränderter Inhalt wird nicht erkannt vor dem Move | Post-Hash erkennt; Signaturprüfung P7 |
| 7 | RR-5 | Unwirksame Dateisperren | Gleichzeitigkeit | Korrektheit hängt nicht davon ab |
| 8 | RR-8 | Hash-Fortsetzung beschädigt oder nicht portabel | falscher Digest ⇒ Fehlalarm oder (bei Erstidentität) falsche Identität | Umschlag mit Prüfsumme, Neuberechnung bei Mismatch, Pre-Hash-Gegenprobe |
| 9 | RR-6 | Ruhefrist kürzer als ein langer Move | Gleichzeitigkeit, siehe RR-1 | Konfiguration, Messung |
| 10 | RR-7 | Unbeobachtete Wiedereintritte | Herkunftshistorie lückenhaft | Beobachtungsgrenze |
| 11 | RR-10 | Doppelter Lese-I/O | keine (Kosten) | Budget, Fairness |

Alle sind in `open-questions.md` (OQ-055, OQ-059, OQ-069) geführt. RR-12 (neu): Persistierte Hash-Zustände unterschiedlicher PHP-Versionen sind nicht portierbar; Neubeginn statt Migration.

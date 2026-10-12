# AGENTS.md

Arbeitsanweisungen für Coding-Agenten (und Menschen) in diesem Repository. Sprache der Projektdokumente: Deutsch, technische Bezeichner Englisch.

## Projekt

**Photo Organizer** (Arbeitstitel): native Nextcloud-App zur sicheren Organisation und Deduplikation privater Foto- und Videobestände. Überblick: [`README.md`](README.md), Details: [`documentation/README.md`](documentation/README.md).

**Stand:** Planungsphase. Es gibt Dokumentation (`documentation/`) und eine Wegwerf-Testumgebung für Plattform-Spikes (`tests/integration/nextcloud-spikes/`), aber **keinen Anwendungscode**. Mit der Implementierung der App wird nur auf ausdrücklichen Auftrag begonnen.

## Definition of Done

Eine Aufgabe ist erst erledigt, wenn **alle** Punkte erfüllt sind:

1. Das angeforderte Feature ist vollständig umgesetzt.
2. **Unit-Tests** sind geschrieben.
3. **E2E-Tests** sind geschrieben.
4. Die Tests laufen und sind grün. Das Ergebnis wird berichtet, wie es ist; fehlschlagende oder übersprungene Tests werden nie als bestanden dargestellt.
5. Die **Dokumentation** ist aktualisiert (betroffene Dokumente unter `documentation/`, bei Bedarf README).
6. **Es wurden keine anderen Features geändert** als angefordert. Auffälligkeiten außerhalb des Auftrags werden gemeldet, nicht stillschweigend behoben.

## Lizenz und Quellanforderungen

Das Projekt steht unter **AGPL-3.0-or-later** (siehe [`LICENSE`](LICENSE)). Für alle künftigen Quellen gilt:

- Jede neue Quelldatei beginnt mit `SPDX-License-Identifier: AGPL-3.0-or-later` und einem Copyright-Vermerk (`SPDX-FileCopyrightText: <Jahr> Oliver Meyer`), im jeweils üblichen Kommentarformat der Sprache.
- Die `info.xml` der App enthält `<licence>AGPL-3.0-or-later</licence>`.
- Abhängigkeiten und übernommener Fremdcode müssen AGPL-3.0-kompatibel lizenziert sein. Ihre Lizenz- und Urheberhinweise bleiben erhalten; Drittcode wird dokumentiert (Name, Version, Lizenz).
- Keine Lizenzbedingungen oder Dateiheader, die das Einsatzgebiet einschränken (die AGPL erlaubt das nicht). Der Wunsch nach nicht-militärischer Nutzung steht nur als unverbindliche Bitte im README.
- Bestehender Wegwerf-Code der Spikes (`po_spike_probe`) wird nicht nachträglich umgestellt, es sei denn, er geht in die App über.

## Arbeitsweise

- **Verbindliches nicht verändern.** Beschlüsse ändert nur der Product Owner. Neue Erkenntnisse, die Beschlossenes berühren, kommen als Entscheidungsvorlage ([Z], `open-questions.md`, `CONFLICT-…`), nicht als stille Änderung.
- **Dokument-Konventionen** (siehe `documentation/README.md`): Statuskennzeichen **[V]** verbindlich, **[T]** technisch vorgeschlagen, **[O]** offen, **[Z]** zu bestätigender Vorschlag. IDs (`DEC-…`, `REQ-…`, `OQ-…`, `CONFLICT-…`, `RISK-…`, `PROP-…`) sind stabil und werden nie umnummeriert.
- **Evidenz trennen** (`platform-spikes.md`): **E** experimentell, **Q** aus Quelltext, **V** aus API-Vertrag, **U** unverifiziert. PASS/FAIL/INCONCLUSIVE werden nie vermischt; FAIL und INCONCLUSIVE gelten nie als PASS.
- **Fail-closed:** Eine Nextcloud-Fähigkeit gilt nur als vorhanden, wenn sie für das konkrete Profil (Version, Storage-Klasse, Locking, DB, PHP) belegt ist. Nichts wird auf andere Versionen oder Storage-Klassen extrapoliert. Aktueller Verifikationsstand: `documentation/nextcloud-integration.md` und `documentation/platform-spikes.md`.
- **Zentrale Plattformerkenntnisse** (Details in `platform-spikes.md`): `Node::move()` überschreibt ein vorhandenes Ziel ohne Papierkorb; Fehlerantworten bedeuten nicht „nicht ausgeführt“; Datei-Sperren sind nicht blockierend.
- **Nur Nextcloud-APIs** für Dateizugriffe, kein direkter Zugriff auf das Datadir (DEC-NCI-001/002). Die Admin-Oberfläche und -API enthalten keine Dateinamen, Pfade, Hashes oder Vorschauen (DEC-USR-009).
- **Nach einem Abschlussbericht anhalten** und auf Review warten; keinen Folgeauftrag selbstständig beginnen.

## Testumgebung (Nextcloud-Spikes)

Verzeichnis: `tests/integration/nextcloud-spikes/` (Bedienung: dortiges `README.md`).

```bash
cd tests/integration/nextcloud-spikes
./ncspike preflight                 # Voraussetzungen prüfen, ändert nichts
./ncspike --nc 34|35 all            # up + bootstrap + test + report
./ncspike --nc 34|35 reset          # Projekt vollständig entfernen
```

Verbindliche Isolation:

- Keine Verbindung zu einem produktiven Nextcloud-Server, keine produktiven Daten, Datenbanken oder Zugangsdaten, keine Abhängigkeit von `/home/cloud/owncloud`.
- Keine Docker-Socket-Mounts, keine privilegierten Container, keine veröffentlichten Ports.
- Nur synthetische Testdateien, angelegt über Nextcloud-APIs, nicht direkt im Datadir.
- Docker nur über `ncspike` bzw. `docker compose -p po-spike-nc34|po-spike-nc35`. Kein globales Docker-Cleanup (`docker system prune`, `volume prune`).
- Images ausschließlich per Digest, keine beweglichen Tags (`versions/*.env`).
- Kein dauerhaft irreversibles Löschen im Test-Harness.
- Lässt sich die Umgebung nicht starten, wird der Blocker dokumentiert; es werden keine Hoständerungen vorgenommen.

## Git

- Commits und Pushes nur auf Anforderung. Commit-Nachrichten kurz und im Imperativ oder als Beschreibung des Ergebnisses; keine Rohlogs, Geheimnisse oder `.state/` einchecken (siehe `.gitignore`).
- Ergebnisdateien der Spikes (`results/**/results.json`, `REPORT.md`, `summary.json`) gehören ins Repository, Rohlogs (`*.gz`) nicht.

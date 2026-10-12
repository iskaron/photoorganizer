# Dokumentation – Photo Organizer (Arbeitstitel)

Native Nextcloud-App zur sicheren Organisation und Deduplikation privater Foto- und Videobestände.

**Sprache:** Deutsch; technische Bezeichner Englisch.
**Statuskennzeichnung in allen Dokumenten:** **[V]** verbindlich beschlossen · **[T]** technisch vorgeschlagen · **[O]** noch zu entscheiden · **[Z]** zu bestätigender Vorschlag.
**Evidenzkennzeichnung in `platform-spikes.md`:** **E** experimentell · **Q** aus Quelltext · **V** aus API-Vertrag · **U** unverifiziert.

## Planungsstatus (Stand 2026-10-09)

Runde 1–4b (Aufträge 1, 2, 2b, 3, 3b, 4, 4b): ausschließlich Planung und Dokumentation. Runde 5 (Auftrag 5): experimentelle Prüfung der Nextcloud-Plattformannahmen in einer isolierten Docker-Compose-Testumgebung (`../tests/integration/nextcloud-spikes/`). Es existiert **weiterhin kein** Anwendungscode der Photo-Organizer-App, kein Scaffold und keine Migration; der Probe-Code der Spikes ist Wegwerf-Testcode.

| Dokument | Zweck | Status |
|---|---|---|
| [requirements.md](requirements.md) | Anforderungen (funktional/nichtfunktional) | Ausführlich, Entwurf zur Abnahme |
| [decisions.md](decisions.md) | Decision Log mit stabilen IDs, Widersprüche | Ausführlich |
| [open-questions.md](open-questions.md) | Offene Fragen mit Priorität und Vorschlag | Erste Fassung |
| [architecture-overview.md](architecture-overview.md) | Technische Architektur | Entwurf [T] |
| [nextcloud-integration.md](nextcloud-integration.md) | Nextcloud-Schnittstellen, klassifiziert (1–4) | Runde 5: Verifikationsstand je Schnittstelle nach den Spikes (Nextcloud 34.0.4 und 35.0.0, nur lokaler Heim-Storage) |
| [platform-spikes.md](platform-spikes.md) | Ergebnisse der experimentellen Plattform-Spikes (Move/Zielkollisionen, File-IDs, Locking, Papierkorb, Events, HashContext), Korrekturbedarf der Architektur | Runde 5: neu; Befunde experimentell (E), aus Quelltext (Q), aus Vertrag (V) getrennt |
| [environment.md](environment.md) | Referenzumgebung (PO-bestätigt), Versionsbereich-Vorschlag, Docker-Testumgebung | Runde 5: Abschnitt 6 (isolierte Testumgebung, Digest-feste Images, Abweichungen zur Referenz) |
| [data-model.md](data-model.md) | Relationales Datenmodell (konzeptionell, ohne SQL) | Runde 3b: nach Review überarbeitet (Scan-Scopes, Herkunftsentscheidung, Fencing, Integrität, pHash, Schlüssel), Entwurf [T] |
| [data-model-examples.md](data-model-examples.md) | 20 Datenbeispiele mit Zustandsänderungen | Runde 3b: 1, 4, 10, 13, 14 überarbeitet; 15–20 neu |
| [job-processing.md](job-processing.md) | Zustandsautomaten M1–M13, Transaktionsgrenzen, Worker, Leases, Event-Verarbeitung | Runde 4b: konsolidiert (Operationsreihenfolge, Autorisierung, fail-closed, Löschsicherheit), Entwurf [T] |
| [crash-recovery.md](crash-recovery.md) | Recovery-Algorithmus, Mediengruppen-Fälle, 26 Fehlerszenarien, Restrisiken | Runde 4b: 26 Szenarien gegen die korrigierten Automaten geprüft, Entwurf [T] |
| [frontend.md](frontend.md) | Webfrontend | Gliederung |
| [performance.md](performance.md) | Performance | Gliederung |
| [testing.md](testing.md) | Teststrategie | Gliederung (Spike-Harness: siehe `../tests/integration/nextcloud-spikes/README.md`) |

## Konventionen

- IDs sind stabil: `DEC-…` (Entscheidungen), `REQ-…` (Anforderungen), `OQ-…` (offene Fragen), `CONFLICT-…`, `RISK-…`, `PROP-…`.
- Beschlossenes ändert nur der Product Owner. Neue Erkenntnisse ändern bestehende Dokumente gezielt; keine Parallelversionen.
- Keine Version oder API gilt als unterstützt, solange sie nicht in `nextcloud-integration.md` als verifiziert eingetragen ist.

## Nächste Schritte

Siehe `open-questions.md` (Priorität P0), insbesondere die Fragen aus Runde 5 (OQ-073 ff.) zum Umgang mit der Erkenntnis, dass `Node::move()` ein vorhandenes Ziel überschreibt (`platform-spikes.md` §3).

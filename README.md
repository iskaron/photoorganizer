# Photo Organizer

Native Nextcloud-App zur **sicheren Organisation und Deduplikation privater Foto- und Videobestände** (Arbeitstitel).

> [!IMPORTANT]
> **Status: Planungsphase.** Dieses Repository enthält bislang ausschließlich Dokumentation und eine Wegwerf-Testumgebung zur Prüfung von Nextcloud-Plattformannahmen. Es gibt **noch keinen Anwendungscode** der App, kein Scaffold und keine Migrationen. Nichts hier ist installierbar oder für den produktiven Einsatz gedacht.

## Idee

Die App soll Medien aus konfigurierten Eingangsordnern in eine Sammlung der Struktur `Jahr/Monat/Quelle` einsortieren und exakte Duplikate (SHA-256) sowie mögliche Duplikate erkennen. Leitlinien aus den Anforderungen:

- **Sicherheit vor Komfort:** Alle Dateioperationen werden vorab als Importplan (Dry-Run) angezeigt, freigegeben und in einem Journal protokolliert. Es gibt keine automatische Löschung.
- **Mediengruppen:** Live Photos, RAW+JPEG und XMP-Sidecars werden als Einheit behandelt.
- **Nur eigene Dateien:** Keine Verarbeitung freigegebener Dateien, Gruppenordner oder externer Mounts; kein direkter Datadir-Zugriff; kein Medienzugriff für Administratoren über die App.
- **Fail-closed:** Ist eine Plattformfähigkeit nicht belegt, wird die Operation nicht ausgeführt.

Die verbindlichen Anforderungen und Entscheidungen stehen in [`documentation/requirements.md`](documentation/requirements.md) und [`documentation/decisions.md`](documentation/decisions.md).

## Repository-Inhalt

| Pfad | Inhalt |
|---|---|
| [`documentation/`](documentation/README.md) | Anforderungen, Entscheidungen, Architektur, Datenmodell, Job-Verarbeitung, Crash-Recovery, offene Fragen |
| [`documentation/platform-spikes.md`](documentation/platform-spikes.md) | Ergebnisse der experimentellen Nextcloud-API-Spikes (Move/Zielkollisionen, File-IDs, Locking, Papierkorb, Events, HashContext) |
| [`tests/integration/nextcloud-spikes/`](tests/integration/nextcloud-spikes/README.md) | Isolierte Docker-Compose-Testumgebung, Probe-App und Auswertung |

Einstieg in die Dokumentation: [`documentation/README.md`](documentation/README.md). Sie ist auf Deutsch verfasst, technische Bezeichner sind englisch.

## Plattform-Spikes

Die Architekturannahmen wurden gegen **Nextcloud 34.0.4** und **35.0.0** geprüft (nur lokaler Heim-Storage, MariaDB, Redis-Locking). Zentrales Ergebnis: `Node::move()` überschreibt ein vorhandenes Ziel ohne Papierkorb und ohne Ausnahme; eine atomare Nichtüberschreibung über die öffentliche API ist nicht belegt. Details, Evidenzklassen und die daraus folgenden offenen Fragen stehen in [`documentation/platform-spikes.md`](documentation/platform-spikes.md).

### Testumgebung starten

Voraussetzungen: Docker mit Compose v2, mindestens 8 GiB freier Speicherplatz, Python 3 (nur für die Auswertung).

```bash
cd tests/integration/nextcloud-spikes

./ncspike preflight          # Voraussetzungen prüfen, ändert nichts
./ncspike --nc 34 all        # up + bootstrap + test + report
./ncspike --nc 34 reset      # Container, Volumes und lokalen Zustand entfernen
```

Die Umgebung ist vollständig isoliert: keine veröffentlichten Ports, internes Netzwerk, kein Docker-Socket, ausschließlich synthetische Testdaten und Docker-Ressourcen mit eindeutigem Präfix (`po-spike-nc34` / `po-spike-nc35`). Alle Optionen beschreibt die [README der Testumgebung](tests/integration/nextcloud-spikes/README.md).

## Mitarbeit

Das Projekt befindet sich in der Planung; Entscheidungen trifft der Product Owner. Offene Fragen sind mit Priorität in [`documentation/open-questions.md`](documentation/open-questions.md) erfasst.

## Lizenz

Noch nicht festgelegt.

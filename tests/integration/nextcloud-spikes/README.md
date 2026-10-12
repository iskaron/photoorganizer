# Nextcloud-API-Spikes (Photo Organizer, Auftrag 5)

Isolierte Docker-Compose-Testumgebung, um die Plattformannahmen der Photo-Organizer-Architektur
experimentell zu prüfen (Move/Zielkollisionen, File-IDs, Locking, Papierkorb, Events, HashContext).
**Hier liegt weder die Photo-Organizer-App noch Code, der in sie übernommen werden soll.** Die Probe-App
`po_spike_probe` ist Wegwerf-Testcode; die Ergebnisse stehen in `documentation/platform-spikes.md`.

## Isolation (verbindlich)

| Regel | Umsetzung |
|---|---|
| Keine Verbindung zum produktiven Server, keine produktiven Daten/Zugangsdaten | Eigene Datenbank, eigener Redis, eigene Volumes; Passwörter werden pro Projekt zufällig erzeugt (`.state/<projekt>/secrets.env`, nur lokal, Modus 600) |
| Keine Abhängigkeit von `/home/cloud/owncloud` | Nichts in diesem Verzeichnis referenziert Pfade außerhalb des Repositories |
| Keine Host-Mounts produktiver Verzeichnisse | Einziger Host-Mount: `probe/po_spike_probe` **schreibgeschützt** (Container `app`, `cron`) und beim HashContext-Lauf `probe/…/lib/Support` schreibgeschützt |
| Kein Docker-Socket, keine privilegierten Container | `privileged: false`, `no-new-privileges`, kein `/var/run/docker.sock` |
| Keine öffentlich erreichbaren Ports | Es werden **keine Ports veröffentlicht**; das Netzwerk ist `internal: true` (weder nach außen noch von außen erreichbar). Zugriff nur über `docker compose exec`. |
| Nur synthetische Testdateien | Alle Fixtures werden deterministisch im Testcode erzeugt (SHA-256-Zählermodus bzw. Xoshiro mit festem Seed) und über Nextcloud-APIs angelegt, nie direkt im Datadir |
| Eindeutiger Präfix, kein globales Cleanup | Compose-Projekte `po-spike-nc34` / `po-spike-nc35`; Label `po-spike=1`; `ncspike` verwendet ausschließlich `docker compose -p <projekt>`; es gibt kein `docker system prune`/`volume prune` |
| Getrennte Installationen je Version | Getrennte Projekte, Volumes und Netze; kein Upgrade zwischen den Versionen |

## Voraussetzungen

Docker (getestet mit 25.0.2) und Docker Compose v2 (getestet mit 2.24.5), ≥ 8 GiB freier Platz, Python 3 (nur für die Auswertung).
`./ncspike preflight` prüft das und verändert nichts. Fehlt etwas, bricht das Skript mit dem konkreten Blocker ab;
es nimmt keine Hoständerungen vor.

## Images (feste Digests, keine beweglichen Tags)

Siehe `versions/nc34.env`, `versions/nc35.env`, `versions/php-matrix.env`. Die Image-Digests sind dort als
Index-Digests festgehalten (`name@sha256:…`); die Referenz-Tags stehen als Kommentar daneben.
Die tatsächlichen Nextcloud-/PHP-/Datenbank-Versionen jedes Testlaufs schreibt `./ncspike test` nach
`results/nc<ver>/runs/<Lauf>/environment.json`.

## Bedienung

```bash
cd tests/integration/nextcloud-spikes

./ncspike preflight                    # Voraussetzungen prüfen (ändert nichts)
./ncspike --nc 34 up                   # Stack starten (db, redis, app); erste Installation 1–3 min
./ncspike --nc 34 bootstrap            # konfigurieren, Benutzer alice/bob, Probe-App, App-Passwörter, Cron-Service
./ncspike --nc 34 test                 # alle Spikes (A–F); Optionen: --group A,B  --case A03  --repeat 150
./ncspike hashctx                      # HashContext-Matrix über PHP 8.2/8.3.14/8.3/8.4/8.5 (+ laufende NC-Container)
./ncspike report                       # results/REPORT.md und results/summary.json erzeugen
./ncspike --nc 34 reset-data           # nur die Testdaten zurücksetzen (Ordner /spike, Papierkorb, Redis-Sperren, Eventlog)
./ncspike --nc 34 down                 # Container + Netz des Projekts entfernen, Volumes bleiben
./ncspike --nc 34 reset                # wie down, plus Volumes und lokaler Zustand dieses Projekts

./ncspike --nc 35 up && ./ncspike --nc 35 bootstrap && ./ncspike --nc 35 test
./ncspike --nc both test               # nacheinander für beide Versionen
./ncspike --nc 34 all                  # up + bootstrap + test + report
./ncspike --nc 34 occ po-spike:env     # beliebige occ-Befehle als www-data
./ncspike --nc 34 shell                # Shell im app-Container
```

`reset` und `down` verändern ausschließlich Ressourcen des gewählten Compose-Projekts
(`po-spike-nc34` bzw. `po-spike-nc35`) und das lokale Verzeichnis `.state/<projekt>/`. `results/` bleibt erhalten.
Ein vollständiger Neuaufbau: `./ncspike --nc 34 reset && ./ncspike --nc 34 up && ./ncspike --nc 34 bootstrap`.

## Läufe, Zusammenführung und Ergebnisse

`./ncspike test` legt je Aufruf `results/nc<ver>/runs/<UTC-Zeitstempel>/` an (`results.json`, `environment.json`, `stderr.log`,
`events.jsonl.gz`, `nextcloud-log-tail.jsonl.gz`, `app-container.log.gz`; Eventlog je Lauf zurückgesetzt, Logs gekürzt). `./ncspike report` führt **alle** Läufe eines Projekts zusammen;
bei mehrfach vorhandenen Fällen gilt der **neueste** Lauf. So lassen sich einzelne Fälle gezielt wiederholen (`--case A21`), ohne den Rest zu verlieren.
`results/_aborted/` enthält zwei vorzeitig abgebrochene Läufe (die Harness wurde währenddessen erweitert); sie werden **nicht** ausgewertet.
Der erste vollständige Lauf (`20261009T143510Z`) enthielt Konkurrenzfälle (A20–A29) mit zu kurzem Timeout der Kindprozesse (nur ca. 60–80 % der Iterationen ausgewertet);
diese Fälle sowie A16, A30 und D03 wurden im Lauf `20261009T153748Z` mit korrigierter Harness wiederholt und überschreiben die alten Ergebnisse in der Auswertung.
Die Fälle E03 und A10 wurden nach kleinen Harness-Korrekturen einzeln wiederholt; B/D/E wurden für NC 35 nach einem vollständigen `reset` und Neuaufbau erneut ausgeführt (gleiche Ergebnisse).
`results/nc<ver>/method-fingerprints.json` (Token-Hash der analysierten Nextcloud-Methoden) und
`results/hashctx/` (PHP-Versionsmatrix) ergänzen die Läufe.

## Struktur

| Pfad | Inhalt |
|---|---|
| `ncspike` | Bedien-CLI (bash) |
| `compose.yaml` | Stack (db = MariaDB, redis, app = Nextcloud+Apache, cron) |
| `versions/*.env` | Image-Digests und Projektnamen |
| `probe/po_spike_probe/` | Wegwerf-Probe-App: `occ po-spike:*`-Befehle, Testfälle `lib/Cases/Group{A…F}.php`, Support (Fixtures, Snapshot, Barrieren, WebDAV-Client, Event-Recorder, Instrumentierung) |
| `harness/hashctx_matrix.py` | HashContext-Portabilitätsmatrix über PHP-Images |
| `harness/report.py` | Auswertung (JSON → Markdown/summary) |
| `results/` | `nc34/`, `nc35/` (Läufe), `hashctx/`, `REPORT.md`, `summary.json` |

## Testfälle (Kurzüberblick)

| Gruppe | Fälle | Gegenstand |
|---|---|---|
| A | A01–A16, A20–A30 | Move, Zielkollisionen (Datei/Ordner/Unicode), veraltete Knoten, Ziel entsteht nach Prüfung, Sperren während des Moves, WebDAV `Overwrite: F`, Prototyp „geschützter Move“, Listener-Fehler, statistische Konkurrenztests (Ziel und Quelle) |
| B | B01–B07 | File-IDs bei Rename/Move/Copy/Trash/Restore/Inhaltsänderung/Storage-Wechsel, Storage-Referenzen |
| C | C01–C07 | `Node::lock`, Verträglichkeit, Wirkung auf WebDAV, Ordner-Sperre, nicht existierender Zielpfad, Wiedereintritt, Mutex-Rennen, Prozessabbruch (TTL) |
| D | D01–D06 | Papierkorb: Identität, Erreichbarkeit, Nachweisverfahren, Namenskollision, Restore bei belegtem Ziel, Fähigkeitserkennung |
| E | E01–E05 | Ereignisse bei Node-API, WebDAV, Chunked Upload v2, Papierkorb/Restore, Identitätsmerkmale |
| F | F01–F07, F10 | HashContext: Fortsetzen, Prozessneustart, Bitfehler, falscher Offset, Dateiänderung, Klassen-Injection, Durchsatz, Versionsmatrix |

Jeder Fall nennt die geprüfte **Annahme** (`hypothesis`). **PASS** = Annahme bestätigt, **FAIL** = widerlegt,
**INCONCLUSIVE** = nicht aussagekräftig (Harness-Fehler, Instrumentierung nicht gegriffen, zu schwache Stichprobe).
FAIL und INCONCLUSIVE werden nie als PASS gewertet. Race-Tests nennen die Zahl der Versuche; ein PASS ist
dort nur eine endliche Stichprobe (`evidence: finite_sample`).

## Instrumentierung (ausdrücklich keine öffentliche API)

Für deterministische Fensteruntersuchungen enthält die Probe-App Testinstrumente, die **interne** Nextcloud-Klassen
verwenden: ein Storage-Wrapper (`OC\Files\Filesystem::addStorageWrapper`, `Wrapper`) und ein Sabre-Hook
(`SabrePluginAddEvent`). Sie sind nur aktiv, wenn `/tmp/po-spike/hook.json` bzw. das Flag
`NoOverwriteGuard::$active` gesetzt ist. Ergebnisse, die darauf beruhen, sind in der Dokumentation als
„instrumentiert“ gekennzeichnet; der „geschützte Move“ (A14/A15/A27–A29) ist ein **Prototyp zur Bewertung einer
Option**, keine Empfehlung.

## Grenzen

* Geprüft wird nur der **lokale Heim-Storage** (`OC\Files\Storage\Local` im Heim-Wrapper mit Papierkorb-Wrapper) auf einem
  Docker-Named-Volume (ext4-Host), mit MariaDB 11.4, Redis-Dateisperren und PHP 8.5.x (Standard der offiziellen Images).
  Objektspeicher (S3), NFS/SMB-Backends, Verschlüsselung, Gruppenordner, externe Speicher und andere Datenbanken
  bleiben **ungeprüft**.
* Die Referenzumgebung des Product Owners verwendet PHP 8.3.14 und MySQL; die Images liefern PHP 8.5.x und MariaDB.
  PHP 8.3.14 ist nur in der HashContext-Matrix vertreten.
* Ein bestandener endlicher Race-Test beweist keine Atomizität; ein nicht reproduzierter Fehler ist nicht ausgeschlossen.

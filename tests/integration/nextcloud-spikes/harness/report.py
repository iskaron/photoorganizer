#!/usr/bin/env python3
"""Auswertung der Spike-Ergebnisse: results/ncXX/runs/<ts>/results.json (+ HashContext-Matrix) → REPORT.md / summary.json.

FAIL und INCONCLUSIVE werden nie als PASS dargestellt. PASS bei Race-Tests heißt nur: in der Stichprobe nicht widerlegt.
"""
import argparse, json, pathlib, sys

STATUS_ORDER = {"FAIL": 0, "INCONCLUSIVE": 1, "PASS": 2}
EVID = {
    "single_observation": "Einzelbeobachtung",
    "finite_sample": "endliche Stichprobe (keine Garantie)",
    "counterexample": "Gegenbeispiel (Annahme widerlegt)",
    "static_analysis": "Quelltextanalyse (nicht experimentell)",
}


def load(path):
    return json.loads(pathlib.Path(path).read_text())


def latest_run(res_dir, nc):
    """Führt alle Läufe eines Projekts zusammen; bei mehrfach vorhandenen Fällen gewinnt der neueste Lauf."""
    d = res_dir / f"nc{nc}" / "runs"
    if not d.exists():
        return None
    merged, stamps, env = {}, [], None
    for rd in sorted(p for p in d.iterdir() if (p / "results.json").exists()):
        try:
            data = load(rd / "results.json")
        except Exception:
            continue
        stamps.append(rd.name)
        envf = rd / "environment.json"
        if envf.exists():
            env = load(envf)
        for r in data.get("results", []):
            r["_run"] = rd.name
            merged[r["case_id"]] = r
    if not merged:
        return None
    return {"results": list(merged.values()), "_env": env or {}, "_ts": ", ".join(stamps)}



# ---------------------------------------------------------------------------------------------
# Ableitung der Fail-closed-Fähigkeitsmatrix (VORSCHLAG, nicht verbindlich; siehe platform-spikes.md §9.3)
# Regel: Eine Fähigkeit gilt nur dann als „Kandidat“, wenn ALLE ihr zugeordneten Testfälle PASS sind.
# Jeder FAIL widerlegt sie für dieses Tupel; INCONCLUSIVE oder fehlende Fälle ergeben „unbekannt“.
# Kein Ergebnis wird auf andere Tupel (Version, Storage-Kette, Datenbank, Locking) übertragen.
# Ein „Kandidat“ ist nie „verifiziert“: Race-Tests sind endliche Stichproben, interne APIs bleiben ein Risiko.
# ---------------------------------------------------------------------------------------------
CAPABILITIES = [
    ("move_no_overwrite[Node::move]", "Node::move lehnt ein vorhandenes Ziel ab", ["A02", "A03", "A04", "A05", "A09", "A20", "A21", "A22"]),
    ("move_no_overwrite[WebDAV MOVE Overwrite:F]", "WebDAV MOVE mit Overwrite: F ist auch unter Konkurrenz sicher", ["A11", "A13", "A23", "A24", "A25", "A26"]),
    ("move_no_overwrite[geschützter Move, interne API]", "Prototyp: Überschreibschutz im gesperrten Storage-Bereich (setzt wirksame Dateisperren voraus)", ["A14", "A27", "A28", "A29"]),
    ("  Gegenprobe: geschützter Move ohne Dateisperren", "muss ohne Sperren scheitern (A15 = FAIL ist hier ERWARTET und bestätigt die Abhängigkeit)", ["A15"]),
    ("move_source_by_path", "Move eines veralteten Knotens trifft nie eine andere Datei", ["A06", "A08", "A30"]),
    ("lock_target (öffentliche API)", "Sperre auf nicht existierenden Zielpfad, kompatibel mit eigenem Move", ["C04", "C05"]),
    ("trash_verified", "Löschen landet im Papierkorb; Inhalt/Identität über die File-ID belegbar", ["D01", "D02", "D05", "D06", "B05"]),
    ("trash_entry_unique_per_second", "zwei Löschungen desselben Pfades in einer Sekunde behalten beide Papierkorbeinträge", ["D03"]),
    ("trash_restore_no_overwrite", "Restore überschreibt eine inzwischen belegte Zieldatei nicht", ["D04"]),
    ("hash_resume (SHA-256)", "Fortsetzbarer SHA-256-Kontext mit Umschlag (inkl. PHP-Versionsmatrix F10)", ["F01", "F02", "F03", "F04", "F06", "F10"]),
    ("signature_change_detection", "Signatur aus Größe/MTime/ETag erkennt jede Inhaltsänderung", ["B06", "F05"]),
]


def derive_capabilities(run, extra=None):
    by = {r["case_id"]: r for r in run["results"]}
    by.update(extra or {})
    rows = []
    for name, desc, ids in CAPABILITIES:
        states = [(i, by.get(i, {}).get("result", "FEHLT")) for i in ids]
        if any(st == "FAIL" for _, st in states):
            verdict = "false (widerlegt)"
        elif all(st == "PASS" for _, st in states):
            verdict = "Kandidat (alle Fälle PASS)"
        else:
            verdict = "unbekannt"
        rows.append((name, desc, verdict, states))
    return rows



RACE_CASES = [
    ("A20", "Node::move", "Folder::newFile"), ("A21", "Node::move", "WebDAV PUT"), ("A22", "Node::move", "Node::move (anderer Quelle)"),
    ("A23", "WebDAV MOVE Overwrite:F", "Folder::newFile"), ("A24", "WebDAV MOVE Overwrite:F", "WebDAV PUT"),
    ("A25", "WebDAV MOVE Overwrite:F", "WebDAV PUT If-None-Match:*"), ("A26", "WebDAV MOVE Overwrite:F", "WebDAV MOVE Overwrite:F (anderer Quelle)"),
    ("A27", "geschützter Move (Prototyp)", "Folder::newFile"), ("A28", "geschützter Move (Prototyp)", "WebDAV PUT"), ("A29", "geschützter Move (Prototyp)", "geschützter Move (anderer Quelle)"),
]


def race_table(runs):
    """Markdown-Tabelle der Konkurrenztests A20–A29 je Version (für REPORT.md und platform-spikes.md)."""
    lines = ["| Fall | Mover | Zweiter Akteur | NC | ausgewertet (≤2 ms ausgerichtet) | **Move ersetzt erfolgreich geschriebenen Inhalt** | Zweiter ersetzt Ergebnis des Movers | Fehler gemeldet, Wirkung vorhanden (Mover / Zweiter) | beide gescheitert | Ergebnis |",
             "|---|---|---|---|---|---|---|---|---|---|"]
    for cid, mover, second in RACE_CASES:
        for nc in ("34", "35"):
            run = runs.get(nc)
            if not run:
                continue
            r = {x["case_id"]: x for x in run["results"]}.get(cid)
            if not r:
                continue
            t = r.get("tally") or {}
            ex = r.get("extra") or {}
            aligned = ex.get("aligned_iterations_within_2ms") if isinstance(ex, dict) else None
            lines.append("| {} | {} | {} | {} | {} ({}) | **{}** | {} | {} / {} | {} | {} |".format(
                cid, mover, second, nc, r["attempts"], aligned if aligned is not None else "?",
                t.get("VIOLATION_move_replaced_successful_write", 0), t.get("second_writer_replaced_move_result", 0),
                t.get("FAIL_REPORTED_BUT_MOVER_EFFECT_PRESENT", 0), t.get("FAIL_REPORTED_BUT_SECOND_EFFECT_PRESENT", 0),
                t.get("both_failed", 0), r["result"]))
    return "\n".join(lines)


def summary(results):
    c = {"PASS": 0, "FAIL": 0, "INCONCLUSIVE": 0}
    for r in results:
        c[r["result"]] += 1
    return c


def short(o, n=1400):
    s = json.dumps(o, ensure_ascii=False, indent=None, separators=(", ", ": "))
    return s if len(s) <= n else s[: n - 1] + "…"


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--summary", help="eine results.json kompakt ausgeben")
    ap.add_argument("--results-dir", default=str(pathlib.Path(__file__).resolve().parent.parent / "results"))
    ap.add_argument("--out", default=None)
    ap.add_argument("--race-table", action="store_true", help="nur die Konkurrenztabelle ausgeben")
    a = ap.parse_args()

    if a.summary:
        data = load(a.summary)
        res = data.get("results", [])
        c = summary(res)
        print(f"  Ergebnis: PASS={c['PASS']} FAIL={c['FAIL']} INCONCLUSIVE={c['INCONCLUSIVE']} (von {len(res)})")
        for r in sorted(res, key=lambda r: (STATUS_ORDER[r['result']], r['case_id'])):
            if r["result"] != "PASS":
                print(f"   {r['result']:<12} {r['case_id']:<4} {r['title'][:90]}")
        return

    rd = pathlib.Path(a.results_dir)
    runs = {nc: latest_run(rd, nc) for nc in ("34", "35")}
    if a.race_table:
        print(race_table(runs))
        return
    hm = None
    hf = rd / "hashctx" / "latest"
    if hf.exists():
        hm = load(rd / "hashctx" / hf.read_text().strip())["results"][0]

    cases = {}
    for nc, run in runs.items():
        if not run:
            continue
        for r in run["results"]:
            cases.setdefault(r["case_id"], {"meta": r, "by": {}})["by"][nc] = r
    out = []
    P = out.append
    P("# Nextcloud-API-Spikes – automatisch erzeugter Ergebnisbericht\n")
    P("*Erzeugt aus `results/ncXX/runs/<Lauf>/results.json`. Maßgeblich sind die JSON-Dateien; dieser Bericht ist eine Lesehilfe.*\n")
    P("**Lesehinweis:** PASS = die geprüfte Architekturannahme wurde bestätigt, FAIL = widerlegt, INCONCLUSIVE = Test nicht aussagekräftig (Harness-Fehler, zu schwache Stichprobe, Instrumentierung nicht gegriffen). "
      "FAIL und INCONCLUSIVE sind nie PASS. Ein PASS in einem Race-Test ist nur eine endliche Stichprobe und keine Garantie. Alle Ergebnisse gelten nur für die aufgeführte Umgebung (lokaler Heim-Storage, Docker-Named-Volume) und nicht für andere Storage-Klassen.\n")
    P("## 1. Umgebungen\n")
    P("| | Nextcloud 34 | Nextcloud 35 |\n|---|---|---|")
    def ev(nc, f):
        run = runs.get(nc)
        if not run:
            return "–"
        try:
            return f(run["_env"])
        except Exception:
            return "?"
    rows = [
        ("Nextcloud", lambda e: e["nextcloud"]["version"] + " (" + ".".join(map(str, e["nextcloud"]["build"])) + ")" if isinstance(e["nextcloud"]["build"], list) else e["nextcloud"]["version"] + f" ({e['nextcloud']['build']})"),
        ("PHP", lambda e: e["php"]["version"] + " " + e["php"]["sapi"]),
        ("Datenbank", lambda e: e["database"]["provider"] + " " + str(e["database"]["server_version"])),
        ("Storage-Klasse (Heim)", lambda e: e["storage_class_home"]),
        ("Storage-Wrapper-Kette", lambda e: " → ".join(e["storage_wrapper_chain_home"])),
        ("Mount-Klasse", lambda e: e["mount_point_class_home"]),
        ("Dateisystem Datadir", lambda e: str(e["datadir_filesystem"])),
        ("Locking", lambda e: e["locking"]["provider_class"] + " / " + str(e["locking"]["memcache_locking"]) + ", filelocking=" + str(e["locking"]["filelocking_enabled"])),
        ("Papierkorb / Versionen / Verschlüsselung", lambda e: f"{e['trashbin_enabled']} / {e['versions_enabled']} / {e['encryption_enabled']}"),
        ("Testlauf", None),
    ]
    for label, f in rows:
        if f is None:
            P(f"| {label} | {runs['34']['_ts'] if runs.get('34') else '–'} | {runs['35']['_ts'] if runs.get('35') else '–'} |")
        else:
            P(f"| {label} | {ev('34', f)} | {ev('35', f)} |")
    P("")
    P("## 2. Gesamtübersicht\n")
    P("| Version | PASS | FAIL | INCONCLUSIVE | Fälle |\n|---|---|---|---|---|")
    for nc, run in runs.items():
        if run:
            c = summary(run["results"])
            P(f"| NC {nc} | {c['PASS']} | {c['FAIL']} | {c['INCONCLUSIVE']} | {len(run['results'])} |")
    if hm:
        P(f"| HashContext-Matrix (F10) | {1 if hm['result']=='PASS' else 0} | {1 if hm['result']=='FAIL' else 0} | 0 | 1 |")
    P("")
    P("## 3. Testfälle im Überblick\n")
    P("| ID | Spike | Titel | NC 34 | NC 35 | Evidenz |\n|---|---|---|---|---|---|")
    for cid in sorted(cases, key=lambda x: (x[0], int(''.join(ch for ch in x[1:] if ch.isdigit()) or 0))):
        m = cases[cid]
        def st(nc):
            r = m["by"].get(nc)
            if not r:
                return "–"
            return r["result"] + (f" ({r['attempts']}×)" if r["attempts"] > 1 else "")
        evid = {EVID.get(r["evidence"], r["evidence"]) for r in m["by"].values()}
        P(f"| {cid} | {m['meta']['spike']} | {m['meta']['title']} | {st('34')} | {st('35')} | {'; '.join(sorted(evid))} |")
    if hm:
        P(f"| F10 | SP-H | {hm['title']} | {hm['result']} ({hm['attempts']}×) | (gleiche Matrix) | {EVID['finite_sample']} |")
    P("")
    P("## 4. Details je Testfall\n")
    for cid in sorted(cases, key=lambda x: (x[0], int(''.join(ch for ch in x[1:] if ch.isdigit()) or 0))):
        m = cases[cid]
        meta = m["meta"]
        P(f"### {cid} – {meta['title']}\n")
        P(f"- **Spike:** {meta['spike']} · **Dokumentbezug:** {meta.get('doc_ref','')}")
        P(f"- **Geprüfte Annahme:** {meta['hypothesis']}")
        for nc in ("34", "35"):
            r = m["by"].get(nc)
            if not r:
                continue
            P(f"- **NC {nc}: {r['result']}** · Evidenz: {EVID.get(r['evidence'], r['evidence'])} · Versuche: {r['attempts']} · Dauer {r['duration_ms']} ms")
            if r.get("tally"):
                P(f"  - Tally: `{short(r['tally'], 700)}`")
            fails = [x for x in r["assertions"] if not x["ok"]]
            for x in fails:
                P(f"  - ✗ {x['desc']} — `{short(x.get('detail'), 300)}`")
            if r.get("harness_error"):
                P(f"  - Harness-Fehler: `{short(r['harness_error'], 400)}`")
            obs = r.get("observed")
            if obs:
                P(f"  - Beobachtet: `{short(obs, 900)}`")
            for n in r.get("notes", [])[:3]:
                P(f"  - Hinweis: {n}")
        P("")
    if hm:
        P("### F10 – HashContext-Matrix\n")
        P(f"- PHP-Versionen: `{short(hm['environment']['php_versions'], 400)}`")
        P(f"- Ergebnis {hm['result']}: {hm['tally']['resume_ok']}/{hm['tally']['resume_total']} Fortsetzungen korrekt")
        P("")
        pm = hm["observed"]["matrix"]
        names = list(pm)
        P("| Erzeuger \\ Verbraucher | " + " | ".join(names) + " |\n|" + "---|" * (len(names) + 1))
        for p in names:
            cells = []
            for c in names:
                v = pm.get(p, {}).get(c, {})
                cells.append("ok" if v and all(s == "ok" for s in v.values()) else str(v))
            P(f"| {p} | " + " | ".join(cells) + " |")
        P("")
    P("## 5a. Konkurrenztests am Ziel (A20–A29) – Zählwerte\n")
    P(race_table(runs))
    P("")
    P("## 5. Abgeleitete Fähigkeiten (Vorschlag, nicht verbindlich)\n")
    P("Abgeleitet nach festen Regeln aus den Testergebnissen **je Nextcloud-Version** für das getestete Tupel (lokaler Heim-Storage mit der oben genannten Wrapperkette, MariaDB, Redis-Locking). "
      "„Kandidat“ heißt nur: kein getesteter Fall widerlegt die Fähigkeit; es ist **keine** Freigabe und gilt für kein anderes Tupel. Die Zeile „Gegenprobe“ muss FAIL zeigen (bestätigt die Abhängigkeit von den Dateisperren).\n")
    P("| Fähigkeit | Bedeutung | NC 34 | NC 35 | Beteiligte Fälle (NC 34 / NC 35) |\n|---|---|---|---|---|")
    caps = {nc: derive_capabilities(run, {"F10": hm} if hm else None) for nc, run in runs.items() if run}
    names = [c[0] for c in CAPABILITIES]
    for i, (name, desc, ids) in enumerate(CAPABILITIES):
        v34 = caps["34"][i][2] if "34" in caps else "–"
        v35 = caps["35"][i][2] if "35" in caps else "–"
        st34 = " ".join(f"{a}:{b}" for a, b in caps["34"][i][3]) if "34" in caps else "–"
        st35 = " ".join(f"{a}:{b}" for a, b in caps["35"][i][3]) if "35" in caps else "–"
        P(f"| `{name.strip()}` | {desc} | {v34} | {v35} | 34: {st34}<br>35: {st35} |")
    P("")
    text = "\n".join(out)
    outp = pathlib.Path(a.out) if a.out else rd / "REPORT.md"
    outp.write_text(text)
    summ = {"generated_from": {nc: (run["_ts"] if run else None) for nc, run in runs.items()},
            "counts": {nc: (summary(run["results"]) if run else None) for nc, run in runs.items()},
            "cases": {cid: {nc: {"result": r["result"], "evidence": r["evidence"], "attempts": r["attempts"], "tally": r["tally"]} for nc, r in m["by"].items()} for cid, m in cases.items()}}
    summ["capabilities_proposal"] = {nc: [{"capability": n, "verdict": v, "cases": dict(st)} for n, d, v, st in derive_capabilities(run, {"F10": hm} if hm else None)] for nc, run in runs.items() if run}
    if hm:
        summ["hashctx_matrix"] = {"result": hm["result"], "resume_ok": hm["tally"]}
    (rd / "summary.json").write_text(json.dumps(summ, indent=2, ensure_ascii=False))
    print(f"[report] {outp} und summary.json geschrieben")


if __name__ == "__main__":
    main()

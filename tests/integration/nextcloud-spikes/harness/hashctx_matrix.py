#!/usr/bin/env python3
"""Spike F – HashContext-Portabilitätsmatrix über mehrere PHP-Laufzeiten.

Erzeugt serialisierte SHA-256-Zustände in jeder Umgebung und setzt sie in jeder anderen fort.
Umgebungen: PHP-CLI-Images (Digest-fest, Alpine) und – falls laufend – die app-Container der
Nextcloud-Projekte. Es werden nur synthetische Daten verwendet; Container tragen das Label po-spike=1,
laufen ohne Netzwerk und mit schreibgeschütztem Labor-Mount.
Ein bestandener Test beweist keine Portabilitätsgarantie (endliche Stichprobe, nur diese Versionen).
"""
import argparse, json, os, subprocess, sys, tempfile, time, pathlib, datetime

HERE = pathlib.Path(__file__).resolve().parent.parent
LAB = HERE / "probe" / "po_spike_probe" / "lib" / "Support"


def sh(cmd, **kw):
    return subprocess.run(cmd, capture_output=True, text=True, **kw)


def load_images():
    txt = (HERE / "versions" / "php-matrix.env").read_text()
    for line in txt.splitlines():
        if line.startswith("PHP_IMAGES="):
            pairs = line.split("=", 1)[1].strip().strip('"').split()
            return dict(p.split("=", 1) for p in pairs)
    raise SystemExit("PHP_IMAGES fehlt in versions/php-matrix.env")


def compose(nc):
    proj = f"po-spike-nc{nc}"
    return ["docker", "compose", "-p", proj, "-f", str(HERE / "compose.yaml"),
            "--env-file", str(HERE / "versions" / f"nc{nc}.env"),
            "--env-file", str(HERE / ".state" / proj / "secrets.env")]


def nc_running(nc):
    r = sh(compose(nc) + ["ps", "--status", "running", "-q", "app"])
    return r.returncode == 0 and r.stdout.strip() != ""


class PhpImageEnv:
    def __init__(self, name, image, work):
        self.name, self.image, self.work, self.kind = name, image, work, "php-cli-alpine"
        self.version = None

    def run(self, args):
        cmd = ["docker", "run", "--rm", "--network", "none", "--label", "po-spike=1", "--name", f"po-spike-hashctx-{self.name}-{os.getpid()}-{int(time.time()*1000)%100000}",
               "-v", f"{LAB}:/lab:ro", "-v", f"{self.work}:/work", self.image, "php", "/lab/hashctx_cli.php"] + args
        return sh(cmd)

    def gen(self, algo, seed, size, offset, outname):
        return self.run(["gen", algo, seed, str(size), str(offset), f"/work/{outname}"])

    def resume(self, inname):
        return self.run(["resume", f"/work/{inname}"])


class NcEnv:
    def __init__(self, nc, work):
        self.nc, self.work, self.name, self.kind = nc, work, f"nc{nc}", "nextcloud-app-container"
        self.base = compose(nc)
        self.cli = "/var/www/html/custom_apps/po_spike_probe/lib/Support/hashctx_cli.php"

    def run(self, args):
        return sh(self.base + ["exec", "-T", "-u", "www-data", "app", "php", self.cli] + args)

    def gen(self, algo, seed, size, offset, outname):
        r = self.run(["gen", algo, seed, str(size), str(offset), f"/tmp/{outname}"])
        if r.returncode == 0:
            cp = sh(self.base + ["cp", f"app:/tmp/{outname}", str(self.work / outname)])
            if cp.returncode != 0:
                r.returncode, r.stderr = cp.returncode, cp.stderr
        return r

    def resume(self, inname):
        cp = sh(self.base + ["cp", str(self.work / inname), f"app:/tmp/{inname}"])
        if cp.returncode != 0:
            return cp
        return self.run(["resume", f"/tmp/{inname}"])


def parse(r):
    try:
        return json.loads(r.stdout.strip().splitlines()[-1])
    except Exception:
        return {"status": "error", "error": (r.stderr or r.stdout)[-300:]}


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--nc", default="", help="Nextcloud-Versionen, z. B. 34,35 (nur wenn laufend)")
    ap.add_argument("--out", default=str(HERE / "results" / "hashctx"))
    a = ap.parse_args()
    images = load_images()
    work = pathlib.Path(tempfile.mkdtemp(prefix="po-spike-hashctx-"))
    os.chmod(work, 0o777)
    envs = [PhpImageEnv(k, v, work) for k, v in images.items()]
    for nc in [x for x in a.nc.split(",") if x]:
        if nc_running(nc):
            envs.append(NcEnv(nc, work))
        else:
            print(f"[hashctx] Nextcloud {nc} läuft nicht – übersprungen", file=sys.stderr)
    scenarios = [("sha256", "F10-A", 1048583, 333333), ("sha256", "F10-B", 4096, 0), ("sha256", "F10-C", 70000, 64)]
    matrix, versions, errors = {}, {}, []
    for e in envs:
        r = e.run(["gen", "sha256", "probe", "1", "0", "/work/_probe.json"] if isinstance(e, PhpImageEnv) else ["gen", "sha256", "probe", "1", "0", "/tmp/_probe.json"])
        versions[e.name] = parse(r).get("php")
    for p in envs:
        for algo, seed, size, off in scenarios:
            fn = f"{p.name}-{seed}.json"
            g = p.gen(algo, seed, size, off, fn)
            gi = parse(g)
            if not gi.get("ok"):
                errors.append({"producer": p.name, "scenario": seed, "error": gi})
                continue
            for c in envs:
                res = parse(c.resume(fn))
                matrix.setdefault(p.name, {}).setdefault(c.name, {})[seed] = res.get("status")
                if res.get("status") != "ok":
                    errors.append({"producer": p.name, "consumer": c.name, "scenario": seed, "result": res})
    bad = [e for e in errors]
    total = sum(len(v2) for v in matrix.values() for v2 in v.values())
    ok = sum(1 for v in matrix.values() for v2 in v.values() for s in v2.values() if s == "ok")
    status = "PASS" if not bad and total > 0 else "FAIL"
    result = {
        "case_id": "F10", "spike": "SP-H", "title": "HashContext-Zustand zwischen PHP-Versionen und Laufzeitumgebungen übertragen",
        "hypothesis": "Ein unter einer PHP-Version serialisierter SHA-256-Kontext lässt sich unter allen getesteten anderen PHP-Versionen korrekt fortsetzen (nur diese Stichprobe; keine Portabilitätsgarantie).",
        "doc_ref": "job-processing.md §3.6, SP-H", "result": status, "evidence": "finite_sample", "attempts": total,
        "tally": {"resume_ok": ok, "resume_total": total, "problems": len(bad)},
        "environment": {"php_versions": versions, "kinds": {e.name: e.kind for e in envs}, "images": {k: v for k, v in images.items()}},
        "expected": {"all_pairs": "ok"}, "observed": {"matrix": matrix}, "assertions": [{"ok": not bad, "desc": "alle Paare (Erzeuger→Verbraucher) ok", "detail": bad[:5]}],
        "notes": ["Endliche Stichprobe: wenige Versionen, ein Algorithmus (SHA-256), drei Offsets.", "Kein Beleg für künftige PHP-Versionen; Umschlag mit PHP-Version, Neuberechnung bei Abweichung bleibt Pflicht."],
        "extra": {"errors": bad[:10]}, "run_id": datetime.datetime.now(datetime.timezone.utc).strftime("%Y%m%dT%H%M%SZ"),
    }
    out = pathlib.Path(a.out); out.mkdir(parents=True, exist_ok=True)
    f = out / f"matrix-{result['run_id']}.json"
    f.write_text(json.dumps({"results": [result]}, indent=2, ensure_ascii=False))
    (out / "latest").write_text(f.name)
    print(json.dumps({"file": str(f), "status": status, "php_versions": versions, "resume_ok": f"{ok}/{total}"}, ensure_ascii=False))
    for n in work.iterdir():
        n.unlink()
    work.rmdir()


if __name__ == "__main__":
    main()

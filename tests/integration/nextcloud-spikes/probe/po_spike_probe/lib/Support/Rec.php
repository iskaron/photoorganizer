<?php
declare(strict_types=1);

namespace OCA\PoSpikeProbe\Support;

/** Ergebnisaufzeichnung eines Testfalls. FAIL und INCONCLUSIVE werden nie zu PASS umgedeutet. */
final class Rec {
	public array $expected = [];
	public array $observed = [];
	public array $notes = [];
	public array $assertions = [];
	public array $api = [];
	public array $extra = [];
	public ?array $before = null;
	public ?array $after = null;
	public ?string $inconclusive = null;
	public string $evidence = 'single_observation'; // single_observation | finite_sample | counterexample | static_analysis
	public int $attempts = 1;
	public array $tally = [];
	public array $logs = [];

	public function expect(string $k, mixed $v): void { $this->expected[$k] = $v; }
	public function observe(string $k, mixed $v): void { $this->observed[$k] = Json::val($v); }
	public function note(string $s): void { $this->notes[] = $s; }
	public function log(mixed $x): void { $this->logs[] = Json::val($x); }

	public function tally(string $bucket, int $n = 1): void {
		$this->tally[$bucket] = ($this->tally[$bucket] ?? 0) + $n;
	}

	public function assert(bool $cond, string $desc, mixed $detail = null): bool {
		$this->assertions[] = ['ok' => $cond, 'desc' => $desc, 'detail' => Json::val($detail)];
		return $cond;
	}

	public function inconclusive(string $why): void {
		$this->inconclusive = ($this->inconclusive ? $this->inconclusive . ' | ' : '') . $why;
	}

	/** Ruft eine API auf und hält Rückgabe oder Ausnahme fest (Ausnahme wird nicht weitergeworfen). */
	public function call(string $label, callable $fn): array {
		$t0 = microtime(true);
		$r = ['label' => $label, 'ok' => true, 'ret' => null, 'exc' => null];
		try {
			$ret = $fn();
			$r['ret'] = Json::val($ret);
			$r['raw'] = $ret;
		} catch (\Throwable $e) {
			$r['ok'] = false;
			$r['exc'] = Json::exc($e);
		}
		$r['ms'] = round((microtime(true) - $t0) * 1000, 2);
		$this->api[] = array_diff_key($r, ['raw' => 1]);
		return $r;
	}

	public function status(): string {
		foreach ($this->assertions as $a) {
			if (!$a['ok']) {
				return 'FAIL';
			}
		}
		if ($this->inconclusive !== null || $this->assertions === []) {
			return 'INCONCLUSIVE';
		}
		return 'PASS';
	}
}

<?php
declare(strict_types=1);

namespace OCA\PoSpikeProbe\Support;

/** Startet Kindprozesse (`occ po-spike:child`), die zu einem gemeinsamen Zeitpunkt Operationen ausführen. */
final class Proc {
	public const TMP = '/tmp/po-spike';

	/** @var resource */
	private $proc;
	private array $pipes = [];
	public string $out;
	public string $go;
	public string $spec;
	public string $tag;
	public array $extra = [];

	public static function ensureTmp(): void {
		if (!is_dir(self::TMP)) {
			@mkdir(self::TMP, 0777, true);
			@chmod(self::TMP, 01777);
		}
	}

	/** @param list<array{off:float,op:array}> $tasks */
	public static function spawn(string $tag, array $tasks): self {
		self::ensureTmp();
		$p = new self();
		$p->tag = $tag . '-' . bin2hex(random_bytes(4));
		$p->spec = self::TMP . "/spec-{$p->tag}.json";
		$p->out = self::TMP . "/out-{$p->tag}.jsonl";
		$p->go = self::TMP . "/go-{$p->tag}";
		file_put_contents($p->spec, json_encode(['tasks' => $tasks, 'go' => $p->go, 'out' => $p->out]));
		@unlink($p->out);
		$cmd = [PHP_BINARY, \OC::$SERVERROOT . '/occ', 'po-spike:child', '--spec=' . $p->spec];
		$p->proc = proc_open($cmd, [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['pipe', 'w']], $p->pipes, \OC::$SERVERROOT);
		if (!is_resource($p->proc)) {
			throw new \RuntimeException('Kindprozess nicht startbar');
		}
		stream_set_blocking($p->pipes[2], false);
		return $p;
	}

	public function lines(): array {
		$o = [];
		if (is_file($this->out)) {
			foreach (file($this->out, FILE_IGNORE_NEW_LINES) as $l) {
				$j = json_decode($l, true);
				if (is_array($j)) {
					$o[] = $j;
				}
			}
		}
		return $o;
	}

	public function waitReady(float $timeout = 30.0): bool {
		$end = microtime(true) + $timeout;
		while (microtime(true) < $end) {
			foreach ($this->lines() as $l) {
				if (!empty($l['ready'])) {
					return true;
				}
			}
			if (!$this->running()) {
				return false;
			}
			usleep(5000);
		}
		return false;
	}

	public function go(float $t0): void {
		file_put_contents($this->go, (string)$t0);
	}

	public function running(): bool {
		$s = proc_get_status($this->proc);
		return (bool)$s['running'];
	}

	public function wait(float $timeout = 120.0): bool {
		$end = microtime(true) + $timeout;
		while (microtime(true) < $end) {
			if (!$this->running()) {
				return true;
			}
			usleep(5000);
		}
		return false;
	}

	public function kill(int $signal = 9): void {
		$s = proc_get_status($this->proc);
		if ($s['running']) {
			posix_kill($s['pid'], $signal);
		}
	}

	public function pid(): int { return (int)proc_get_status($this->proc)['pid']; }

	public function stderr(): string {
		$e = stream_get_contents($this->pipes[2]);
		return $e === false ? '' : $e;
	}

	public function close(): void {
		if (is_resource($this->proc)) {
			@proc_close($this->proc);
		}
		@unlink($this->spec);
		@unlink($this->go);
	}

	/** Ergebnisse je Task-Index */
	public function results(): array {
		$res = [];
		foreach ($this->lines() as $l) {
			if (isset($l['i'])) {
				$res[$l['i']] = $l;
			}
		}
		return $res;
	}

	/**
	 * Startet mehrere Kindprozesse mit synchronem Start.
	 * @param array<string, list<array{off:float,op:array}>> $plans Name → Tasks (off relativ zum gemeinsamen T0)
	 * @return array<string, array> Name → Ergebnisse je Task-Index
	 */
	public static function race(string $tag, array $plans, float $lead = 0.4, float $timeout = 180.0): array {
		$procs = [];
		foreach ($plans as $name => $tasks) {
			$procs[$name] = self::spawn($tag . '-' . $name, $tasks);
		}
		$ready = true;
		foreach ($procs as $p) {
			$ready = $p->waitReady(45) && $ready;
		}
		$out = ['ready' => $ready, 'children' => []];
		if ($ready) {
			$t0 = microtime(true) + $lead;
			foreach ($procs as $p) {
				$p->go($t0);
			}
			$out['t0'] = $t0;
		}
		foreach ($procs as $name => $p) {
			$finished = $ready ? $p->wait($timeout) : false;
			if (!$finished) {
				$p->kill();
			}
			$out['children'][$name] = ['finished' => $finished, 'results' => $p->results(), 'stderr' => mb_substr($p->stderr(), 0, 2000)];
			$p->close();
		}
		return $out;
	}
}

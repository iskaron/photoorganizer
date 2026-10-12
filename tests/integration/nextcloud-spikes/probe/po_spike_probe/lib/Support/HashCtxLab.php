<?php
// WEGWERF-TESTCODE (Nextcloud-API-Spikes, Auftrag 5). Reines PHP, keine Nextcloud-Abhängigkeit
// (wird auch in php:*-cli-Containern der Portabilitätsmatrix verwendet).
declare(strict_types=1);

final class HashCtxLab {
	/** Deterministische synthetische Daten, unabhängig von PHP-Version (SHA-256-Zählermodus). */
	public static function data(string $seed, int $size): string {
		$out = '';
		for ($i = 0; strlen($out) < $size; $i++) {
			$out .= hash('sha256', $seed . ':' . $i, true);
		}
		return substr($out, 0, $size);
	}

	/** Serialisierter Hash-Kontext nach dem Hashen von $data[0..offset). */
	public static function state(string $algo, string $data, int $offset): string {
		$ctx = hash_init($algo);
		hash_update($ctx, substr($data, 0, $offset));
		return serialize($ctx);
	}

	/** Setzt aus dem serialisierten Zustand bei $offset fort; wirft bei jedem Fehler/Warnung. */
	public static function resume(string $state, string $data, int $offset): string {
		$prev = set_error_handler(function (int $no, string $str) {
			throw new \ErrorException($str, 0, $no);
		});
		try {
			$ctx = unserialize($state, ['allowed_classes' => ['HashContext']]);
			if (!$ctx instanceof \HashContext) {
				throw new \RuntimeException('kein HashContext');
			}
			hash_update($ctx, substr($data, $offset));
			return hash_final($ctx);
		} finally {
			restore_error_handler();
		}
	}

	/** Alle Einzelbitfehler im serialisierten Zustand: Ablehnung / stiller Fehler / wirkungslos. */
	public static function flipAll(string $state, string $data, int $offset, string $expected): array {
		$tally = ['bits_total' => 0, 'rejected' => 0, 'accepted_correct_digest' => 0, 'accepted_wrong_digest' => 0];
		$examples = [];
		$n = strlen($state);
		for ($i = 0; $i < $n; $i++) {
			for ($b = 0; $b < 8; $b++) {
				$m = $state;
				$m[$i] = chr(ord($m[$i]) ^ (1 << $b));
				$tally['bits_total']++;
				try {
					$d = self::resume($m, $data, $offset);
					if ($d === $expected) {
						$tally['accepted_correct_digest']++;
					} else {
						$tally['accepted_wrong_digest']++;
						if (count($examples) < 3) {
							$examples[] = ['byte' => $i, 'bit' => $b];
						}
					}
				} catch (\Throwable $e) {
					$tally['rejected']++;
				}
			}
		}
		$tally['examples_silent_wrong'] = $examples;
		$tally['state_bytes'] = $n;
		return $tally;
	}

	/** Umschlag mit Prüfsumme (Vorschlag aus job-processing.md §3.6): erkennt jede Veränderung der Nutzlast. */
	public static function envelope(string $algo, int $offset, array $fileSig, string $state): string {
		return json_encode(['v' => 1, 'php' => PHP_VERSION, 'algo' => $algo, 'offset' => $offset, 'file' => $fileSig, 'state' => base64_encode($state), 'sum' => hash('sha256', $state)]);
	}

	public static function openEnvelope(string $env): array {
		$j = json_decode($env, true, 8, JSON_THROW_ON_ERROR);
		$state = base64_decode((string)$j['state'], true);
		if ($state === false || hash('sha256', $state) !== $j['sum']) {
			throw new \RuntimeException('Prüfsumme des Hash-Zustands stimmt nicht');
		}
		return [$j, $state];
	}
}

<?php
declare(strict_types=1);

namespace OCA\PoSpikeProbe\Support;

use OC\Files\Storage\Wrapper\Wrapper;

/**
 * INSTRUMENTIERUNG (interne API, nur Testzweck): hält einen Storage-rename() an, nachdem
 * View::rename die Dateisperren erworben hat, um das Fenster im Inneren von Node::move
 * deterministisch zu vermessen. Wirkt nur, wenn /tmp/po-spike/hook.json existiert; hält nur den ersten Treffer an.
 */
class RenameHookWrapper extends Wrapper {
	public function rename(string $source, string $target): bool {
		self::maybeFreeze($target);
		// Prototyp „geschützter Move“ (Spike A14–A15, A27–A29): Prüfung INNERHALB des von View::rename gesperrten Bereichs
		if (NoOverwriteGuard::$active && $this->file_exists($target)) {
			return false;
		}
		self::maybeFreeze($target, 'storage_after_check');
		return parent::rename($source, $target);
	}

	public static function maybeFreeze(string $target, string $phase = 'storage'): void {
		$f = '/tmp/po-spike/hook.json';
		if (!is_file($f)) {
			return;
		}
		$h = json_decode((string)@file_get_contents($f), true);
		if (!is_array($h) || ($h['phase'] ?? 'storage') !== $phase || !str_contains($target, (string)$h['match'])) {
			return;
		}
		$fh = @fopen($h['marker'], 'x'); // nur der erste Treffer hält an
		if (!$fh) {
			return;
		}
		fwrite($fh, (string)getmypid());
		fclose($fh);
		$end = microtime(true) + (float)($h['max_wait'] ?? 10);
		while (microtime(true) < $end && !is_file($h['release'])) {
			usleep(10000);
		}
	}
}

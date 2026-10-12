<?php
declare(strict_types=1);

namespace OCA\PoSpikeProbe\Support;

use OCP\Files\Node;

final class Json {
	/** Macht beliebige Werte JSON-tauglich (Nodes → Pfad/ID, Exceptions → Klasse/Meldung). */
	public static function val(mixed $v, int $depth = 0): mixed {
		if ($depth > 6) {
			return '…';
		}
		if ($v instanceof Node) {
			$o = ['_node' => get_class($v)];
			try { $o['path'] = $v->getPath(); } catch (\Throwable $e) { $o['path'] = 'ERR:' . get_class($e); }
			try { $o['id'] = $v->getId(); } catch (\Throwable $e) { $o['id'] = 'ERR:' . get_class($e); }
			return $o;
		}
		if ($v instanceof \Throwable) {
			return self::exc($v);
		}
		if (is_array($v)) {
			$o = [];
			foreach ($v as $k => $x) {
				$o[$k] = self::val($x, $depth + 1);
			}
			return $o;
		}
		if (is_object($v)) {
			return '(' . get_class($v) . ')';
		}
		if (is_string($v) && !mb_check_encoding($v, 'UTF-8')) {
			return 'base64:' . base64_encode(substr($v, 0, 64));
		}
		return $v;
	}

	public static function exc(\Throwable $e): array {
		return ['class' => get_class($e), 'message' => $e->getMessage(), 'code' => $e->getCode(), 'prev' => $e->getPrevious() ? get_class($e->getPrevious()) : null];
	}

	public static function enc(mixed $v): string {
		return json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_PRETTY_PRINT) ?: '{}';
	}
}

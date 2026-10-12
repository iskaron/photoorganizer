<?php
declare(strict_types=1);

namespace OCA\PoSpikeProbe\Support;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Files\Node;

/** Protokolliert jedes Event als JSON-Zeile (prozessübergreifend, mit flock). */
class EventRecorder implements IEventListener {
	private static int $seq = 0;
	public const FILE = '/tmp/po-spike/events.jsonl';

	public function handle(Event $event): void {
		try {
			$rec = [
				't' => microtime(true),
				'pid' => getmypid(),
				'sapi' => PHP_SAPI,
				'seq' => ++self::$seq,
				'class' => (new \ReflectionClass($event))->getName(),
				'method' => $_SERVER['REQUEST_METHOD'] ?? null,
				'uri' => isset($_SERVER['REQUEST_URI']) ? preg_replace('~\?.*~', '', (string)$_SERVER['REQUEST_URI']) : null,
				'req' => $_SERVER['HTTP_X_REQUEST_ID'] ?? ($_SERVER['UNIQUE_ID'] ?? null),
				'props' => self::props($event),
			];
			if (!is_dir('/tmp/po-spike')) {
				@mkdir('/tmp/po-spike', 0777, true);
				@chmod('/tmp/po-spike', 01777);
			}
			$fh = @fopen(self::FILE, 'ab');
			if ($fh) {
				flock($fh, LOCK_EX);
				fwrite($fh, json_encode($rec, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) . "\n");
				fflush($fh);
				flock($fh, LOCK_UN);
				fclose($fh);
				@chmod(self::FILE, 0666);
			}
		} catch (\Throwable $e) {
			// Testinstrument darf nie die Operation stören
		}
	}

	private static function node(mixed $n): mixed {
		if (!$n instanceof Node) {
			return is_scalar($n) || $n === null ? $n : (is_object($n) ? get_class($n) : gettype($n));
		}
		$o = ['path' => null, 'id' => null, 'type' => null];
		try { $o['path'] = $n->getPath(); } catch (\Throwable $e) { $o['path'] = 'ERR:' . get_class($e); }
		try { $o['id'] = $n->getId(); } catch (\Throwable $e) { $o['id'] = 'ERR:' . get_class($e); }
		try { $o['type'] = $n->getType() === Node::TYPE_FOLDER ? 'dir' : 'file'; } catch (\Throwable $e) { $o['type'] = null; }
		try { $o['size'] = $n->getSize(); } catch (\Throwable $e) { $o['size'] = 'ERR:' . get_class($e); }
		return $o;
	}

	private static function props(Event $e): array {
		$p = [];
		foreach (['getNode' => 'node', 'getSource' => 'source', 'getTarget' => 'target', 'getFolder' => 'folder'] as $m => $k) {
			if (method_exists($e, $m)) {
				try { $p[$k] = self::node($e->$m()); } catch (\Throwable $x) { $p[$k] = 'ERR:' . get_class($x); }
			}
		}
		foreach (['getPath', 'getStorageId', 'getFileId', 'getRestoredPath', 'getOriginalPath', 'isRestored', 'getUser', 'getUserId'] as $m) {
			if (method_exists($e, $m)) {
				try {
					$v = $e->$m();
					$p[lcfirst(substr($m, 3) ?: $m)] = is_scalar($v) || $v === null ? $v : (is_object($v) ? get_class($v) : gettype($v));
				} catch (\Throwable $x) { $p[$m] = 'ERR:' . get_class($x); }
			}
		}
		return $p;
	}

	public static function read(): array {
		$out = [];
		if (!is_file(self::FILE)) {
			return $out;
		}
		foreach (file(self::FILE, FILE_IGNORE_NEW_LINES) as $l) {
			$j = json_decode($l, true);
			if (is_array($j)) {
				$out[] = $j;
			}
		}
		return $out;
	}
}

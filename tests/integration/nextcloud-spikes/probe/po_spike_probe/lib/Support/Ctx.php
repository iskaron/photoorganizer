<?php
declare(strict_types=1);

namespace OCA\PoSpikeProbe\Support;

use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\Server;

/** Gemeinsame Hilfsmittel der Testfälle (nur Nextcloud-APIs für Testdaten; kein direkter Datadir-Zugriff). */
final class Ctx {
	public IRootFolder $root;
	public string $runId;
	public array $env = [];

	public function __construct(string $runId) {
		$this->root = Server::get(IRootFolder::class);
		$this->runId = $runId;
	}

	public function userFolder(string $uid): Folder {
		return $this->root->getUserFolder($uid);
	}

	/** Eigener Arbeitsordner je Testfall und Lauf: /spike/<run>/<case> (wird über die API angelegt) */
	public function workdir(string $case, string $uid = 'alice', string $sub = ''): Folder {
		$f = $this->userFolder($uid);
		$parts = array_filter(['spike', $this->runId, $case, $sub], fn($x) => $x !== '');
		foreach ($parts as $p) {
			$f = $f->nodeExists($p) ? $f->get($p) : $f->newFolder($p);
		}
		return $f;
	}

	/** Deterministische synthetische Bytes (Seed → immer gleicher Inhalt). */
	public static function bytes(string $seed, int $size): string {
		$eng = new \Random\Engine\Xoshiro256StarStar(hash('sha256', 'po-spike:' . $seed, true));
		return (new \Random\Randomizer($eng))->getBytes($size);
	}

	public static function shaStr(string $s): string {
		return hash('sha256', $s);
	}

	/** SHA-256 über den gesamten Inhalt eines Datei-Knotens (Stream, kein Voll-Laden). */
	public function sha(Node $n): ?string {
		if (!$n instanceof File) {
			return null;
		}
		$h = $n->fopen('r');
		if ($h === false) {
			return null;
		}
		$ctx = hash_init('sha256');
		hash_update_stream($ctx, $h);
		fclose($h);
		return hash_final($ctx);
	}

	/** Legt eine Fixture-Datei an. Bricht ab, wenn der Name schon existiert (newFile würde überschreiben!). */
	public function put(Folder $dir, string $name, string $content): File {
		if ($dir->nodeExists($name)) {
			throw new \LogicException("Fixture existiert bereits: $name");
		}
		$f = $dir->newFile($name, $content);
		return $f;
	}

	public function fresh(string $fullPath): ?Node {
		try {
			return $this->root->get($fullPath);
		} catch (NotFoundException) {
			return null;
		}
	}

	/** Momentaufnahme eines Teilbaums: Pfad relativ zur Basis → Eigenschaften (inkl. SHA-256, File-ID). */
	public function snapshot(Folder $base): array {
		$out = [];
		$prefix = rtrim($base->getPath(), '/') . '/';
		$walk = function (Folder $f) use (&$walk, &$out, $prefix) {
			foreach ($f->getDirectoryListing() as $n) {
				$rel = substr($n->getPath(), strlen($prefix));
				$e = ['id' => $n->getId(), 'type' => $n->getType() === Node::TYPE_FOLDER ? 'dir' : 'file', 'size' => $n->getSize(), 'mtime' => $n->getMTime(), 'etag' => $n->getEtag()];
				try { $e['internal_path'] = $n->getInternalPath(); } catch (\Throwable) {}
				if ($n instanceof File) {
					$e['sha256'] = $this->sha($n);
				}
				$out[$rel] = $e;
				if ($n instanceof Folder) {
					$walk($n);
				}
			}
		};
		$walk($base);
		ksort($out);
		return $out;
	}

	/** Reduzierte Sicht für Berichte: Anzahl, Pfade, File-IDs, Hashes. */
	public static function brief(array $snap): array {
		$files = array_filter($snap, fn($e) => $e['type'] === 'file');
		return [
			'file_count' => count($files),
			'dir_count' => count($snap) - count($files),
			'entries' => array_map(fn($e) => ['id' => $e['id'], 'sha256' => $e['sha256'] ?? null, 'size' => $e['size']], $snap),
		];
	}

	public function storageInfo(Node $n): array {
		$o = [];
		try { $st = $n->getStorage(); $o['class'] = get_class($st); } catch (\Throwable $e) { $o['class'] = 'ERR'; }
		try { $o['storage_numeric_id'] = $n->getMountPoint()->getNumericStorageId(); } catch (\Throwable $e) { $o['storage_numeric_id'] = null; }
		try { $o['mount_point'] = $n->getMountPoint()->getMountPoint(); } catch (\Throwable $e) { $o['mount_point'] = null; }
		try { $o['internal_path'] = $n->getInternalPath(); } catch (\Throwable $e) { $o['internal_path'] = null; }
		return $o;
	}

	/** Wartet aktiv bis zum Zeitpunkt $t (Unix-Mikrosekunden als float) – für präzise gleichzeitige Starts. */
	public static function spinUntil(float $t): float {
		while (($d = $t - microtime(true)) > 0.003) {
			usleep((int)min(2000, max(100, ($d - 0.002) * 1e6 / 2)));
		}
		while (microtime(true) < $t) {
		}
		return microtime(true);
	}
}

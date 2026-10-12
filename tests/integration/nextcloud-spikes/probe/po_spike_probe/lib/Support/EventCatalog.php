<?php
declare(strict_types=1);

namespace OCA\PoSpikeProbe\Support;

use OCP\EventDispatcher\Event;

/** Ermittelt Event-Klassen im Quelltext der laufenden Nextcloud-Version (versionsunabhängig). */
final class EventCatalog {
	private const SKIP = [
		'BeforeFileSystemSetupEvent', 'InvalidateMountCacheEvent', 'BeforeRemotePropfindEvent', 'UserHomeSetupEvent',
	];

	/** @return list<class-string> */
	public static function classes(): array {
		$root = \OC::$SERVERROOT;
		$dirs = [
			[$root . '/lib/public/Files/Events', 'OCP\\Files\\Events'],
			[$root . '/lib/public/Files/Cache', 'OCP\\Files\\Cache'],
			[$root . '/apps/files_trashbin/lib/Events', 'OCA\\Files_Trashbin\\Events'],
			[$root . '/apps/files_versions/lib/Events', 'OCA\\Files_Versions\\Events'],
		];
		$out = [];
		foreach ($dirs as [$dir, $ns]) {
			if (!is_dir($dir)) {
				continue;
			}
			$it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
			foreach ($it as $f) {
				if ($f->getExtension() !== 'php') {
					continue;
				}
				$rel = substr($f->getPathname(), strlen($dir) + 1, -4);
				$cls = $ns . '\\' . str_replace('/', '\\', $rel);
				if (in_array(basename($rel), self::SKIP, true)) {
					continue;
				}
				if (!class_exists($cls)) {
					continue;
				}
				$r = new \ReflectionClass($cls);
				if ($r->isAbstract() || $r->isInterface() || !$r->isSubclassOf(Event::class)) {
					continue;
				}
				$out[] = $cls;
			}
		}
		sort($out);
		return $out;
	}
}

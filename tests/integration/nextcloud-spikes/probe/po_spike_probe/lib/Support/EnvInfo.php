<?php
declare(strict_types=1);

namespace OCA\PoSpikeProbe\Support;

use OCP\App\IAppManager;
use OCP\Files\IRootFolder;
use OCP\Files\Storage\IWrapperStorage;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Lock\ILockingProvider;
use OCP\Server;
use OCP\ServerVersion;

final class EnvInfo {
	public static function collect(): array {
		$cfg = Server::get(IConfig::class);
		$apps = Server::get(IAppManager::class);
		$sv = Server::get(ServerVersion::class);
		$db = Server::get(IDBConnection::class);
		$dbver = null;
		try {
			$qb = $db->getQueryBuilder();
			$dbver = $db->getServerVersion();
		} catch (\Throwable $e) {
			$dbver = 'ERR:' . $e->getMessage();
		}
		$datadir = $cfg->getSystemValueString('datadirectory', '');
		$fs = null;
		if ($datadir !== '' && function_exists('exec')) {
			$o = [];
			@exec('stat -f -c %T ' . escapeshellarg($datadir) . ' 2>&1', $o);
			$fs = $o[0] ?? null;
		}
		$storage = null;
		$chain = [];
		try {
			$uf = Server::get(IRootFolder::class)->getUserFolder('alice');
			$st = $uf->getStorage();
			$chain[] = get_class($st);
			while (method_exists($st, 'getWrapperStorage')) {
				$st = $st->getWrapperStorage();
				$chain[] = get_class($st);
			}
			$storage = end($chain);
			$mp = $uf->getMountPoint();
			$mpClass = get_class($mp);
			$storageId = $uf->getStorage()->getId();
			$storageId = preg_replace('~(home::|local::).*~', '$1<hidden>', (string)$storageId);
		} catch (\Throwable $e) {
			$mpClass = 'ERR:' . $e->getMessage();
			$storageId = null;
		}
		return [
			'nextcloud' => [
				'version' => $sv->getVersionString(),
				'build' => $sv->getVersion(),
				'channel' => $sv->getChannel(),
				'major' => (int)explode('.', $sv->getVersionString())[0],
			],
			'php' => [
				'version' => PHP_VERSION,
				'sapi' => PHP_SAPI,
				'extensions_relevant' => array_values(array_intersect(['hash', 'redis', 'apcu', 'pcntl', 'posix', 'curl', 'imagick', 'exif'], get_loaded_extensions())),
				'memory_limit' => ini_get('memory_limit'),
			],
			'database' => [
				'provider' => $db->getDatabaseProvider(),
				'server_version' => $dbver,
			],
			'storage_class_home' => $storage,
			'storage_wrapper_chain_home' => $chain,
			'mount_point_class_home' => $mpClass,
			'storage_id_class' => $storageId,
			'datadir_filesystem' => $fs,
			'locking' => [
				'filelocking_enabled' => $cfg->getSystemValue('filelocking.enabled', true),
				'provider_class' => get_class(Server::get(ILockingProvider::class)),
				'memcache_locking' => $cfg->getSystemValue('memcache.locking', null),
				'filelocking_ttl' => $cfg->getSystemValue('filelocking.ttl', null),
			],
			'trashbin_enabled' => $apps->isEnabledForAnyone('files_trashbin'),
			'versions_enabled' => $apps->isEnabledForAnyone('files_versions'),
			'encryption_enabled' => $apps->isEnabledForAnyone('encryption'),
			'trashbin_retention_obligation' => $cfg->getSystemValue('trashbin_retention_obligation', 'auto (Default)'),
			'probe_app_version' => $apps->getAppVersion('po_spike_probe'),
		];
	}
}

<?php
declare(strict_types=1);

namespace OCA\PoSpikeProbe\Support;

use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\NotFoundException;

/** Einzeloperationen, identisch im Hauptprozess und in Kindprozessen (Konkurrenztests). */
final class Ops {
	public static function exec(Ctx $c, array $op): array {
		$r = ['op' => $op['type'], 'ok' => true, 'ret' => null, 'exc' => null, 'pid' => getmypid()];
		$r['t0'] = microtime(true);
		try {
			switch ($op['type']) {
				case 'move':
					$n = $c->root->get($op['src']);
					$id = $n->getId();
					$moved = $n->move($op['dst']);
					$r['ret'] = ['path' => $moved->getPath(), 'id' => $moved->getId(), 'id_before' => $id];
					break;
				case 'move_guarded':
					// Prototyp: Node::move mit prozesslokalem Überschreibschutz im Storage-Wrapper (interne API)
					$n = $c->root->get($op['src']);
					NoOverwriteGuard::$active = true;
					try {
						$moved = $n->move($op['dst']);
					} finally {
						NoOverwriteGuard::$active = false;
					}
					$r['ret'] = ['path' => $moved->getPath(), 'id' => $moved->getId()];
					break;
				case 'newfile':
					$dir = $c->root->get($op['dir']);
					$content = Ctx::bytes($op['seed'], $op['size'] ?? 1024);
					$f = $dir->newFile($op['name'], $content);
					$r['ret'] = ['path' => $f->getPath(), 'id' => $f->getId(), 'sha256' => Ctx::shaStr($content)];
					break;
				case 'putcontent':
					$n = $c->root->get($op['path']);
					$content = Ctx::bytes($op['seed'], $op['size'] ?? 1024);
					$n->putContent($content);
					$r['ret'] = ['id' => $n->getId(), 'sha256' => Ctx::shaStr($content)];
					break;
				case 'delete':
					$c->root->get($op['path'])->delete();
					break;
				case 'dav':
					$dav = new Dav($op['user']);
					$headers = $op['headers'] ?? [];
					$body = isset($op['seed']) ? Ctx::bytes($op['seed'], $op['size'] ?? 1024) : null;
					if (isset($op['dest'])) {
						$headers['Destination'] = $dav->files($op['dest']);
					}
					$resp = $dav->req($op['method'], $dav->files($op['rel']), $headers, $body);
					$r['ret'] = Dav::brief($resp);
					$r['ok'] = $resp['status'] >= 200 && $resp['status'] < 300;
					if ($body !== null) {
						$r['ret']['sha256'] = Ctx::shaStr($body);
					}
					$r['t0'] = $resp['t0'];
					$r['t1'] = $resp['t1'];
					return $r;
				case 'lock':
					// Sperre auf existierenden Knoten halten (hold_s Sekunden oder bis Release-Datei)
					$n = $c->root->get($op['path']);
					$type = $op['lock_type'] === 'exclusive' ? \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE : \OCP\Lock\ILockingProvider::LOCK_SHARED;
					$n->lock($type);
					$r['ret'] = ['locked' => $op['path'], 'type' => $op['lock_type']];
					if (!empty($op['marker'])) {
						file_put_contents($op['marker'], (string)getmypid());
					}
					$until = microtime(true) + ($op['hold_s'] ?? 2);
					while (microtime(true) < $until && !(isset($op['release']) && is_file($op['release']))) {
						usleep(20000);
					}
					$n->unlock($type);
					break;
				case 'lock_key':
					// Sperre über den öffentlichen ILockingProvider auf einen Schlüssel (auch nicht existierender Pfad)
					$prov = \OCP\Server::get(\OCP\Lock\ILockingProvider::class);
					$type = $op['lock_type'] === 'exclusive' ? \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE : \OCP\Lock\ILockingProvider::LOCK_SHARED;
					$prov->acquireLock($op['key'], $type, $op['readable'] ?? $op['key']);
					$r['ret'] = ['locked_key' => $op['key'], 'type' => $op['lock_type']];
					if (!empty($op['marker'])) {
						file_put_contents($op['marker'], (string)getmypid());
					}
					$until = microtime(true) + ($op['hold_s'] ?? 2);
					while (microtime(true) < $until && !(isset($op['release']) && is_file($op['release']))) {
						usleep(20000);
					}
					$prov->releaseLock($op['key'], $type);
					break;
				case 'lock_hold_forever':
					// hält die Sperre bis zum Abbruch des Prozesses (Test des Prozessabbruchs)
					$n = $c->root->get($op['path']);
					$n->lock($op['lock_type'] === 'exclusive' ? \OCP\Lock\ILockingProvider::LOCK_EXCLUSIVE : \OCP\Lock\ILockingProvider::LOCK_SHARED);
					file_put_contents($op['marker'], (string)getmypid());
					sleep(600);
					break;
				case 'sleep':
					usleep((int)(($op['s'] ?? 0.1) * 1e6));
					break;
				default:
					throw new \InvalidArgumentException('unbekannte Operation ' . $op['type']);
			}
		} catch (\Throwable $e) {
			$r['ok'] = false;
			$r['exc'] = Json::exc($e);
		}
		$r['t1'] = microtime(true);
		return $r;
	}
}

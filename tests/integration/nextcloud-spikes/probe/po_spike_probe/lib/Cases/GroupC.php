<?php
// WEGWERF-TESTCODE (Nextcloud-API-Spikes, Auftrag 5). Gruppe C: File-Locking.
declare(strict_types=1);

namespace OCA\PoSpikeProbe\Cases;

use OCA\PoSpikeProbe\Support\CaseDef;
use OCA\PoSpikeProbe\Support\Ctx;
use OCA\PoSpikeProbe\Support\Dav;
use OCA\PoSpikeProbe\Support\Proc;
use OCA\PoSpikeProbe\Support\Rec;
use OCP\Files\Node;
use OCP\Lock\ILockingProvider;
use OCP\Server;

final class GroupC {
	private const DOC = 'job-processing.md §6.3.4, SP-E, OQ-069 (lock_target)';

	/** Startet einen Kindprozess, der eine Sperre hält, und wartet bis sie gehalten wird. */
	private static function holder(string $tag, array $op): ?Proc {
		Proc::ensureTmp();
		$marker = Proc::TMP . '/lockmarker-' . $tag . '-' . bin2hex(random_bytes(3));
		$release = $marker . '.release';
		$op['marker'] = $marker;
		$op['release'] = $release;
		$op['hold_s'] = $op['hold_s'] ?? 60;
		$p = Proc::spawn('C-' . $tag, [['off' => 0.0, 'op' => $op]]);
		$p->extra = ['marker' => $marker, 'release' => $release];
		if (!$p->waitReady(45)) {
			return null;
		}
		$p->go(microtime(true) + 0.1);
		$end = microtime(true) + 20;
		while (microtime(true) < $end && !is_file($marker)) {
			usleep(10000);
		}
		return is_file($marker) ? $p : null;
	}

	private static function releaseHolder(?Proc $p): ?array {
		if ($p === null) {
			return null;
		}
		touch($p->extra['release']);
		$p->wait(20);
		$res = $p->results()[0]['res'] ?? null;
		$p->close();
		@unlink($p->extra['marker']);
		@unlink($p->extra['release']);
		return $res;
	}

	public static function lockKey(Node $parentFolder, string $name): string {
		$storageId = $parentFolder->getStorage()->getId();
		$internal = trim($parentFolder->getInternalPath() . '/' . $name, '/');
		return 'files/' . md5($storageId . '::' . $internal);
	}

	/** @return list<CaseDef> */
	public static function cases(): array {
		$d = [];

		$d[] = new CaseDef('C01', 'SP-E', 'Node::lock – Verträglichkeitsmatrix shared/exclusive zwischen zwei Prozessen',
			'Die öffentliche Node::lock-API liefert gegenseitigen Ausschluss: shared+shared erlaubt, jede Kombination mit exclusive wird sofort mit LockedException abgewiesen (kein Warten).',
			function (Ctx $c, Rec $r) {
				$w = $c->workdir('C01');
				$f = $c->put($w, 'a.bin', Ctx::bytes('C01', 1000));
				foreach (['shared', 'exclusive'] as $held) {
					$h = self::holder("c01$held", ['type' => 'lock', 'path' => $f->getPath(), 'lock_type' => $held]);
					if ($h === null) {
						$r->inconclusive("Halter-Prozess ($held) nicht startbar");
						continue;
					}
					foreach (['shared' => ILockingProvider::LOCK_SHARED, 'exclusive' => ILockingProvider::LOCK_EXCLUSIVE] as $want => $const) {
						$res = $r->call("Fremdprozess hält $held, ich erwerbe $want", function () use ($f, $const, $c) {
							$n = $c->root->get($f->getPath());
							$n->lock($const);
							$n->unlock($const);
							return 'acquired';
						});
						$expectOk = ($held === 'shared' && $want === 'shared');
						$r->observe("held_{$held}_want_{$want}", $res['ok'] ? 'acquired' : $res['exc']);
						$r->assert($res['ok'] === $expectOk, "held=$held want=$want → " . ($expectOk ? 'erlaubt' : 'abgewiesen'), $res['exc']);
					}
					self::releaseHolder($h);
				}
				$res = $r->call('unlock ohne gehaltene Sperre', fn() => $c->root->get($f->getPath())->unlock(ILockingProvider::LOCK_SHARED));
				$r->observe('unlock_without_lock', $res['ok'] ? 'kein Fehler' : $res['exc']);
				$r->note('Erwerb schlägt sofort fehl, es gibt kein Warten/Timeout (siehe LockedException-Meldungen).');
			}, false, self::DOC);

		$d[] = new CaseDef('C02', 'SP-E', 'Konkurrenz durch WebDAV-Client und Node-API gegen eine gesperrte Quelldatei',
			'Eine App-Sperre (Node::lock exclusive) auf der Quelle schützt vor Move/Überschreiben/Löschen/Lesen durch WebDAV-Clients und andere Prozesse, solange sie gehalten wird (Sperren wirken für alle Nextcloud-Codepfade, nicht für Zugriffe am Storage vorbei).',
			function (Ctx $c, Rec $r) {
				$w = $c->workdir('C02');
				$content = Ctx::bytes('C02', 1500);
				$f = $c->put($w, 'src.bin', $content);
				$rel = 'spike/' . $c->runId . '/C02/';
				$dav = new Dav('alice');
				foreach (['exclusive', 'shared'] as $held) {
					$h = self::holder("c02$held", ['type' => 'lock', 'path' => $f->getPath(), 'lock_type' => $held]);
					if ($h === null) {
						$r->inconclusive('Halter nicht gestartet');
						continue;
					}
					$out = [];
					$out['dav_get'] = $dav->get($rel . 'src.bin')['status'];
					$out['dav_put_overwrite'] = $dav->put($rel . 'src.bin', 'XXX')['status'];
					$out['dav_move'] = $dav->move($rel . 'src.bin', $rel . 'moved.bin', false)['status'];
					$out['dav_delete'] = $dav->delete($rel . 'src.bin')['status'];
					$n = $c->root->get($f->getPath());
					$mv = $r->call("$held: Node::move", fn() => $n->move($w->getPath() . '/moved2.bin'));
					$out['node_move'] = $mv['ok'] ? 'ok' : ($mv['exc']['class'] ?? '?');
					$n = $c->root->get($f->getPath());
					$dl = $r->call("$held: Node::delete", fn() => $n->delete());
					$out['node_delete'] = $dl['ok'] ? 'ok' : ($dl['exc']['class'] ?? '?');
					$r->observe("while_$held", $out);
					self::releaseHolder($h);
					$snap = $c->snapshot($w);
					$r->assert(isset($snap['src.bin']) && ($snap['src.bin']['sha256'] ?? null) === Ctx::shaStr($content) && count($snap) === 1, "$held: Datei unverändert an Ort und Stelle", array_keys($snap));
					if ($held === 'exclusive') {
						$r->assert($out['dav_put_overwrite'] >= 400 && $out['dav_move'] >= 400 && $out['dav_delete'] >= 400 && $out['node_move'] !== 'ok' && $out['node_delete'] !== 'ok', 'exklusive Sperre: Schreibzugriffe werden abgewiesen', $out);
						$r->observe('dav_get_while_exclusive', $out['dav_get']);
					}
					$r->after = $snap;
					if ($held === 'exclusive') {
						// Datei ist unverändert -> shared-Lauf möglich
					}
				}
			}, false, self::DOC);

		$d[] = new CaseDef('C03', 'SP-E', 'Sperre auf Zielordner: wirkt sie auf Anlage/Move von Kindern?',
			'Eine exklusive Sperre auf den Zielordner verhindert Anlage und Move von Dateien in diesen Ordner (hierarchischer Schutz des Zielordners).',
			function (Ctx $c, Rec $r) {
				$w = $c->workdir('C03');
				$dst = $w->newFolder('dst');
				$f = $c->put($w, 'src.bin', Ctx::bytes('C03', 1000));
				$rel = 'spike/' . $c->runId . '/C03/';
				$dav = new Dav('alice');
				foreach (['exclusive', 'shared'] as $held) {
					$h = self::holder("c03$held", ['type' => 'lock', 'path' => $dst->getPath(), 'lock_type' => $held]);
					if ($h === null) {
						$r->inconclusive('Halter nicht gestartet');
						continue;
					}
					$out = [];
					$nf = $r->call("$held: newFile im gesperrten Ordner", fn() => $c->root->get($dst->getPath())->newFile('new.bin', 'x'));
					$out['node_newFile'] = $nf['ok'] ? 'ok' : $nf['exc']['class'];
					$mv = $r->call("$held: Node::move in den gesperrten Ordner", fn() => $c->root->get($w->getPath() . '/src.bin')->move($dst->getPath() . '/src.bin'));
					$out['node_move_into'] = $mv['ok'] ? 'ok' : $mv['exc']['class'];
					if ($mv['ok']) {
						$c->root->get($dst->getPath() . '/src.bin')->move($w->getPath() . '/src.bin');
					}
					$out['dav_put_into'] = $dav->put($rel . 'dst/viaDav.bin', 'x')['status'];
					$r->observe("folder_lock_$held", $out);
					self::releaseHolder($h);
					foreach (['new.bin', 'viaDav.bin'] as $n) {
						if ($c->root->nodeExists($dst->getPath() . '/' . $n)) {
							$c->root->get($dst->getPath() . '/' . $n)->delete();
						}
					}
					if ($held === 'exclusive') {
						$r->assert($out['node_newFile'] !== 'ok' && $out['node_move_into'] !== 'ok' && $out['dav_put_into'] >= 400, 'Ordner-Sperre exklusiv blockiert Kinder-Anlage und Move hinein', $out);
					} else {
						$r->note('shared-Ordner-Sperre: ' . json_encode($out));
					}
				}
				$r->after = $c->snapshot($w);
			}, false, self::DOC);

		$d[] = new CaseDef('C04', 'SP-E', 'Nicht existierender Zielpfad: Sperrbarkeit über ILockingProvider (lock_target)',
			'Ein noch nicht existierender Zielpfad kann gesperrt werden (pfadbasierter Schlüssel) und die Sperre hält fremde Anlage/Moves ab; unklar bleibt, ob der eigene Move trotz eigener Sperre möglich ist.',
			function (Ctx $c, Rec $r) {
				$w = $c->workdir('C04');
				$f = $c->put($w, 'src.bin', Ctx::bytes('C04-src', 1000));
				$rel = 'spike/' . $c->runId . '/C04/';
				$res = $r->call('Node::lock auf nicht existierenden Pfad (Node-Objekt existiert nicht)', fn() => $c->root->get($w->getPath() . '/target.bin')->lock(ILockingProvider::LOCK_EXCLUSIVE));
				$r->observe('node_lock_on_nonexisting', $res['ok'] ? 'ok' : $res['exc']);
				$key = self::lockKey($w, 'target.bin');
				$r->observe('provider_key_scheme', 'files/md5(storageId::internalPath) – interne, nicht dokumentierte Namenskonvention (Nextcloud Common::acquireLock)');
				$prov = Server::get(ILockingProvider::class);
				$readable = 'po-spike C04';
				$prov->acquireLock($key, ILockingProvider::LOCK_EXCLUSIVE, $readable);
				$out = [];
				try {
					$dav = new Dav('alice');
					$out['dav_put_target'] = $dav->put($rel . 'target.bin', 'dav')['status'];
					$out['dav_move_to_target'] = $dav->move($rel . 'src.bin', $rel . 'target.bin', false)['status'];
					$own = $r->call('eigener Node::move auf den von mir exklusiv gesperrten Zielpfad', fn() => $c->root->get($w->getPath() . '/src.bin')->move($w->getPath() . '/target.bin'));
					$out['own_node_move'] = $own['ok'] ? 'ok' : $own['exc']['class'];
					$cr = Proc::race('C04', ['x' => [['off' => 0.0, 'op' => ['type' => 'newfile', 'dir' => $w->getPath(), 'name' => 'target.bin', 'seed' => 'C04-other', 'size' => 100]]]]);
					$cres = $cr['children']['x']['results'][0]['res'] ?? null;
					$out['other_process_newFile'] = $cres ? ($cres['ok'] ? 'ok' : ($cres['exc']['class'] ?? '?')) : 'kein Ergebnis';
				} finally {
					$prov->releaseLock($key, ILockingProvider::LOCK_EXCLUSIVE);
				}
				$r->observe('while_target_key_locked_exclusive', $out);
				$r->after = $c->snapshot($w);
				$othersBlocked = ($out['dav_put_target'] ?? 0) >= 400 && ($out['dav_move_to_target'] ?? 0) >= 400 && ($out['other_process_newFile'] ?? 'ok') !== 'ok';
				$r->assert($othersBlocked, 'fremde Anlage/Move auf den gesperrten, nicht existierenden Zielpfad werden abgewiesen', $out);
				$r->observe('own_move_blocked_by_own_lock', ($out['own_node_move'] ?? 'ok') !== 'ok');
				$r->note('Der Schlüssel wird mit der internen Konvention von Nextcloud gebildet (nicht Teil der öffentlichen API-Verträge). Ob der eigene Move trotz eigener Sperre läuft, steht unter own_move_blocked_by_own_lock.');
				$r->evidence = 'single_observation';
			}, false, self::DOC);

		$d[] = new CaseDef('C05', 'SP-E', 'Wiedereintritt: eigene Sperre auf der Quelle und anschließender Node::move',
			'Node::move funktioniert, wenn der Aufrufer die Quelle bereits selbst (shared) gesperrt hat; mit eigener exklusiver Sperre scheitert er an der eigenen Sperre (kein Wiedereintritt).',
			function (Ctx $c, Rec $r) {
				$w = $c->workdir('C05');
				$f = $c->put($w, 'a.bin', Ctx::bytes('C05', 800));
				$results = [];
				foreach (['shared' => ILockingProvider::LOCK_SHARED, 'exclusive' => ILockingProvider::LOCK_EXCLUSIVE] as $label => $const) {
					$n = $c->root->get($w->getPath() . '/a.bin');
					$n->lock($const);
					$mv = $r->call("Node::move bei eigener $label-Sperre auf der Quelle", fn() => $n->move($w->getPath() . '/b.bin'));
					$results[$label] = $mv['ok'] ? 'ok' : $mv['exc']['class'] . ': ' . $mv['exc']['message'];
					try { $n->unlock($const); } catch (\Throwable $e) { $results[$label . '_unlock'] = get_class($e); }
					if ($mv['ok']) {
						$c->root->get($w->getPath() . '/b.bin')->move($w->getPath() . '/a.bin');
					}
				}
				$r->observe('reentrancy', $results);
				$r->after = $c->snapshot($w);
				$r->assert(str_starts_with($results['shared'], 'ok'), 'eigene shared-Sperre blockiert den eigenen Move nicht', $results['shared']);
				$r->assert($results['exclusive'] !== 'ok', 'eigene exklusive Sperre blockiert den eigenen Move (kein Wiedereintritt) – Erwartung', $results['exclusive']);
			}, false, self::DOC);

		$d[] = new CaseDef('C06', 'SP-E', 'Zwei Worker erwerben gleichzeitig dieselbe exklusive Sperre (kooperativer Mutex)',
			'Von zwei gleichzeitig startenden Prozessen erhält höchstens einer die exklusive Sperre; beide gleichzeitig ist nie möglich.',
			function (Ctx $c, Rec $r, int $repeat) {
				$n = max(10, (int)($repeat / 4));
				$w = $c->workdir('C06');
				$plans = ['a' => [], 'b' => []];
				for ($i = 0; $i < $n; $i++) {
					$c->put($w, "f$i.bin", Ctx::bytes("C06-$i", 100));
					foreach (['a', 'b'] as $k) {
						$plans[$k][] = ['off' => 0.6 + $i * 0.9, 'op' => ['type' => 'lock', 'path' => $w->getPath() . "/f$i.bin", 'lock_type' => 'exclusive', 'hold_s' => 0.35]];
					}
				}
				$race = Proc::race('C06', $plans, 0.5, 60 + $n * 2);
				if (!$race['ready']) {
					$r->inconclusive('Kindprozesse nicht bereit');
					return;
				}
				$both = 0;
				for ($i = 0; $i < $n; $i++) {
					$a = $race['children']['a']['results'][$i]['res'] ?? null;
					$b = $race['children']['b']['results'][$i]['res'] ?? null;
					if (!$a || !$b) {
						$r->tally('missing');
						continue;
					}
					$ov = $a['t0'] < $b['t1'] && $b['t0'] < $a['t1'];
					$key = ($a['ok'] ? 'a_won' : 'a_lost') . '/' . ($b['ok'] ? 'b_won' : 'b_lost');
					$r->tally($key);
					if ($a['ok'] && $b['ok'] && $ov) {
						// beide erfolgreich: nur dann Verletzung, wenn die Haltezeiten überlappten (hold 0,35 s)
						$both++;
						$r->tally('VIOLATION_both_hold_exclusive_lock');
					}
				}
				$r->attempts = $n;
				$r->assert($both === 0, "nie beide gleichzeitig Besitzer in $n Versuchen", $r->tally);
				$r->evidence = $both ? 'counterexample' : 'finite_sample';
				$r->note('Die Messung der Überlappung beruht auf den Zeitstempeln innerhalb von Op.t0..t1 (Erwerb bis Freigabe).');
			}, true, self::DOC);

		$d[] = new CaseDef('C07', 'SP-E', 'Prozessabbruch (kill -9) mit gehaltener Sperre: bleibt die Sperre bestehen, wie lange?',
			'Eine Sperre eines abgebrochenen Prozesses wird nicht sofort freigegeben; sie ist bis zum Ablauf der Sperrlebensdauer wirksam (temporärer Fehler mit Backoff, keine automatische Freigabe durch die App möglich).',
			function (Ctx $c, Rec $r) {
				$w = $c->workdir('C07');
				$f = $c->put($w, 'a.bin', Ctx::bytes('C07', 500));
				Proc::ensureTmp();
				$marker = Proc::TMP . '/lockmarker-c07-' . bin2hex(random_bytes(3));
				$p = Proc::spawn('C07', [['off' => 0.0, 'op' => ['type' => 'lock_hold_forever', 'path' => $f->getPath(), 'lock_type' => 'exclusive', 'marker' => $marker]]]);
				if (!$p->waitReady(45)) {
					$r->inconclusive('Kindprozess nicht bereit');
					return;
				}
				$p->go(microtime(true) + 0.1);
				$end = microtime(true) + 20;
				while (microtime(true) < $end && !is_file($marker)) {
					usleep(10000);
				}
				if (!is_file($marker)) {
					$r->inconclusive('Sperre nicht erworben');
					return;
				}
				$before = $r->call('lock während Halter lebt', fn() => $c->root->get($f->getPath())->lock(ILockingProvider::LOCK_EXCLUSIVE));
				$p->kill(9);
				$p->wait(10);
				$p->close();
				sleep(2);
				$after = $r->call('lock 2 s nach kill -9 des Halters', function () use ($c, $f) {
					$n = $c->root->get($f->getPath());
					$n->lock(ILockingProvider::LOCK_EXCLUSIVE);
					$n->unlock(ILockingProvider::LOCK_EXCLUSIVE);
					return 'acquired';
				});
				$r->observe('lock_while_holder_alive', $before['ok'] ? 'acquired' : $before['exc']['class']);
				$r->observe('lock_2s_after_kill', $after['ok'] ? 'acquired' : $after['exc']['class']);
				// TTL der Sperre direkt im Sperr-Cache (Testinfrastruktur) ablesen
				$ttl = null;
				$keys = [];
				try {
					$cfg = Server::get(\OCP\IConfig::class)->getSystemValue('redis', []);
					if ($cfg && class_exists(\Redis::class)) {
						$rd = new \Redis();
						$rd->connect($cfg['host'] ?? 'redis', (int)($cfg['port'] ?? 6379), 2.0);
						if (!empty($cfg['password'])) {
							$rd->auth($cfg['password']);
						}
						$key = self::lockKey($w, 'a.bin');
						foreach ($rd->keys('*' . $key . '*') as $k) {
							$keys[] = ['key' => preg_replace('~^.*?(files/)~', '$1', $k), 'ttl' => $rd->ttl($k)];
							$ttl = $rd->ttl($k);
							$rd->del($k); // Testaufräumen: nur diese Test-Sperre
						}
					}
				} catch (\Throwable $e) {
					$r->note('TTL nicht lesbar: ' . $e->getMessage());
				}
				$r->observe('stale_lock_keys_ttl_seconds', $keys);
				$r->observe('configured_filelocking_ttl', Server::get(\OCP\IConfig::class)->getSystemValue('filelocking.ttl', 'nicht gesetzt (Standard: max(3600, max_execution_time))'));
				$release = $r->call('lock nach manuellem Löschen des Schlüssels (nur Testaufräumen)', function () use ($c, $f) {
					$n = $c->root->get($f->getPath());
					$n->lock(ILockingProvider::LOCK_EXCLUSIVE);
					$n->unlock(ILockingProvider::LOCK_EXCLUSIVE);
					return 'acquired';
				});
				$r->assert(!$after['ok'], 'Sperre bleibt nach kill -9 des Halters wirksam (Erwartung)', $after['exc']);
				$r->assert($ttl !== null && $ttl > 60, 'Sperre trägt eine lange Restlaufzeit (>60 s)', $ttl);
				$r->observe('after_test_cleanup', $release['ok'] ? 'Schlüssel gelöscht, Sperre wieder erwerbbar' : $release['exc']);
			}, false, self::DOC);

		return $d;
	}
}

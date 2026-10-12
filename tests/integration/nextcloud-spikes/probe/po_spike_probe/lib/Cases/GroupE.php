<?php
// WEGWERF-TESTCODE (Nextcloud-API-Spikes, Auftrag 5). Gruppe E: Dateievents.
declare(strict_types=1);

namespace OCA\PoSpikeProbe\Cases;

use OCA\PoSpikeProbe\Support\CaseDef;
use OCA\PoSpikeProbe\Support\Ctx;
use OCA\PoSpikeProbe\Support\Dav;
use OCA\PoSpikeProbe\Support\EventRecorder;
use OCA\PoSpikeProbe\Support\Rec;

final class GroupE {
	private const DOC = 'job-processing.md §4 (Events), OQ-056, SP-E/Events-Spike, nextcloud-integration.md §2.3';

	private static function short(string $cls): string {
		return substr(strrchr('\\' . $cls, '\\'), 1);
	}

	/** @return list<array> Ereignisse im Zeitfenster, ohne Appdata-/Cache-Rauschen */
	private static function window(float $t0, float $t1): array {
		$out = [];
		foreach (EventRecorder::read() as $e) {
			if ($e['t'] < $t0 || $e['t'] > $t1) {
				continue;
			}
			$paths = [];
			foreach (['node', 'source', 'target', 'folder'] as $k) {
				if (isset($e['props'][$k]['path'])) {
					$paths[] = $e['props'][$k]['path'];
				}
			}
			if ($paths && count(array_filter($paths, fn($p) => str_contains($p, '/appdata_') || str_contains($p, '/cache/'))) === count($paths)) {
				continue;
			}
			$e['dt_ms'] = round(($e['t'] - $t0) * 1000, 1);
			$out[] = $e;
		}
		usort($out, fn($a, $b) => $a['t'] <=> $b['t']);
		return $out;
	}

	private static function line(array $e): string {
		$p = $e['props'];
		$f = fn($n) => is_array($n) ? ($n['path'] ?? '?') . '#' . ($n['id'] ?? '?') . (isset($n['size']) ? ' size=' . $n['size'] : '') : (string)$n;
		$parts = [];
		foreach (['node', 'source', 'target', 'folder'] as $k) {
			if (isset($p[$k])) {
				$parts[] = "$k=" . $f($p[$k]);
			}
		}
		foreach ($p as $k => $v) {
			if (!in_array($k, ['node', 'source', 'target', 'folder'], true)) {
				$parts[] = "$k=" . (is_scalar($v) || $v === null ? var_export($v, true) : json_encode($v));
			}
		}
		return sprintf('+%sms %s [%s pid=%d %s] %s', $e['dt_ms'], self::short($e['class']), $e['sapi'], $e['pid'], ($e['method'] ?? '-'), implode(' ', $parts));
	}

	/**
	 * Führt eine Aktion aus, sammelt die dabei ausgelösten Events und prüft Mengen je Klasse.
	 * @param array<string,array{0:int,1:int}> $expect Klasse → [min,max]
	 */
	private static function scenario(Rec $r, string $label, callable $action, array $expect): array {
		usleep(300000);
		$t0 = microtime(true);
		$res = $action();
		usleep(600000);
		$t1 = microtime(true);
		$ev = self::window($t0 - 0.005, $t1);
		$counts = [];
		foreach ($ev as $e) {
			$counts[self::short($e['class'])] = ($counts[self::short($e['class'])] ?? 0) + 1;
		}
		$r->observe("sequence:$label", array_map([self::class, 'line'], array_values(array_filter($ev, fn($e) => !str_starts_with(self::short($e['class']), 'CacheEntr')))));
		$r->observe("counts:$label", $counts);
		foreach ($expect as $cls => [$min, $max]) {
			$n = $counts[$cls] ?? 0;
			$r->assert($n >= $min && $n <= $max, "$label: $cls erwartet $min.." . ($max === 99 ? 'n' : $max) . ", beobachtet $n", $counts);
		}
		$r->expected[$label] = array_map(fn($x) => $x[0] . '..' . ($x[1] === 99 ? 'n' : $x[1]), $expect);
		return ['events' => $ev, 'counts' => $counts, 'action' => $res];
	}

	/** @return list<CaseDef> */
	public static function cases(): array {
		$d = [];

		$d[] = new CaseDef('E01', 'Events', 'App-interne Dateioperationen über die Node-API (CLI-Prozess): Create, Write, Rename, Move, Copy, Delete',
			'Jede App-Operation löst genau ein Hauptereignis (NodeCreated / NodeRenamed / NodeCopied / NodeDeleted) aus; Mehrfachzustellung tritt nicht auf. (Naive Erwartung; Abweichungen zeigen den Idempotenzbedarf.)',
			function (Ctx $c, Rec $r) {
				$w = $c->workdir('E01');
				$dst = $w->newFolder('dst');
				$f = null;
				self::scenario($r, 'node_newFile', function () use ($w, &$f) { $f = $w->newFile('a.bin', Ctx::bytes('E01', 5000)); }, ['NodeCreatedEvent' => [1, 1], 'NodeWrittenEvent' => [1, 99]]);
				self::scenario($r, 'node_putContent', function () use ($c, $w) { $c->root->get($w->getPath() . '/a.bin')->putContent(Ctx::bytes('E01-2', 6000)); }, ['NodeCreatedEvent' => [0, 0], 'NodeWrittenEvent' => [1, 99]]);
				self::scenario($r, 'node_rename_same_folder', function () use ($c, $w) { $c->root->get($w->getPath() . '/a.bin')->move($w->getPath() . '/b.bin'); }, ['BeforeNodeRenamedEvent' => [1, 1], 'NodeRenamedEvent' => [1, 1], 'NodeCreatedEvent' => [0, 0], 'NodeDeletedEvent' => [0, 0]]);
				self::scenario($r, 'node_move_other_folder', function () use ($c, $w, $dst) { $c->root->get($w->getPath() . '/b.bin')->move($dst->getPath() . '/b.bin'); }, ['NodeRenamedEvent' => [1, 1], 'NodeCreatedEvent' => [0, 0], 'NodeDeletedEvent' => [0, 0]]);
				self::scenario($r, 'node_copy', function () use ($c, $dst, $w) { $c->root->get($dst->getPath() . '/b.bin')->copy($w->getPath() . '/copy.bin'); }, ['NodeCopiedEvent' => [1, 1]]);
				self::scenario($r, 'node_touch', function () use ($c, $w) { $c->root->get($w->getPath() . '/copy.bin')->touch(time() + 5); }, ['NodeTouchedEvent' => [1, 1]]);
				self::scenario($r, 'node_delete', function () use ($c, $w) { $c->root->get($w->getPath() . '/copy.bin')->delete(); }, ['NodeDeletedEvent' => [1, 1], 'BeforeNodeDeletedEvent' => [1, 1]]);
				$r->after = $c->snapshot($w);
			}, false, self::DOC);

		$d[] = new CaseDef('E02', 'Events', 'WebDAV-Operationen eines Clients (Apache-Prozess): PUT neu/überschreiben, MOVE (Rename/Move), COPY, DELETE',
			'WebDAV-Aktionen lösen dieselben Ereignisklassen aus wie die Node-API, und zwar im Webserver-Prozess (nicht im Cron/CLI-Kontext des Organizers).',
			function (Ctx $c, Rec $r) {
				$w = $c->workdir('E02');
				$w->newFolder('dst');
				$dav = new Dav('alice');
				$rel = 'spike/' . $c->runId . '/E02/';
				self::scenario($r, 'dav_put_new', fn() => $dav->put($rel . 'a.bin', Ctx::bytes('E02', 200000)), ['NodeCreatedEvent' => [1, 1], 'NodeWrittenEvent' => [1, 99]]);
				self::scenario($r, 'dav_put_overwrite', fn() => $dav->put($rel . 'a.bin', Ctx::bytes('E02-2', 200000)), ['NodeCreatedEvent' => [0, 0], 'NodeWrittenEvent' => [1, 99]]);
				self::scenario($r, 'dav_move_rename', fn() => $dav->move($rel . 'a.bin', $rel . 'b.bin', false), ['NodeRenamedEvent' => [1, 1], 'NodeCreatedEvent' => [0, 0], 'NodeDeletedEvent' => [0, 0]]);
				self::scenario($r, 'dav_move_other_folder', fn() => $dav->move($rel . 'b.bin', $rel . 'dst/b.bin', false), ['NodeRenamedEvent' => [1, 1], 'NodeCreatedEvent' => [0, 0], 'NodeDeletedEvent' => [0, 0]]);
				self::scenario($r, 'dav_copy', fn() => $dav->copy($rel . 'dst/b.bin', $rel . 'copy.bin', false), ['NodeCopiedEvent' => [1, 1]]);
				self::scenario($r, 'dav_delete', fn() => $dav->delete($rel . 'copy.bin'), ['NodeDeletedEvent' => [1, 1]]);
				self::scenario($r, 'dav_mkcol', fn() => $dav->mkcol($rel . 'newdir'), ['NodeCreatedEvent' => [1, 1]]);
				$r->after = $c->snapshot($w);
			}, false, self::DOC);

		$d[] = new CaseDef('E03', 'Events', 'Chunked Upload v2 (Nextcloud-/Mobil-/Desktop-Clients): wann und wie oft Ereignisse für die Zieldatei',
			'Beim Chunked Upload entsteht die Zieldatei erst beim abschließenden MOVE; Zwischenstände (Chunks) lösen keine Ereignisse für den Zielpfad aus, und die Zieldatei ist bei den Ereignissen vollständig.',
			function (Ctx $c, Rec $r) {
				$w = $c->workdir('E03');
				$dav = new Dav('alice');
				$rel = 'spike/' . $c->runId . '/E03/';
				$uuid = 'spike-' . bin2hex(random_bytes(4));
				$c1 = Ctx::bytes('E03-c1', 150000);
				$c2 = Ctx::bytes('E03-c2', 150000);
				$dest = $dav->files($rel . 'big.bin');
				$up = $dav->url('uploads', $uuid);
				$out = self::scenario($r, 'chunked_upload_v2', function () use ($dav, $up, $dest, $c1, $c2) {
					$res = [];
					$res[] = Dav::brief($dav->req('MKCOL', $up, ['Destination' => $dest]));
					$res[] = Dav::brief($dav->req('PUT', $up . '/00001', ['OC-Total-Length' => (string)(strlen($c1) + strlen($c2))], $c1));
					$res[] = Dav::brief($dav->req('PUT', $up . '/00002', ['OC-Total-Length' => (string)(strlen($c1) + strlen($c2))], $c2));
					$res[] = Dav::brief($dav->req('MOVE', $up . '/.file', ['Destination' => $dest, 'OC-Total-Length' => (string)(strlen($c1) + strlen($c2))]));
					return $res;
				}, ['NodeCreatedEvent' => [1, 99]]);
				$r->observe('http', $out['action']);
				$r->after = $c->snapshot($w);
				$r->assert(($r->after['big.bin']['sha256'] ?? null) === Ctx::shaStr($c1 . $c2), 'zusammengesetzte Datei korrekt (Testvoraussetzung)');
				$targetEvents = array_values(array_filter($out['events'], fn($e) => str_contains(json_encode($e['props'], JSON_UNESCAPED_SLASHES), '/E03/big.bin')));
				$r->observe('events_for_target_only', array_map([self::class, 'line'], $targetEvents));
				$r->observe('events_for_target_classes', array_count_values(array_map(fn($e) => self::short($e['class']), $targetEvents)));
				$firstTarget = $targetEvents[0] ?? null;
				$r->observe('target_events_before_move_completed', null);
				// Chunk-Phase: gab es Ereignisse für Uploadpfade bzw. den Zielpfad vor dem abschließenden MOVE?
				$chunkEvents = array_values(array_filter($out['events'], fn($e) => str_contains(json_encode($e['props'], JSON_UNESCAPED_SLASHES), '/uploads/')));
				$r->observe('events_for_upload_area', array_map([self::class, 'line'], $chunkEvents));
				$sizes = array_map(fn($e) => $e['props']['node']['size'] ?? ($e['props']['target']['size'] ?? null), array_values(array_filter($targetEvents, fn($e) => !str_starts_with(self::short($e['class']), 'Before'))));
				$r->observe('sizes_in_after_events', $sizes);
				$r->assert(count(array_filter($sizes, fn($s) => $s !== null && $s !== strlen($c1 . $c2))) === 0, 'in allen After-Events für die Zieldatei ist die Datei bereits vollständig (Größe = Gesamtlänge)', $sizes);
			}, false, self::DOC);

		$d[] = new CaseDef('E04', 'Events', 'Papierkorb: Delete und Restore (Node-API und WebDAV-Restore)',
			'Löschen löst NodeDeleted + MoveToTrash aus; die Wiederherstellung ist an eigenen Ereignissen (NodeRestored / NodeCreated) erkennbar, nicht als Rename.',
			function (Ctx $c, Rec $r) {
				$w = $c->workdir('E04');
				$dav = new Dav('alice');
				$f = $c->put($w, 'x.bin', Ctx::bytes('E04', 3000));
				$id = $f->getId();
				self::scenario($r, 'trash_by_node_delete', fn() => $c->root->get($w->getPath() . '/x.bin')->delete(), ['NodeDeletedEvent' => [1, 1], 'MoveToTrashEvent' => [1, 1]]);
				$it = GroupD::items($dav, 'spike/' . $c->runId . '/E04/x.bin');
				if (count($it) !== 1) {
					$r->inconclusive('Papierkorbeintrag nicht eindeutig');
					return;
				}
				self::scenario($r, 'restore_via_webdav', fn() => $dav->req('MOVE', $dav->base() . $it[0][0], ['Destination' => $dav->url('trashbin', 'restore/' . basename($it[0][0]))]), ['NodeRestoredEvent' => [1, 1], 'NodeRenamedEvent' => [0, 0]]);
				$r->after = $c->snapshot($w);
				$r->assert(($r->after['x.bin']['id'] ?? null) === $id, 'File-ID nach Restore unverändert (Querverweis B05)');
			}, false, self::DOC);

		$d[] = new CaseDef('E05', 'Events', 'Identitätsmerkmale der Ereignisse (Pfad, File-ID, Quelle/Ziel) über alle Szenarien',
			'Alle After-Ereignisse tragen File-ID und Pfad des betroffenen Knotens; NodeRenamedEvent trägt Quelle und Ziel mit derselben File-ID.',
			function (Ctx $c, Rec $r) {
				$w = $c->workdir('E05');
				$dav = new Dav('alice');
				$rel = 'spike/' . $c->runId . '/E05/';
				$f = $c->put($w, 'a.bin', Ctx::bytes('E05', 2000));
				$id = $f->getId();
				usleep(300000);
				$t0 = microtime(true);
				$c->root->get($f->getPath())->move($w->getPath() . '/b.bin');
				$dav->move($rel . 'b.bin', $rel . 'c.bin', false);
				usleep(600000);
				$ev = self::window($t0 - 0.005, microtime(true));
				$ren = array_values(array_filter($ev, fn($e) => self::short($e['class']) === 'NodeRenamedEvent'));
				$r->observe('renamed_events', array_map([self::class, 'line'], $ren));
				$r->assert(count($ren) === 2, 'zwei NodeRenamedEvent (einer je Rename)', count($ren));
				foreach ($ren as $e) {
					$sId = $e['props']['source']['id'] ?? null;
					$tId = $e['props']['target']['id'] ?? null;
					$r->assert($tId === $id, 'NodeRenamedEvent: das Ziel-Node trägt die File-ID ' . $id, [$tId, $e['sapi']]);
					$r->observe('source_node_id_in_' . $e['sapi'] . '_event', $sId);
					$r->assert($sId === $id, 'NodeRenamedEvent: auch das Quell-Node liefert die File-ID (Erwartung der bisherigen Dokumentation)', [$sId, $e['sapi']]);
				}
				$after = array_values(array_filter($ev, fn($e) => !str_starts_with(self::short($e['class']), 'Before') && isset($e['props']['node'])));
				$r->observe('after_events_with_node_ids', array_map(fn($e) => self::short($e['class']) . ':' . ($e['props']['node']['id'] ?? 'null'), $after));
				$r->after = $c->snapshot($w);
			}, false, self::DOC);

		$d[] = new CaseDef('E06', 'Events', 'Ordner-Move: Ereignisse für den Ordner und für enthaltene Dateien (group_follow-Erkennung)',
			'Beim Verschieben eines Ordners mit Dateien entsteht genau ein NodeRenamedEvent für den Ordner; für die enthaltenen Dateien gibt es keine eigenen NodeRenamedEvents (Pfadänderung der Kinder muss aus dem Ordner-Ereignis abgeleitet werden).',
			function (Ctx $c, Rec $r) {
				$w = $c->workdir('E06');
				$w->newFolder('dst');
				$dir = $w->newFolder('album');
				$k1 = $c->put($dir, 'IMG_0001.jpg', Ctx::bytes('E06-1', 1000));
				$k2 = $c->put($dir, 'IMG_0001.xmp', Ctx::bytes('E06-2', 300));
				$ids = [$k1->getId(), $k2->getId()];
				$dav = new Dav('alice');
				$rel = 'spike/' . $c->runId . '/E06/';
				$out = self::scenario($r, 'dav_move_folder_with_children', fn() => $dav->move($rel . 'album', $rel . 'dst/album', false), ['NodeRenamedEvent' => [1, 1]]);
				$ren = array_values(array_filter($out['events'], fn($e) => self::short($e['class']) === 'NodeRenamedEvent'));
				$r->observe('renamed_event_targets', array_map(fn($e) => ($e['props']['target']['path'] ?? '?') . '#' . ($e['props']['target']['id'] ?? '?'), $ren));
				$cacheUpd = array_values(array_filter($out['events'], fn($e) => self::short($e['class']) === 'CacheEntryUpdatedEvent'));
				$kidEvents = array_values(array_filter($cacheUpd, fn($e) => in_array($e['props']['fileId'] ?? null, $ids, true)));
				$r->observe('cache_updated_events_for_children', array_map(fn($e) => $e['props']['path'] . '#' . $e['props']['fileId'], $kidEvents));
				$r->after = $c->snapshot($w);
				$r->assert(($r->after['dst/album/IMG_0001.jpg']['id'] ?? null) === $ids[0] && ($r->after['dst/album/IMG_0001.xmp']['id'] ?? null) === $ids[1], 'Kinder behalten ihre File-IDs');
				$r->note('Ob CacheEntryUpdatedEvent für die Kinder verlässlich ausgeliefert wird, ist nur hier beobachtet; als Erkennungsquelle für Kinder-Pfadänderungen ist der Ordner-Rename plus ein Teilbaum-Abgleich (Listing nach file_id) vorzusehen.');
			}, false, self::DOC);

		return $d;
	}
}

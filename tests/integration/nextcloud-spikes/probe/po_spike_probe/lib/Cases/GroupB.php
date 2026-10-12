<?php
// WEGWERF-TESTCODE (Nextcloud-API-Spikes, Auftrag 5). Gruppe B: File-IDs und Storage-Referenzen.
declare(strict_types=1);

namespace OCA\PoSpikeProbe\Cases;

use OCA\PoSpikeProbe\Support\CaseDef;
use OCA\PoSpikeProbe\Support\Ctx;
use OCA\PoSpikeProbe\Support\Dav;
use OCA\PoSpikeProbe\Support\Proc;
use OCA\PoSpikeProbe\Support\Rec;
use OCP\Constants;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\Node;

final class GroupB {
	private const DOC = 'nextcloud-integration.md §2.2, SP-B, SP-C, DEC-IMM / Identitätsmodell';

	/** Referenzmerkmale eines Knotens (nur öffentliche Node-/FileInfo-APIs) */
	public static function ref(Ctx $c, Node $n): array {
		$o = ['id' => $n->getId(), 'path' => $n->getPath(), 'etag' => $n->getEtag(), 'mtime' => $n->getMTime(), 'size' => $n->getSize()];
		$o += $c->storageInfo($n);
		try { $o['parent_id'] = $n->getParent()->getId(); } catch (\Throwable) { $o['parent_id'] = null; }
		return $o;
	}

	/** @return list<CaseDef> */
	public static function cases(): array {
		$d = [];

		$d[] = new CaseDef('B01', 'SP-B', 'Rename im selben Ordner: File-ID und Storage-Referenz',
			'Rename (Node::move im selben Ordner) erhält die File-ID; Storage und Mount bleiben gleich; nur Pfad/internal_path/ETag ändern sich.',
			function (Ctx $c, Rec $r) {
				$w = $c->workdir('B01');
				$content = Ctx::bytes('B01', 3000);
				$f = $c->put($w, 'a.jpg', $content);
				$r->before = $c->snapshot($w);
				$ref0 = self::ref($c, $f);
				$moved = $f->move($w->getPath() . '/b.jpg');
				$ref1 = self::ref($c, $c->root->get($moved->getPath()));
				$r->after = $c->snapshot($w);
				$r->observe('before', $ref0);
				$r->observe('after', $ref1);
				$r->assert($ref0['id'] === $ref1['id'], 'File-ID unverändert');
				$r->assert($ref0['storage_numeric_id'] === $ref1['storage_numeric_id'], 'Storage unverändert');
				$r->assert($ref0['internal_path'] !== $ref1['internal_path'], 'internal_path ändert sich');
				$r->observe('etag_changed', $ref0['etag'] !== $ref1['etag']);
				$r->observe('mtime_changed', $ref0['mtime'] !== $ref1['mtime']);
				$r->assert(($r->after['b.jpg']['sha256'] ?? null) === Ctx::shaStr($content), 'Inhalt unverändert');
			}, false, self::DOC);

		$d[] = new CaseDef('B02', 'SP-B', 'Move in anderen Ordner (inkl. Ordner-Move mit Kindern)',
			'Move in einen anderen Ordner desselben Storage erhält die File-ID; ein Ordner-Move erhält auch die IDs aller Kinder.',
			function (Ctx $c, Rec $r) {
				$w = $c->workdir('B02');
				$a = $w->newFolder('a');
				$b = $w->newFolder('b');
				$f = $c->put($a, 'x.jpg', Ctx::bytes('B02-x', 2000));
				$sub = $a->newFolder('sub');
				$c->put($sub, 'y.jpg', Ctx::bytes('B02-y', 2000));
				$c->put($sub, 'z.jpg', Ctx::bytes('B02-z', 2000));
				$r->before = $c->snapshot($w);
				$idsBefore = array_map(fn($e) => $e['id'], $r->before);
				$f->move($b->getPath() . '/x.jpg');
				$c->root->get($sub->getPath())->move($b->getPath() . '/sub');
				$r->after = $c->snapshot($w);
				$map = [
					'a/x.jpg' => 'b/x.jpg', 'a/sub' => 'b/sub', 'a/sub/y.jpg' => 'b/sub/y.jpg', 'a/sub/z.jpg' => 'b/sub/z.jpg',
				];
				foreach ($map as $from => $to) {
					$r->assert(($r->after[$to]['id'] ?? null) === $idsBefore[$from], "ID von $from bleibt unter $to erhalten", [$idsBefore[$from], $r->after[$to]['id'] ?? null]);
				}
				$r->assert(count($r->after) === count($r->before), 'Anzahl der Einträge gleich');
			}, false, self::DOC);

		$d[] = new CaseDef('B03', 'SP-B', 'Move durch WebDAV-Client (Umbenennen, Verschieben)',
			'WebDAV MOVE erhält die File-ID wie Node::move (Client-Sync-Verhalten).',
			function (Ctx $c, Rec $r) {
				$w = $c->workdir('B03');
				$w->newFolder('dst');
				$f = $c->put($w, 'a.jpg', Ctx::bytes('B03', 2500));
				$id = $f->getId();
				$r->before = $c->snapshot($w);
				$dav = new Dav('alice');
				$rel = 'spike/' . $c->runId . '/B03/';
				$m1 = $dav->move($rel . 'a.jpg', $rel . 'b.jpg', false);
				$m2 = $dav->move($rel . 'b.jpg', $rel . 'dst/c.jpg', false);
				$r->observe('dav', [Dav::brief($m1), Dav::brief($m2)]);
				$r->after = $c->snapshot($w);
				$r->assert(($r->after['dst/c.jpg']['id'] ?? null) === $id, 'File-ID nach zwei WebDAV-Moves erhalten', [$id, $r->after['dst/c.jpg']['id'] ?? null]);
				$r->assert(($r->after['dst/c.jpg']['sha256'] ?? null) === ($r->before['a.jpg']['sha256'] ?? null), 'Inhalt unverändert');
				$r->observe('oc_fileid_header_prefix_matches', str_starts_with((string)($m2['headers']['oc-fileid'] ?? ''), sprintf('%08d', $id)));
			}, false, self::DOC);

		$d[] = new CaseDef('B04', 'SP-B', 'Copy (Node::copy und WebDAV COPY)',
			'Copy erzeugt eine neue File-ID; die Quelle bleibt unverändert.',
			function (Ctx $c, Rec $r) {
				$w = $c->workdir('B04');
				$content = Ctx::bytes('B04', 2000);
				$f = $c->put($w, 'a.jpg', $content);
				$r->before = $c->snapshot($w);
				$copy = $r->call('Node::copy', fn() => $f->copy($w->getPath() . '/copy_node.jpg'));
				$dav = new Dav('alice');
				$rel = 'spike/' . $c->runId . '/B04/';
				$cp = $dav->copy($rel . 'a.jpg', $rel . 'copy_dav.jpg', false);
				$r->observe('dav_copy', Dav::brief($cp));
				$r->after = $c->snapshot($w);
				$r->assert($r->after['a.jpg']['id'] === $r->before['a.jpg']['id'], 'Quell-ID unverändert');
				$r->assert(($r->after['copy_node.jpg']['id'] ?? 0) !== $r->before['a.jpg']['id'], 'Kopie (Node) hat neue ID');
				$r->assert(($r->after['copy_dav.jpg']['id'] ?? 0) !== $r->before['a.jpg']['id'], 'Kopie (WebDAV) hat neue ID');
				$r->assert(($r->after['copy_node.jpg']['sha256'] ?? null) === Ctx::shaStr($content), 'Kopie-Inhalt gleich');
			}, false, self::DOC);

		$d[] = new CaseDef('B05', 'SP-B/SP-J', 'Trash und Restore: File-ID, Pfad, Storage-Referenzen',
			'Beim Löschen in den Papierkorb bleibt die File-ID erhalten (Papierkorbeintrag trägt dieselbe ID); die Wiederherstellung stellt Pfad und ID wieder her.',
			function (Ctx $c, Rec $r) {
				$w = $c->workdir('B05');
				$content = Ctx::bytes('B05', 2200);
				$f = $c->put($w, 'gone.jpg', $content);
				$id = $f->getId();
				$ref0 = self::ref($c, $f);
				$r->before = $c->snapshot($w);
				$dav = new Dav('alice');
				$t0 = time();
				$f->delete();
				$byId = $c->root->getById($id);
				$r->observe('getById_after_delete', array_map(fn($n) => $n->getPath(), $byId));
				$list = $dav->propfind($dav->url('trashbin', 'trash'), 1, ['nc:trashbin-filename', 'nc:trashbin-original-location', 'nc:trashbin-deletion-time'])['entries'];
				$item = null;
				foreach ($list as $href => $e) {
					if (ltrim($e['trashbin-original-location'] ?? '', '/') === 'spike/' . $c->runId . '/B05/gone.jpg') {
						$item = [$href, $e];
					}
				}
				$r->observe('trash_entry', $item);
				if ($item === null) {
					$r->inconclusive('kein Papierkorbeintrag gefunden');
					return;
				}
				$r->assert((int)($item[1]['fileid'] ?? 0) === $id, 'Papierkorbeintrag trägt dieselbe File-ID wie vorher', [$id, $item[1]['fileid'] ?? null]);
				$get = $dav->req('GET', $dav->base() . $item[0]);
				$r->observe('trash_content_get', ['status' => $get['status'], 'sha256' => $get['status'] === 200 ? Ctx::shaStr((string)$get['body']) : null]);
				$r->assert($get['status'] === 200 && Ctx::shaStr((string)$get['body']) === Ctx::shaStr($content), 'Inhalt im Papierkorb per WebDAV lesbar und gleich');
				// Restore
				$rs = $dav->req('MOVE', $dav->base() . $item[0], ['Destination' => $dav->url('trashbin', 'restore/' . basename($item[0]))]);
				$r->observe('restore_response', Dav::brief($rs));
				$r->after = $c->snapshot($w);
				$r->assert(isset($r->after['gone.jpg']), 'Datei nach Restore am Originalpfad', array_keys($r->after));
				$r->assert(($r->after['gone.jpg']['id'] ?? null) === $id, 'File-ID nach Restore unverändert', [$id, $r->after['gone.jpg']['id'] ?? null]);
				$r->assert(($r->after['gone.jpg']['sha256'] ?? null) === Ctx::shaStr($content), 'Inhalt nach Restore unverändert');
				$r->observe('after_restore_ref', isset($r->after['gone.jpg']) ? self::ref($c, $c->root->get($w->getPath() . '/gone.jpg')) : null);
				$r->observe('before_ref', $ref0);
			}, false, self::DOC);

		$d[] = new CaseDef('B06', 'SP-B', 'Inhaltsänderung (gleiche Größe): File-ID, ETag, MTime',
			'Eine Inhaltsänderung erhält die File-ID und ändert ETag (auch bei gleicher Größe), sodass eine Signatur aus ETag/MTime/Größe jede Änderung erkennt.',
			function (Ctx $c, Rec $r) {
				$w = $c->workdir('B06');
				$f = $c->put($w, 'a.jpg', Ctx::bytes('B06-1', 2000));
				$ref0 = self::ref($c, $f);
				$r->before = $c->snapshot($w);
				// (1) Änderung innerhalb derselben Sekunde, gleiche Größe
				$f->putContent(Ctx::bytes('B06-2', 2000));
				$ref1 = self::ref($c, $c->root->get($f->getPath()));
				$sig0 = [$ref0['size'], $ref0['mtime'], $ref0['etag']];
				$sig1 = [$ref1['size'], $ref1['mtime'], $ref1['etag']];
				$sameSecond = $ref0['mtime'] === $ref1['mtime'];
				$r->observe('same_second_change', ['mtime_before_after' => [$ref0['mtime'], $ref1['mtime']], 'signature_before' => $sig0, 'signature_after' => $sig1, 'content_changed' => ($c->sha($c->root->get($f->getPath())) !== Ctx::shaStr(Ctx::bytes('B06-1', 2000)))]);
				$r->assert($ref0['id'] === $ref1['id'], 'File-ID unverändert');
				$r->assert($sig0 !== $sig1, 'Signatur (Größe, MTime, ETag) ändert sich auch bei Änderung in derselben Sekunde mit gleicher Größe', [$sig0, $sig1]);
				if ($sig0 === $sig1) {
					$r->evidence = 'counterexample';
					$r->note('Der Inhalt hat sich geändert, ETag/MTime/Größe nicht: ein Hash-Cache über diese Signatur würde die Änderung übersehen (Granularität Sekunde).');
				}
				// (2) Änderung nach > 1 s
				sleep(2);
				$f2 = $c->root->get($f->getPath());
				$f2->putContent(Ctx::bytes('B06-3', 2000));
				$ref2 = self::ref($c, $c->root->get($f->getPath()));
				$sig2 = [$ref2['size'], $ref2['mtime'], $ref2['etag']];
				$r->observe('change_after_2s', ['signature_after' => $sig2]);
				$r->assert($sig1 !== $sig2, 'nach >1 s ändert sich die Signatur', [$sig1, $sig2]);
				$r->after = $c->snapshot($w);
			}, false, self::DOC);

		$d[] = new CaseDef('B07', 'SP-B/SP-C', 'Move über Storage-Grenzen (Heim-Storage → Freigabe-Storage eines anderen Benutzers)',
			'Ein Move über Storage-Grenzen erhält die File-ID nicht (Kopie + Löschen); bestätigt die Beschränkung auf den Heim-Storage (REQ-SRC-06). Gilt nur für diese Storage-Kombination.',
			function (Ctx $c, Rec $r) {
				$bobW = $c->workdir('B07', 'bob', 'share' . $c->runId);
				$aliceW = $c->workdir('B07', 'alice');
				$content = Ctx::bytes('B07', 2000);
				$f = $c->put($aliceW, 'moveme.jpg', $content);
				$id = $f->getId();
				$mgr = \OCP\Server::get(\OCP\Share\IManager::class);
				$share = $mgr->newShare();
				$share->setNode($bobW)->setShareType(\OCP\Share\IShare::TYPE_USER)->setSharedWith('alice')->setSharedBy('bob')->setShareOwner('bob')->setPermissions(Constants::PERMISSION_ALL & ~Constants::PERMISSION_SHARE);
				try {
					$share = $mgr->createShare($share);
				} catch (\Throwable $e) {
					$r->inconclusive('Freigabe nicht erstellbar: ' . $e->getMessage());
					return;
				}
				$mgr->acceptShare($share, 'alice');
				$target = $share->getTarget(); // z. B. /shared
				$r->observe('share_target', $target);
				$r->before = $c->snapshot($aliceW);
				$p = Proc::race('B07', ['x' => [['off' => 0.0, 'op' => ['type' => 'move', 'src' => $f->getPath(), 'dst' => '/alice/files' . $target . '/moveme.jpg']]]]);
				$res = $p['children']['x']['results'][0]['res'] ?? null;
				$r->observe('child_result', $res);
				if (!$res || !$res['ok']) {
					$r->inconclusive('Move in die Freigabe nicht ausgeführt/möglich (Freigabe im Kindprozess nicht sichtbar?)');
					return;
				}
				$new = $res['ret'];
				$r->observe('id_before', $id);
				$r->observe('id_after', $new['id']);
				$bobSnap = $c->snapshot($bobW);
				$r->observe('bob_side', array_map(fn($e) => ['id' => $e['id'], 'sha256' => $e['sha256'] ?? null], $bobSnap));
				$r->assert($new['id'] !== $id, 'File-ID ändert sich über die Storage-Grenze (Erwartung der Architektur)', [$id, $new['id']]);
				$r->assert(($bobSnap['moveme.jpg']['sha256'] ?? null) === Ctx::shaStr($content), 'Inhalt auf der Zielseite gleich');
				$r->after = $c->snapshot($aliceW);
				$r->note('Storage-Klassen: Quelle ' . ($c->env['storage_class'] ?? '?') . ' → Ziel Freigabe-Storage (OCA\\Files_Sharing\\SharedStorage über Jail); nur diese Kombination geprüft.');
			}, false, self::DOC);

		return $d;
	}
}

<?php
// WEGWERF-TESTCODE (Nextcloud-API-Spikes, Auftrag 5). Gruppe D: Papierkorb (nur reversible Operationen).
declare(strict_types=1);

namespace OCA\PoSpikeProbe\Cases;

use OCA\PoSpikeProbe\Support\CaseDef;
use OCA\PoSpikeProbe\Support\Ctx;
use OCA\PoSpikeProbe\Support\Dav;
use OCA\PoSpikeProbe\Support\Rec;
use OCP\App\IAppManager;
use OCP\Server;

final class GroupD {
	private const DOC = 'job-processing.md §6.4, DEC-DUP-010, SP-J, trash_verified';

	/** @return list<array{0:string,1:array}> href und Eigenschaften der Papierkorbeinträge zu einem Originalpfad (relativ zum Benutzerordner) */
	public static function items(Dav $dav, string $originalRel): array {
		$list = $dav->propfind($dav->url('trashbin', 'trash'), 1, ['nc:trashbin-filename', 'nc:trashbin-original-location', 'nc:trashbin-deletion-time'])['entries'];
		$out = [];
		foreach ($list as $href => $e) {
			if (ltrim($e['trashbin-original-location'] ?? '', '/') === ltrim($originalRel, '/')) {
				$out[] = [$href, $e];
			}
		}
		usort($out, fn($a, $b) => strcmp($a[0], $b[0]));
		return $out;
	}

	/** @return list<CaseDef> */
	public static function cases(): array {
		$d = [];

		$d[] = new CaseDef('D01', 'SP-J', 'Node::delete() verwendet den Nextcloud-Papierkorb; Identität, Erreichbarkeit und Inhalt des gelöschten Knotens',
			'Node::delete() verschiebt in den Papierkorb (nicht endgültig). Der Eintrag ist auffindbar (WebDAV und IRootFolder per File-ID), trägt dieselbe File-ID und denselben Inhalt.',
			function (Ctx $c, Rec $r) {
				$w = $c->workdir('D01');
				$content = Ctx::bytes('D01', 4096);
				$f = $c->put($w, 'photo.jpg', $content);
				$id = $f->getId();
				$rel = 'spike/' . $c->runId . '/D01/photo.jpg';
				$r->before = $c->snapshot($w);
				$dav = new Dav('alice');
				$t0 = time();
				$del = $r->call('Node::delete()', fn() => $f->delete());
				$r->after = $c->snapshot($w);
				$r->assert($del['ok'] && $r->after === [], 'Datei am Originalpfad nicht mehr vorhanden', array_keys($r->after));
				$byId = $c->root->getById($id);
				$r->observe('getById_paths_after_delete', array_map(fn($n) => $n->getPath(), $byId));
				$r->assert(count($byId) === 1 && str_contains($byId[0]->getPath(), '/files_trashbin/'), 'File-ID löst über IRootFolder auf einen Knoten im Papierkorbbereich auf');
				$r->observe('hash_via_root_folder', isset($byId[0]) ? $c->sha($byId[0]) : null);
				$r->assert(isset($byId[0]) && $c->sha($byId[0]) === Ctx::shaStr($content), 'Inhalt des Papierkorb-Knotens (IRootFolder) = Inhalt vor dem Löschen');
				$it = self::items($dav, $rel);
				$r->observe('trash_entries', $it);
				$r->assert(count($it) === 1, 'genau ein Papierkorbeintrag über WebDAV zum Originalpfad', count($it));
				if (count($it) === 1) {
					$r->assert((int)$it[0][1]['fileid'] === $id, 'Papierkorbeintrag trägt dieselbe File-ID', [$id, $it[0][1]['fileid']]);
					$r->assert((int)$it[0][1]['trashbin-deletion-time'] >= $t0 - 1 && (int)$it[0][1]['trashbin-deletion-time'] <= time() + 1, 'Löschzeit plausibel (innerhalb des Testfensters)', $it[0][1]['trashbin-deletion-time']);
					$g = $dav->req('GET', $dav->base() . $it[0][0]);
					$r->assert($g['status'] === 200 && Ctx::shaStr((string)$g['body']) === Ctx::shaStr($content), 'Inhalt per WebDAV-GET im Papierkorb = Inhalt vor dem Löschen', $g['status']);
				}
			}, false, self::DOC);

		$d[] = new CaseDef('D02', 'SP-J', 'Nachweis einer Papierkorboperation (Pre-Delete-Hash beider Exemplare, Post-Beobachtung)',
			'Nach dem Löschen lässt sich allein über öffentliche APIs belegen: Duplikat nicht mehr am Ort, Papierkorbeintrag mit derselben File-ID und gleichem Hash vorhanden, kanonische Datei unverändert.',
			function (Ctx $c, Rec $r) {
				$w = $c->workdir('D02');
				$content = Ctx::bytes('D02', 6000);
				$canon = $c->put($w, 'canonical.jpg', $content);
				$dupe = $c->put($w, 'dupe.jpg', $content);
				$dupId = $dupe->getId();
				$canonId = $canon->getId();
				$r->before = $c->snapshot($w);
				// P-Pre: beide Exemplare frisch hashen
				$preDup = $c->sha($c->root->get($dupe->getPath()));
				$preCan = $c->sha($c->root->get($canon->getPath()));
				$sigCan = $r->before['canonical.jpg'];
				$r->assert($preDup === $preCan && $preDup === Ctx::shaStr($content), 'Pre-Delete: beide Exemplare neu gehasht und gleich');
				$c->root->get($dupe->getPath())->delete();
				$r->after = $c->snapshot($w);
				// Post-Beobachtung
				$gone = !isset($r->after['dupe.jpg']);
				$canonSame = isset($r->after['canonical.jpg']) && $r->after['canonical.jpg']['sha256'] === $sigCan['sha256'] && $r->after['canonical.jpg']['id'] === $canonId && $r->after['canonical.jpg']['etag'] === $sigCan['etag'];
				$byId = $c->root->getById($dupId);
				$inTrash = count($byId) === 1 && str_contains($byId[0]->getPath(), '/files_trashbin/files/') && $c->sha($byId[0]) === $preDup;
				$dav = new Dav('alice');
				$it = self::items($dav, 'spike/' . $c->runId . '/D02/dupe.jpg');
				$r->observe('post_checks', ['duplicate_gone' => $gone, 'canonical_unchanged' => $canonSame, 'trash_node_by_id_hash_equal' => $inTrash, 'webdav_trash_entries' => count($it)]);
				$r->assert($gone, 'Duplikat nicht mehr am Ort');
				$r->assert($canonSame, 'kanonische Datei unverändert (ID, Hash, ETag)');
				$r->assert($inTrash, 'Papierkorb-Knoten per File-ID auffindbar, Hash gleich Pre-Delete-Hash');
				$r->assert(count($it) === 1 && (int)$it[0][1]['fileid'] === $dupId, 'WebDAV-Papierkorbeintrag mit identischer File-ID');
				$r->note('Der Nachweis beruht auf der Identität über die File-ID und dem Hash des Papierkorb-Knotens; er belegt, dass der Inhalt erhalten ist, nicht dass er unbegrenzt erhalten bleibt (Retention).');
			}, false, self::DOC);

		$d[] = new CaseDef('D03', 'SP-J', 'Zwei Löschungen gleichnamiger Dateien am selben Pfad kurz nacheinander (gleiche Sekunde) und mit 2 s Abstand',
			'Zwei Papierkorbeinträge zum gleichen Originalpfad bleiben unterscheidbar und beide Inhalte erhalten – auch wenn sie in derselben Sekunde entstehen (Eintragsname trägt nur einen Sekundenzeitstempel).',
			function (Ctx $c, Rec $r) {
				$dav = new Dav('alice');
				foreach (['same_second' => 0, 'two_seconds_apart' => 2] as $mode => $gap) {
					$w = $c->workdir('D03' . $mode);
					$cA = Ctx::bytes("D03-$mode-A", 1500);
					$cB = Ctx::bytes("D03-$mode-B", 1500);
					$a = $c->put($w, 'same.jpg', $cA);
					$idA = $a->getId();
					$tA = time();
					$a->delete();
					$tA2 = time();
					if ($gap) {
						sleep($gap);
					}
					$b = $c->put($w, 'same.jpg', $cB);
					$idB = $b->getId();
					$tB0 = time();
					$b->delete();
					$tB = time();
					$it = self::items($dav, 'spike/' . $c->runId . '/D03' . $mode . '/same.jpg');
					$hashes = [];
					foreach ($it as $x) {
						$g = $dav->req('GET', $dav->base() . $x[0]);
						$hashes[(int)$x[1]['fileid']] = Ctx::shaStr((string)$g['body']);
					}
					$byIdA = $c->root->getById($idA);
					$byIdB = $c->root->getById($idB);
					$r->observe($mode, [
						'ids' => ['A' => $idA, 'B' => $idB],
						'delete_A_second' => [$tA, $tA2], 'delete_B_second' => [$tB0, $tB],
						'trash_entries' => array_map(fn($x) => [basename($x[0]), $x[1]['fileid'] ?? null, $x[1]['trashbin-deletion-time'] ?? null], $it),
						'content_A_reachable_by_id' => $byIdA ? array_map(fn($n) => $n->getPath(), $byIdA) : [],
						'content_B_reachable_by_id' => $byIdB ? array_map(fn($n) => $n->getPath(), $byIdB) : [],
						'hashes_via_webdav' => $hashes,
					]);
					$sameSecond = ($tA2 === $tB0);
					if ($mode === 'same_second' && !$sameSecond) {
						$r->inconclusive("$mode: die beiden Löschungen lagen nicht in derselben Sekunde ($tA2 vs. $tB0) – wiederholen");
						continue;
					}
					$bothHeld = isset($hashes[$idA], $hashes[$idB]) && $hashes[$idA] === Ctx::shaStr($cA) && $hashes[$idB] === Ctx::shaStr($cB);
					$r->assert(count($it) === 2 && $bothHeld, "$mode: zwei getrennte Papierkorbeinträge, beide Inhalte über ihre File-ID erhalten", ['entries' => count($it), 'hashes' => $hashes]);
					if (count($it) < 2) {
						$r->evidence = 'counterexample';
						$r->note("$mode: ein Papierkorbeintrag fehlt – der später gelöschte Inhalt hat den früheren ersetzt (Eintragsname = Name + Sekundenzeitstempel).");
					}
				}
			}, false, self::DOC);

		$d[] = new CaseDef('D04', 'SP-J', 'Restore bei inzwischen vorhandener Zieldatei',
			'Die Wiederherstellung überschreibt eine inzwischen am Originalpfad vorhandene Datei nicht.',
			function (Ctx $c, Rec $r) {
				$w = $c->workdir('D04');
				$cOld = Ctx::bytes('D04-old', 1800);
				$cNew = Ctx::bytes('D04-new', 1800);
				$a = $c->put($w, 'x.jpg', $cOld);
				$idOld = $a->getId();
				$a->delete();
				$b = $c->put($w, 'x.jpg', $cNew);
				$idNew = $b->getId();
				$r->before = $c->snapshot($w);
				$dav = new Dav('alice');
				$it = self::items($dav, 'spike/' . $c->runId . '/D04/x.jpg');
				if (count($it) !== 1) {
					$r->inconclusive('Papierkorbeintrag nicht eindeutig');
					return;
				}
				$rs = $dav->req('MOVE', $dav->base() . $it[0][0], ['Destination' => $dav->url('trashbin', 'restore/' . basename($it[0][0]))]);
				$r->observe('restore_response', Dav::brief($rs));
				$r->after = $c->snapshot($w);
				$r->observe('names_after', array_keys($r->after));
				$r->assert(($r->after['x.jpg']['sha256'] ?? null) === Ctx::shaStr($cNew) && ($r->after['x.jpg']['id'] ?? null) === $idNew, 'die inzwischen vorhandene Datei x.jpg ist unverändert');
				$restored = array_filter($r->after, fn($e, $k) => $k !== 'x.jpg' && ($e['sha256'] ?? null) === Ctx::shaStr($cOld), ARRAY_FILTER_USE_BOTH);
				$r->observe('restored_under', array_keys($restored));
				$r->assert(count($restored) === 1 || $rs['status'] >= 400, 'wiederhergestellter Inhalt unter anderem Namen oder Restore abgelehnt (kein Überschreiben)', array_keys($r->after));
				$r->observe('restored_id_equals_original', count($restored) === 1 ? (array_values($restored)[0]['id'] === $idOld) : null);
			}, false, self::DOC);

		$d[] = new CaseDef('D05', 'SP-J', 'Löschen per WebDAV DELETE und Löschen eines Ordners',
			'WebDAV DELETE und Ordner-Löschung führen ebenfalls in den Papierkorb; File-IDs der Kinder bleiben erhalten.',
			function (Ctx $c, Rec $r) {
				$w = $c->workdir('D05');
				$cA = Ctx::bytes('D05-a', 1000);
				$f = $c->put($w, 'viadav.jpg', $cA);
				$idF = $f->getId();
				$sub = $w->newFolder('folder');
				$k1 = $c->put($sub, 'k1.jpg', Ctx::bytes('D05-k1', 700));
				$k2 = $c->put($sub, 'k2.jpg', Ctx::bytes('D05-k2', 700));
				$ids = [$k1->getId(), $k2->getId()];
				$dav = new Dav('alice');
				$rel = 'spike/' . $c->runId . '/D05/';
				$r->before = $c->snapshot($w);
				$dl = $dav->delete($rel . 'viadav.jpg');
				$r->observe('dav_delete', Dav::brief($dl));
				$c->root->get($sub->getPath())->delete();
				$r->after = $c->snapshot($w);
				$byIdFile = $c->root->getById($idF);
				$r->assert(count($byIdFile) === 1 && str_contains($byIdFile[0]->getPath(), '/files_trashbin/'), 'WebDAV-DELETE: Knoten per File-ID im Papierkorbbereich');
				$kids = [];
				foreach ($ids as $kid) {
					$n = $c->root->getById($kid);
					$kids[$kid] = array_map(fn($x) => $x->getPath(), $n);
				}
				$r->observe('children_by_id_after_folder_delete', $kids);
				$r->assert(count(array_filter($kids, fn($p) => count($p) === 1)) === 2, 'Kinder des gelöschten Ordners sind per File-ID im Papierkorbbereich auffindbar');
				$it = self::items($dav, $rel . 'folder');
				$r->observe('folder_trash_entries', count($it));
			}, false, self::DOC);

		$d[] = new CaseDef('D06', 'SP-J', 'Fähigkeitserkennung trash_verified (nicht destruktiv)',
			'Der Papierkorb-Betrieb ist ohne Löschversuch erkennbar: App files_trashbin aktiv und Papierkorb-Storage-Wrapper im Heim-Storage; die Prüfung allein beweist noch nicht, dass jede Löschung im Papierkorb landet.',
			function (Ctx $c, Rec $r) {
				$apps = Server::get(IAppManager::class);
				$enabled = $apps->isEnabledForAnyone('files_trashbin');
				$chain = $c->env['storage_wrapper_chain'] ?? [];
				$r->observe('files_trashbin_enabled', $enabled);
				$r->observe('storage_chain_contains_trashbin_wrapper', in_array('OCA\\Files_Trashbin\\Storage', $chain, true));
				$r->observe('trashbin_retention_obligation', Server::get(\OCP\IConfig::class)->getSystemValue('trashbin_retention_obligation', 'auto (Standard)'));
				$r->assert($enabled && in_array('OCA\\Files_Trashbin\\Storage', $chain, true), 'Papierkorb aktiv und im Storage-Stack erkennbar');
				$r->note('Der Fall „Papierkorb deaktiviert → Node::delete löscht endgültig“ wurde bewusst NICHT experimentell geprüft (Auftrag 5: keine dauerhaft irreversible Löschung im Harness); er folgt nur aus dem Quelltext (Trash-Wrapper entfällt, Local::unlink löscht direkt).');
				$r->evidence = 'static_analysis';
			}, false, self::DOC);

		return $d;
	}
}

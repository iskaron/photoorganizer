<?php
// WEGWERF-TESTCODE (Nextcloud-API-Spikes, Auftrag 5). Gruppe A: Move und Zielkollisionen.
declare(strict_types=1);

namespace OCA\PoSpikeProbe\Cases;

use OCA\PoSpikeProbe\Support\CaseDef;
use OCA\PoSpikeProbe\Support\Ctx;
use OCA\PoSpikeProbe\Support\Dav;
use OCA\PoSpikeProbe\Support\Proc;
use OCA\PoSpikeProbe\Support\Rec;
use OCP\Files\File;
use OCP\Files\Folder;

final class GroupA {
	private const DOC = 'job-processing.md §6.3, DEC-ARC-010, OQ-069';

	/** @return list<CaseDef> */
	public static function cases(): array {
		$d = [];
		$H_NOOVER = 'Move auf einen vorhandenen Zielpfad scheitert mit Ausnahme und lässt Ziel und Quelle unverändert (Voraussetzung für move_no_overwrite).';

		// ---------------------------------------------------------------- A01
		$d[] = new CaseDef('A01', 'SP-A', 'Einfacher Move/Rename auf demselben Storage',
			'Move ohne vorhandenes Ziel verschiebt den Knoten unverändert (Inhalt, Anzahl) und liefert den Zielknoten.',
			function (Ctx $c, Rec $r) {
				$w = $c->workdir('A01');
				$a = $w->newFolder('a');
				$b = $w->newFolder('b');
				$content = Ctx::bytes('A01', 4096);
				$f = $c->put($a, 'photo.jpg', $content);
				$r->before = $c->snapshot($w);
				$r->expect('result', 'a/photo.jpg → b/photo.jpg; Inhalt gleich; Quelle weg; Dateianzahl 1');
				$res = $r->call('Node::move(b/photo.jpg)', fn() => $f->move($b->getPath() . '/photo.jpg'));
				$r->after = $c->snapshot($w);
				$r->assert($res['ok'], 'Move ohne Ausnahme', $res['exc']);
				$r->assert(isset($r->after['b/photo.jpg']) && !isset($r->after['a/photo.jpg']), 'Ziel vorhanden, Quelle weg', array_keys($r->after));
				$r->assert(($r->after['b/photo.jpg']['sha256'] ?? null) === Ctx::shaStr($content), 'SHA-256 unverändert');
				$r->assert(count(array_filter($r->after, fn($e) => $e['type'] === 'file')) === 1, 'genau eine Datei');
				$r->observe('storage', $c->storageInfo($c->root->get($b->getPath() . '/photo.jpg')));
			}, false, self::DOC);

		// ---------------------------------------------------------------- A02 / A03
		foreach ([['A02', 'gleichem', true], ['A03', 'anderem', false]] as [$id, $label, $same]) {
			$d[] = new CaseDef($id, 'SP-A1', "Ziel existiert bereits (Datei) mit $label Inhalt",
				$H_NOOVER,
				function (Ctx $c, Rec $r) use ($id, $same) {
					$w = $c->workdir($id);
					$srcContent = Ctx::bytes("$id-src", 3000);
					$tgtContent = $same ? $srcContent : Ctx::bytes("$id-tgt", 3000);
					$src = $c->put($w, 'src.jpg', $srcContent);
					$tgt = $c->put($w, 'target.jpg', $tgtContent);
					$tgtIdBefore = $tgt->getId();
					$dav = new Dav('alice');
					$trashBefore = array_keys($dav->propfind($dav->url('trashbin', 'trash'), 1)['entries']);
					$r->before = $c->snapshot($w);
					$r->expect('result', 'Ausnahme; Ziel (id ' . $tgtIdBefore . ') und Quelle unverändert');
					$res = $r->call('Node::move(target.jpg)', fn() => $src->move($w->getPath() . '/target.jpg'));
					$r->after = $c->snapshot($w);
					$trashAfter = array_keys($dav->propfind($dav->url('trashbin', 'trash'), 1)['entries']);
					$newTrash = array_values(array_diff($trashAfter, $trashBefore));
					$r->observe('overwritten_target_in_trash', $newTrash);
					$r->observe('target_after', $r->after['target.jpg'] ?? null);
					$r->observe('source_present_after', isset($r->after['src.jpg']));
					$r->observe('old_target_file_id_still_exists', ($c->root->getById($tgtIdBefore)[0] ?? null) !== null);
					$r->assert(!$res['ok'], 'Move scheitert mit Ausnahme', $res);
					$r->assert(isset($r->after['src.jpg']) && ($r->after['src.jpg']['sha256'] ?? null) === Ctx::shaStr($srcContent), 'Quelle unverändert vorhanden');
					$r->assert(($r->after['target.jpg']['id'] ?? null) === $tgtIdBefore && ($r->after['target.jpg']['sha256'] ?? null) === Ctx::shaStr($tgtContent), 'Ziel unverändert (ID und Inhalt)');
					if ($res['ok']) {
						$r->evidence = 'counterexample';
						$r->note('Das vorhandene Ziel wurde ersetzt (alte File-ID existiert nicht mehr, kein Papierkorbeintrag, siehe overwritten_target_in_trash); ' . ($same ? 'bei gleichem Inhalt ist dennoch die Identität des alten Ziels verloren.' : 'der Inhalt des alten Ziels ist nicht mehr auffindbar.'));
					}
				}, false, self::DOC);
		}

		// ---------------------------------------------------------------- A04
		$d[] = new CaseDef('A04', 'SP-A2', 'Ziel existiert als Ordner (nicht leer) / Ordner auf Datei',
			'Move einer Datei auf einen vorhandenen Ordnerpfad scheitert und lässt den Ordner samt Inhalt unverändert; ebenso Ordner auf Datei.',
			function (Ctx $c, Rec $r) {
				$w = $c->workdir('A04');
				$src = $c->put($w, 'src.jpg', Ctx::bytes('A04-src', 1500));
				$dir = $w->newFolder('target.jpg');
				$inner = $c->put($dir, 'inner1.bin', Ctx::bytes('A04-in1', 900));
				$c->put($dir, 'inner2.bin', Ctx::bytes('A04-in2', 900));
				$other = $w->newFolder('folder_src');
				$c->put($other, 'x.bin', Ctx::bytes('A04-x', 500));
				$fileTarget = $c->put($w, 'file_target.txt', Ctx::bytes('A04-ft', 700));
				$r->before = $c->snapshot($w);
				$r->expect('result', 'beide Moves scheitern; Ordner target.jpg mit zwei Dateien und file_target.txt unverändert');
				$res1 = $r->call('Datei → Ordnerpfad', fn() => $src->move($w->getPath() . '/target.jpg'));
				$after1 = $c->snapshot($w);
				$res2 = $r->call('Ordner → Dateipfad', fn() => $c->root->get($w->getPath() . '/folder_src')->move($w->getPath() . '/file_target.txt'));
				$r->after = $c->snapshot($w);
				$r->assert(!$res1['ok'], 'Datei→Ordnerpfad scheitert', $res1['ret'] ?? $res1['exc']);
				$r->assert(isset($r->after['target.jpg/inner1.bin'], $r->after['target.jpg/inner2.bin']), 'Inhalt des Zielordners erhalten', array_keys($r->after));
				$r->assert(!$res2['ok'], 'Ordner→Dateipfad scheitert', $res2['ret'] ?? $res2['exc']);
				$r->assert(isset($r->after['folder_src/x.bin']) && isset($r->after['file_target.txt']) && ($r->after['file_target.txt']['type'] ?? '') === 'file', 'Quellordner und Zieldatei erhalten');
				if ($res1['ok'] || $res2['ok']) {
					$r->evidence = 'counterexample';
				}
				$r->observe('snapshot_after_first_call', array_keys($after1));
				$r->observe('snapshot_after_second_call', array_keys($r->after));
			}, false, self::DOC);

		// ---------------------------------------------------------------- A05
		$d[] = new CaseDef('A05', 'SP-A4', 'Quelle = Ziel, Groß-/Kleinschreibung, Unicode-Normalisierung',
			'Move auf den eigenen Pfad ist wirkungslos oder scheitert, ohne den Inhalt zu zerstören; Namensvarianten (Groß-/Kleinschreibung, NFC/NFD) werden nicht stillschweigend zusammengeführt.',
			function (Ctx $c, Rec $r) {
				$w = $c->workdir('A05');
				$content = Ctx::bytes('A05', 2000);
				$f = $c->put($w, 'same.jpg', $content);
				$r->before = $c->snapshot($w);
				$r->expect('same_path', 'Datei same.jpg danach noch vorhanden mit gleichem Inhalt');
				$res = $r->call('Node::move(eigener Pfad)', fn() => $f->move($w->getPath() . '/same.jpg'));
				$snap = $c->snapshot($w);
				$r->observe('same_path_exists_after', isset($snap['same.jpg']));
				$r->observe('same_path_sha_after', $snap['same.jpg']['sha256'] ?? null);
				$r->assert(isset($snap['same.jpg']) && ($snap['same.jpg']['sha256'] ?? null) === Ctx::shaStr($content), 'Datei nach Move auf eigenen Pfad intakt', [$res['ret'] ?? $res['exc']]);
				// Groß-/Kleinschreibung
				$g = $c->put($w, 'Case.JPG', Ctx::bytes('A05-case', 800));
				$res2 = $r->call('Case.JPG → case.jpg', fn() => $g->move($w->getPath() . '/case.jpg'));
				$snap2 = $c->snapshot($w);
				$r->observe('case_rename_names', array_values(array_filter(array_keys($snap2), fn($k) => strtolower($k) === 'case.jpg')));
				$r->assert(count(array_filter(array_keys($snap2), fn($k) => strtolower($k) === 'case.jpg')) === 1, 'genau eine case.jpg-Variante nach Rename');
				// Unicode: NFC-Datei vorhanden, anderes Objekt auf NFD-Namen verschieben
				$nfc = "\u{00E9}.jpg";
				$nfd = "e\u{0301}.jpg";
				$h = $c->put($w, $nfc, Ctx::bytes('A05-nfc', 600));
				$k = $c->put($w, 'other.jpg', Ctx::bytes('A05-other', 600));
				$res3 = $r->call('other.jpg → NFD-Name', fn() => $k->move($w->getPath() . '/' . $nfd));
				$snap3 = $c->snapshot($w);
				$uni = array_values(array_filter(array_keys($snap3), fn($x) => str_contains($x, "\u{0301}") || str_contains($x, "\u{00E9}")));
				$r->observe('unicode_names_after', array_map(fn($x) => bin2hex($x), $uni));
				$r->observe('unicode_move_result', $res3['ok'] ? 'ok' : $res3['exc']);
				$r->assert(count($uni) === 2 || !$res3['ok'], 'NFC- und NFD-Name bleiben zwei Dateien oder die Operation scheitert (keine stille Zusammenführung)', $uni);
				$r->after = $snap3;
				if ($res3['ok'] && count($uni) < 2) {
					$r->evidence = 'counterexample';
					$r->note('Der NFD-Zielname wurde auf den NFC-Namen normalisiert und hat die vorhandene Datei ersetzt (stille Zusammenführung zweier Namensvarianten).');
				}
			}, false, self::DOC);

		// ---------------------------------------------------------------- A06
		$d[] = new CaseDef('A06', 'SP-A5', 'Verspäteter zweiter Aufruf mit veraltetem Knoten',
			'Ein zweiter Worker mit einem vor dem ersten Move geladenen Knoten kann nichts mehr verschieben, auch keine andere Datei, die inzwischen am alten Pfad liegt.',
			function (Ctx $c, Rec $r) {
				$w = $c->workdir('A06');
				$contentA = Ctx::bytes('A06-a', 1800);
				$contentB = Ctx::bytes('A06-b', 1800);
				$orig = $c->put($w, 'orig.jpg', $contentA);
				$id = $orig->getId();
				$worker1 = $c->root->get($orig->getPath());
				$worker2 = $c->root->get($orig->getPath());
				$r->before = $c->snapshot($w);
				$r->expect('step1', 'Worker 1 verschiebt orig.jpg → w1.jpg');
				$m1 = $r->call('Worker 1 move → w1.jpg', fn() => $worker1->move($w->getPath() . '/w1.jpg'));
				$r->assert($m1['ok'], 'Worker 1 erfolgreich');
				// Variante a: Quelle fehlt
				$m2 = $r->call('Worker 2 (veralteter Knoten) move → w2.jpg, Quelle fehlt', fn() => $worker2->move($w->getPath() . '/w2.jpg'));
				$r->assert(!$m2['ok'], 'veralteter Aufruf scheitert, wenn Quelle fehlt', $m2['ret'] ?? $m2['exc']);
				// Variante b: am alten Pfad liegt inzwischen eine ANDERE Datei
				$intruder = $c->put($w, 'orig.jpg', $contentB);
				$worker3 = $c->root->get($w->getPath() . '/w1.jpg'); // zweiter Worker, geladen vor dem Austausch
				$staleBefore = $c->root->getById($id)[0] ?? null;
				$r->observe('file_id_of_w1_is', $id);
				$worker4Path = $w->getPath() . '/orig.jpg';
				// Worker 2 hält weiterhin den Pfad orig.jpg
				$m3 = $r->call('Worker 2 (veraltet, Pfad orig.jpg) move → w3.jpg, am alten Pfad liegt jetzt eine andere Datei', fn() => $worker2->move($w->getPath() . '/w3.jpg'));
				$after = $c->snapshot($w);
				$r->after = $after;
				$r->observe('intruder_file_id', $intruder->getId());
				$r->observe('after_paths', array_keys($after));
				$movedWrong = $m3['ok'] && isset($after['w3.jpg']) && ($after['w3.jpg']['sha256'] ?? null) === Ctx::shaStr($contentB);
				$r->observe('stale_worker_moved_foreign_file', $movedWrong);
				$r->assert(!$movedWrong, 'veralteter Worker verschiebt NICHT die inzwischen am alten Pfad liegende fremde Datei', $m3['ret'] ?? $m3['exc']);
				if ($movedWrong) {
					$r->evidence = 'counterexample';
					$r->note('Node::move arbeitet auf dem gespeicherten Pfad, nicht auf der File-ID: ein veralteter Knoten verschiebt, was jetzt am alten Pfad liegt. move_source_by_path ist nur durch Prüfung von Pfad UND File-ID unmittelbar vor dem Aufruf herstellbar (Restfenster, siehe A21/A22).');
				}
			}, false, self::DOC);

		// ---------------------------------------------------------------- A07
		$d[] = new CaseDef('A07', 'SP-A6', 'Fehlerfälle: Zielordner fehlt, ungültiger Name, Quelle fehlt',
			'Jeder dieser Fehlerfälle endet mit einer Ausnahme und lässt den Bestand unverändert.',
			function (Ctx $c, Rec $r) {
				$w = $c->workdir('A07');
				$content = Ctx::bytes('A07', 1000);
				$f = $c->put($w, 'src.jpg', $content);
				$r->before = $c->snapshot($w);
				$cases = [
					'zielordner_fehlt' => $w->getPath() . '/gibtesnicht/x.jpg',
					'name_mit_doppelpunkt_backslash' => $w->getPath() . '/a\\b.jpg',
					'name_zu_lang_300' => $w->getPath() . '/' . str_repeat('x', 300) . '.jpg',
					'name_htaccess' => $w->getPath() . '/.htaccess',
				];
				$informational = ['name_mit_leerzeichen_am_ende' => $w->getPath() . '/trailing. '];
				foreach ($cases as $label => $target) {
					$res = $r->call("move → $label", fn() => $f->move($target));
					$snap = $c->snapshot($w);
					$intact = isset($snap['src.jpg']) && ($snap['src.jpg']['sha256'] ?? null) === Ctx::shaStr($content) && count($snap) === 1;
					$r->assert(!$res['ok'] && $intact, "$label: Ausnahme und Bestand unverändert", ['ok' => $res['ok'], 'exc' => $res['exc'], 'paths' => array_keys($snap)]);
					if ($res['ok']) {
						// zurückräumen, damit die folgenden Fälle auswertbar bleiben
						$f = $c->root->get($res['raw']->getPath());
						$f->move($w->getPath() . '/src.jpg');
					}
				}
				foreach ($informational as $label => $target) {
					$res = $r->call("move → $label (nur beschreibend)", fn() => $f->move($target));
					$r->observe($label, $res['ok'] ? 'akzeptiert' : $res['exc']);
					if ($res['ok']) {
						$f = $c->root->get($res['raw']->getPath());
						$f->move($w->getPath() . '/src.jpg');
					}
				}
				$ghost = $c->root->get($w->getPath() . '/src.jpg');
				$ghost->delete();
				$res = $r->call('move einer gelöschten Quelle', fn() => $ghost->move($w->getPath() . '/after_delete.jpg'));
				$snap = $c->snapshot($w);
				$r->assert(!$res['ok'] && !isset($snap['after_delete.jpg']), 'gelöschte Quelle: Ausnahme, kein Ziel entstanden', $res['exc']);
				$r->after = $snap;
			}, false, self::DOC);

		// ---------------------------------------------------------------- A08 (SP-I)
		$d[] = new CaseDef('A08', 'SP-I', 'Move eines zuvor geladenen, inzwischen umbenannten Knotens (folgt er der File-ID?)',
			'Ein vor einer fremden Umbenennung geladener Knoten scheitert beim Move oder folgt der File-ID – er verschiebt nie eine andere Datei.',
			function (Ctx $c, Rec $r) {
				$w = $c->workdir('A08');
				$content = Ctx::bytes('A08', 1700);
				$orig = $c->put($w, 'before.jpg', $content);
				$id = $orig->getId();
				$loaded = $c->root->get($orig->getPath()); // „Job-Zeitpunkt“
				$r->before = $c->snapshot($w);
				// Fremder Benutzer benennt per WebDAV um (anderer Prozesskontext/Request)
				$dav = new Dav('alice');
				$rel = 'spike/' . $c->runId . '/A08/';
				$mv = $dav->move($rel . 'before.jpg', $rel . 'renamed_by_user.jpg', false);
				$r->observe('webdav_rename', Dav::brief($mv));
				$r->assert($mv['status'] >= 200 && $mv['status'] < 300, 'WebDAV-Umbenennung erfolgreich (Testvoraussetzung)');
				$r->observe('stale_node_getPath_after_external_rename', $loaded->getPath());
				$r->observe('stale_node_getId', $loaded->getId());
				$res = $r->call('veralteter Knoten → Node::move(final.jpg)', fn() => $loaded->move($w->getPath() . '/final.jpg'));
				$after = $c->snapshot($w);
				$r->after = $after;
				$followed = isset($after['final.jpg']) && $after['final.jpg']['id'] === $id;
				$r->observe('result', $res['ok'] ? ($followed ? 'followed_file_id' : 'moved_something_else') : 'failed');
				$r->assert(!$res['ok'] || $followed, 'scheitert oder folgt der File-ID', $res['ret'] ?? $res['exc']);
				$r->assert(isset($after['renamed_by_user.jpg']) || $followed, 'die vom Benutzer umbenannte Datei ist nicht verloren');
				// Gegenprobe für move_source_by_path: Auflösung per File-ID liefert den aktuellen Pfad
				$cur = $w->getFirstNodeById($id);
				$r->observe('getFirstNodeById_current_path', $cur ? $cur->getPath() : null);
			}, false, self::DOC);

		// ---------------------------------------------------------------- A09
		$d[] = new CaseDef('A09', 'SP-A3', 'Ziel entsteht zwischen Existenzprüfung und Move (Kindprozess legt das Ziel an)',
			'Ein nodeExists()-Check vor dem Move schützt nicht: Entsteht das Ziel danach durch einen anderen Prozess, überschreibt Node::move es nicht (sonst ist ein Vorab-Check unzureichend).',
			function (Ctx $c, Rec $r) {
				$w = $c->workdir('A09');
				$srcContent = Ctx::bytes('A09-src', 2000);
				$tgtContent = Ctx::bytes('A09-tgt', 2000);
				$src = $c->put($w, 'src.jpg', $srcContent);
				$r->before = $c->snapshot($w);
				$exists = $w->nodeExists('target.jpg');
				$r->observe('exists_check', $exists);
				$r->assert(!$exists, 'Existenzprüfung: Ziel frei (Testvoraussetzung)');
				$race = Proc::race('A09', ['creator' => [['off' => 0.0, 'op' => ['type' => 'newfile', 'dir' => $w->getPath(), 'name' => 'target.jpg', 'seed' => 'A09-tgt', 'size' => 2000]]]]);
				$r->observe('creator', $race['children']['creator'] ?? null);
				$r->assert((bool)$race['ready'], 'Kindprozess bereit (Testvoraussetzung)');
				$res = $r->call('Node::move(target.jpg) nach dem Check', fn() => $src->move($w->getPath() . '/target.jpg'));
				$r->after = $c->snapshot($w);
				$targetSha = $r->after['target.jpg']['sha256'] ?? null;
				$r->observe('target_sha_after', $targetSha);
				$r->assert($targetSha === Ctx::shaStr($tgtContent) && !$res['ok'], 'vom anderen Prozess angelegtes Ziel unverändert, Move scheitert', ['move' => $res['ok'], 'target_is' => $targetSha === Ctx::shaStr($srcContent) ? 'moved_source' : ($targetSha === Ctx::shaStr($tgtContent) ? 'creator_content' : 'other')]);
				if ($res['ok']) {
					$r->evidence = 'counterexample';
				}
			}, false, self::DOC);

		// ---------------------------------------------------------------- A10
		$d[] = new CaseDef('A10', 'SP-A1', 'Node::newFile auf vorhandenem Pfad (Neuanlage-API als mögliche Reservierung)',
			'Folder::newFile() scheitert, wenn der Pfad bereits existiert (wäre eine atomare „Reservierungs“-Primitive).',
			function (Ctx $c, Rec $r) {
				$w = $c->workdir('A10');
				$content = Ctx::bytes('A10-old', 1200);
				$c->put($w, 'exists.jpg', $content);
				$r->before = $c->snapshot($w);
				$res = $r->call('Folder::newFile(exists.jpg, neuer Inhalt)', fn() => $w->newFile('exists.jpg', Ctx::bytes('A10-new', 1200)));
				$res2 = $r->call('Folder::newFile(exists.jpg) ohne Inhalt', fn() => $w->newFile('exists.jpg'));
				$r->after = $c->snapshot($w);
				$r->observe('content_replaced', ($r->after['exists.jpg']['sha256'] ?? null) !== Ctx::shaStr($content));
				$r->assert(!$res['ok'] && ($r->after['exists.jpg']['sha256'] ?? null) === Ctx::shaStr($content), 'newFile mit Inhalt scheitert, Inhalt unverändert', $res['ret'] ?? $res['exc']);
				$r->assert(!$res2['ok'], 'newFile ohne Inhalt scheitert', $res2['ret'] ?? $res2['exc']);
				if ($res['ok'] || $res2['ok']) {
					$r->evidence = 'counterexample';
				}
			}, false, self::DOC);

		// ---------------------------------------------------------------- A11 WebDAV Overwrite:F deterministisch
		$d[] = new CaseDef('A11', 'SP-A1', 'WebDAV MOVE mit Overwrite: F auf vorhandenes Ziel (Vergleichspunkt)',
			'WebDAV MOVE mit „Overwrite: F“ antwortet 412 und lässt Ziel und Quelle unverändert.',
			function (Ctx $c, Rec $r) {
				$w = $c->workdir('A11');
				$s = Ctx::bytes('A11-s', 1500);
				$t = Ctx::bytes('A11-t', 1500);
				$c->put($w, 'src.jpg', $s);
				$tg = $c->put($w, 'target.jpg', $t);
				$r->before = $c->snapshot($w);
				$dav = new Dav('alice');
				$rel = 'spike/' . $c->runId . '/A11/';
				$trash0 = array_keys($dav->propfind($dav->url('trashbin', 'trash'), 1)['entries']);
				$m = $dav->move($rel . 'src.jpg', $rel . 'target.jpg', false);
				$r->observe('dav_move_overwrite_F', Dav::brief($m));
				$afterF = $c->snapshot($w);
				$m2 = $dav->move($rel . 'src.jpg', $rel . 'target.jpg', true);
				$r->observe('dav_move_overwrite_T', Dav::brief($m2));
				$r->after = $c->snapshot($w);
				$trash1 = array_keys($dav->propfind($dav->url('trashbin', 'trash'), 1)['entries']);
				$r->observe('after_overwrite_F_unchanged', array_keys($afterF) === ['src.jpg', 'target.jpg'] && ($afterF['target.jpg']['sha256'] ?? null) === Ctx::shaStr($t) && ($afterF['src.jpg']['sha256'] ?? null) === Ctx::shaStr($s));
				$r->observe('overwrite_T_old_target_in_trash', array_values(array_diff($trash1, $trash0)));
				$r->assert(array_keys($afterF) === ['src.jpg', 'target.jpg'] && ($afterF['target.jpg']['sha256'] ?? null) === Ctx::shaStr($t), 'nach 412: Quelle und Ziel unverändert');
				$r->assert($m['status'] === 412, 'Overwrite: F auf vorhandenes Ziel → 412', Dav::brief($m));
				$r->note('Der zweite Aufruf (Overwrite: T) ist nur eine Kontrolle: er zeigt, dass WebDAV MOVE ohne F das Ziel ersetzt.');
			}, false, self::DOC);

		// ---------------------------------------------------------------- A12 Locking während Move (Instrumentierung)
		$d[] = new CaseDef('A12', 'SP-A3/SP-E', 'Während eines (künstlich angehaltenen) Moves: kann ein Konkurrent das Ziel anlegen?',
			'Solange Node::move innerhalb von View::rename läuft, sind Zielpfad-Anlage und Zielpfad-Move durch Dateisperren ausgeschlossen (Sperre = gegenseitiger Ausschluss, noch kein No-Overwrite).',
			function (Ctx $c, Rec $r) {
				foreach ([false, true] as $lockingOn) {
					$tag = $lockingOn ? 'locking_on' : 'locking_off';
					self::withFileLocking($c, $lockingOn, function () use ($c, $r, $tag, $lockingOn) {
						$w = $c->workdir('A12' . $tag);
						$c->put($w, 'src.jpg', Ctx::bytes('A12-src', 1000));
						$c->put($w, 'other.jpg', Ctx::bytes('A12-other', 1000));
						$rel = 'spike/' . $c->runId . '/A12' . $tag . '/';
						$marker = '/tmp/po-spike/hook-hit-' . $tag;
						$release = '/tmp/po-spike/hook-release-' . $tag;
						@unlink($marker);
						@unlink($release);
						Proc::ensureTmp();
						file_put_contents('/tmp/po-spike/hook.json', json_encode(['match' => '/A12' . $tag . '/target.jpg', 'marker' => $marker, 'release' => $release, 'max_wait' => 12]));
						@chmod('/tmp/po-spike/hook.json', 0666);
						$p = Proc::spawn('A12mover-' . $tag, [['off' => 0.0, 'op' => ['type' => 'move', 'src' => $w->getPath() . '/src.jpg', 'dst' => $w->getPath() . '/target.jpg']]]);
						$ok = $p->waitReady(45);
						if ($ok) {
							$p->go(microtime(true) + 0.2);
						}
						$hit = false;
						$end = microtime(true) + 15;
						while (microtime(true) < $end && !($hit = is_file($marker))) {
							usleep(10000);
						}
						$r->observe("{$tag}_move_frozen_inside_storage_rename", $hit);
						if (!$hit) {
							$r->inconclusive("$tag: Instrumentierung hat nicht gegriffen");
						} else {
							$dav = new Dav('alice');
							$a = $r->call("$tag: newFile(target.jpg) während Move", fn() => $w->newFile('target.jpg', Ctx::bytes('A12-creator', 1000)));
							$b = $dav->put($rel . 'target.jpg', Ctx::bytes('A12-dav', 1000));
							$m = $dav->move($rel . 'other.jpg', $rel . 'target.jpg', true);
							$r->observe("{$tag}_node_newFile_during_move", $a['ok'] ? 'success' : $a['exc']);
							$r->observe("{$tag}_dav_put_during_move", Dav::brief($b));
							$r->observe("{$tag}_dav_move_during_move", Dav::brief($m));
							if ($lockingOn) {
								$rejected = fn($st) => $st >= 400;
								$r->assert(!$a['ok'] && $rejected($b['status']) && $rejected($m['status']), 'mit Dateisperren: Konkurrenzzugriffe auf das Ziel werden abgewiesen (LockedException / HTTP ≥ 400)', [$a['exc']['class'] ?? 'ok', $b['status'], $m['status']]);
							} else {
								$r->note('ohne Dateisperren: Ergebnis rein beschreibend (kein Ausschluss erwartet): ' . ($a['ok'] ? 'newFile ok' : 'newFile fail') . ', PUT ' . $b['status'] . ', MOVE ' . $m['status']);
							}
						}
						touch($release);
						$p->wait(30);
						$res = $p->results();
						$r->observe("{$tag}_mover_result", $res[0]['res'] ?? null);
						$p->close();
						@unlink('/tmp/po-spike/hook.json');
						$r->after = $c->snapshot($w);
					});
				}
			}, false, self::DOC);

		// ---------------------------------------------------------------- A13 (Instrumentierung Sabre)
		$d[] = new CaseDef('A13', 'SP-A3', 'WebDAV MOVE Overwrite: F – Ziel entsteht NACH dem Overwrite-Check (Sabre beforeMove angehalten)',
			'Auch „Overwrite: F“ bleibt sicher: Entsteht das Ziel nach dem Check von Sabre, wird es nicht ersetzt (412/Fehler).',
			function (Ctx $c, Rec $r) {
				$w = $c->workdir('A13');
				$srcC = Ctx::bytes('A13-src', 1200);
				$crtC = Ctx::bytes('A13-creator', 1200);
				$c->put($w, 'src.bin', $srcC);
				$rel = 'spike/' . $c->runId . '/A13/';
				$marker = '/tmp/po-spike/hook-hit-A13';
				$release = '/tmp/po-spike/hook-release-A13';
				@unlink($marker);
				@unlink($release);
				Proc::ensureTmp();
				file_put_contents('/tmp/po-spike/hook.json', json_encode(['phase' => 'sabre', 'match' => '/A13/target.bin', 'marker' => $marker, 'release' => $release, 'max_wait' => 15]));
				@chmod('/tmp/po-spike/hook.json', 0666);
				$r->before = $c->snapshot($w);
				$p = Proc::spawn('A13mover', [['off' => 0.0, 'op' => ['type' => 'dav', 'user' => 'alice', 'method' => 'MOVE', 'rel' => $rel . 'src.bin', 'dest' => $rel . 'target.bin', 'headers' => ['Overwrite' => 'F']]]]);
				$ok = $p->waitReady(45);
				$ok && $p->go(microtime(true) + 0.2);
				$hit = false;
				$end = microtime(true) + 20;
				while (microtime(true) < $end && !($hit = is_file($marker))) {
					usleep(10000);
				}
				$r->observe('dav_move_frozen_after_overwrite_check', $hit);
				if (!$hit) {
					$r->inconclusive('Instrumentierung (Sabre beforeMove) hat nicht gegriffen');
				} else {
					$dav = new Dav('alice');
					$trash0 = array_keys($dav->propfind($dav->url('trashbin', 'trash'), 1)['entries']);
					$a = $r->call('Node::newFile(target.bin) während der angehaltenen MOVE-Anfrage', fn() => $w->newFile('target.bin', $crtC));
					$r->observe('creator_succeeded', $a['ok']);
				}
				touch($release);
				$p->wait(40);
				$res = $p->results();
				$p->close();
				@unlink('/tmp/po-spike/hook.json');
				$mv = $res[0]['res'] ?? null;
				$r->observe('dav_move_result_after_release', $mv);
				$r->after = $c->snapshot($w);
				if ($hit) {
					$trash1 = array_keys($dav->propfind($dav->url('trashbin', 'trash'), 1)['entries']);
					$r->observe('creator_content_in_trash', array_values(array_diff($trash1, $trash0)));
					$final = $r->after['target.bin']['sha256'] ?? null;
					$replaced = ($a['ok'] ?? false) && $final === Ctx::shaStr($srcC);
					$r->observe('final_target_is', $final === Ctx::shaStr($srcC) ? 'moved_source' : ($final === Ctx::shaStr($crtC) ? 'creator_content' : 'other'));
					$r->assert(!$replaced, 'das nach dem Check angelegte Ziel wurde nicht ersetzt', ['move_ok' => $mv['ok'] ?? null, 'final' => $final === Ctx::shaStr($srcC) ? 'moved_source' : 'creator_content']);
					if ($replaced) {
						$r->evidence = 'counterexample';
						$r->note('Overwrite: F wird von Sabre einmal vor der Ausführung geprüft; ein danach entstandenes Ziel wird vom anschließenden Rename ersetzt (kein Papierkorbeintrag, siehe creator_content_in_trash).');
					}
				}
			}, false, self::DOC);

		// ---------------------------------------------------------------- A14/A15 geschützter Move (Prototyp, interne API)
		foreach ([['A14', true], ['A15', false]] as [$gid, $lockingOn]) {
			$d[] = new CaseDef($gid, 'SP-A1/A3', 'Prototyp „geschützter Move“ (Storage-Wrapper, interne API) ' . ($lockingOn ? 'mit' : 'ohne') . ' Dateisperren',
				$lockingOn
					? 'Ein Node::move mit Überschreibschutz INNERHALB des gesperrten Bereichs (Storage-Wrapper-Prüfung) ersetzt weder ein vorhandenes noch ein während des Moves angelegtes Ziel.'
					: 'Der geschützte Move bleibt auch ohne aktivierte Dateisperren sicher (Erwartung der Architektur: nein – die Sicherheit hängt an den Sperren).',
				function (Ctx $c, Rec $r) use ($gid, $lockingOn) {
					self::withFileLocking($c, $lockingOn, function () use ($c, $r, $gid, $lockingOn) {
						$w = $c->workdir($gid);
						$srcC = Ctx::bytes("$gid-src", 1200);
						$preC = Ctx::bytes("$gid-pre", 1200);
						$crtC = Ctx::bytes("$gid-creator", 1200);
						$c->put($w, 'src1.bin', $srcC);
						$c->put($w, 'src2.bin', $srcC . 'x');
						$c->put($w, 'pre.bin', $preC);
						$r->before = $c->snapshot($w);
						// (1) deterministisch: vorhandenes Ziel
						$p0 = \OCA\PoSpikeProbe\Support\Proc::race($gid . 'a', ['x' => [['off' => 0.0, 'op' => ['type' => 'move_guarded', 'src' => $w->getPath() . '/src2.bin', 'dst' => $w->getPath() . '/pre.bin']]]]);
						$m0 = $p0['children']['x']['results'][0]['res'] ?? null;
						$snap0 = $c->snapshot($w);
						$r->observe('existing_target_guarded_move', $m0 ? ($m0['ok'] ? 'ok (ERSETZT!)' : $m0['exc']['class']) : 'kein Ergebnis');
						$r->assert($m0 && !$m0['ok'] && ($snap0['pre.bin']['sha256'] ?? null) === Ctx::shaStr($preC) && isset($snap0['src2.bin']), 'vorhandenes Ziel: geschützter Move scheitert, Ziel und Quelle unverändert', $m0['exc'] ?? null);
						// (2) Ziel entsteht während des Moves (angehalten nach der Prüfung, vor dem Rename)
						$rel = 'spike/' . $c->runId . '/' . $gid . '/';
						$marker = '/tmp/po-spike/hook-hit-' . $gid;
						$release = '/tmp/po-spike/hook-release-' . $gid;
						@unlink($marker);
						@unlink($release);
						Proc::ensureTmp();
						file_put_contents('/tmp/po-spike/hook.json', json_encode(['phase' => 'storage_after_check', 'match' => '/' . $gid . '/target.bin', 'marker' => $marker, 'release' => $release, 'max_wait' => 15]));
						@chmod('/tmp/po-spike/hook.json', 0666);
						$p = Proc::spawn($gid . 'mover', [['off' => 0.0, 'op' => ['type' => 'move_guarded', 'src' => $w->getPath() . '/src1.bin', 'dst' => $w->getPath() . '/target.bin']]]);
						$ok = $p->waitReady(45);
						$ok && $p->go(microtime(true) + 0.2);
						$hit = false;
						$end = microtime(true) + 20;
						while (microtime(true) < $end && !($hit = is_file($marker))) {
							usleep(10000);
						}
						$a = ['ok' => null];
						$b = ['status' => null];
						if (!$hit) {
							$r->inconclusive('Instrumentierung (nach Prüfung, vor Rename) hat nicht gegriffen');
						} else {
							$a = $r->call('Node::newFile(target.bin) während der angehaltenen Wrapper-Prüfung', fn() => $w->newFile('target.bin', $crtC));
							$dav = new Dav('alice');
							$b = $dav->put($rel . 'target.bin', $crtC . 'dav');
							$r->observe('creator_node_newFile', $a['ok'] ? 'success' : $a['exc']['class']);
							$r->observe('creator_dav_put', Dav::brief($b));
						}
						touch($release);
						$p->wait(40);
						$res = $p->results();
						$p->close();
						@unlink('/tmp/po-spike/hook.json');
						$mv = $res[0]['res'] ?? null;
						$r->observe('guarded_mover_result', $mv ? ($mv['ok'] ? 'ok' : $mv['exc']) : null);
						$r->after = $c->snapshot($w);
						$final = $r->after['target.bin']['sha256'] ?? null;
						$creatorSucceeded = ($a['ok'] ?? false) || (($b['status'] ?? 0) >= 200 && ($b['status'] ?? 0) < 300);
						$replaced = $hit && $creatorSucceeded && ($mv['ok'] ?? false) && $final === Ctx::shaStr($srcC);
						$r->observe('final_target_is', $final === Ctx::shaStr($srcC) ? 'moved_source' : ($final === Ctx::shaStr($crtC) || $final === Ctx::shaStr($crtC . 'dav') ? 'creator_content' : 'other'));
						if ($hit) {
							$r->assert(!$replaced, 'ein während des Moves erfolgreich angelegter Inhalt wurde nicht ersetzt', ['creator_succeeded' => $creatorSucceeded, 'mover_ok' => $mv['ok'] ?? null]);
							if ($lockingOn) {
								$r->assert(!($a['ok'] ?? true) && ($b['status'] ?? 0) >= 400, 'Konkurrenten werden durch die Dateisperren abgewiesen', [$a['exc']['class'] ?? 'ok', $b['status'] ?? null]);
							}
						}
						if ($replaced) {
							$r->evidence = 'counterexample';
						}
					});
				}, false, self::DOC);
		}

		// ---------------------------------------------------------------- A16 Ausnahmen während des Moves
		$d[] = new CaseDef('A16', 'SP-A', 'Fehler und Ausnahmen während des Moves: Listener bricht vor dem Rename ab / scheitert nach dem Rename',
			'Ein Fehler eines Event-Listeners vor dem Rename bricht den Move sauber ab (Ausnahme, Datei unverändert); ein Listener-Fehler nach dem Rename erreicht den Aufrufer nicht (der Move gilt als erfolgreich, die Wirkung ist eingetreten).',
			function (Ctx $c, Rec $r) {
				$w = $c->workdir('A16');
				$content = Ctx::bytes('A16', 1500);
				Proc::ensureTmp();
				// (a) Abbruch im Before-Listener
				$f1 = $c->put($w, 'a.bin', $content);
				file_put_contents('/tmp/po-spike/throw.json', json_encode(['event' => 'BeforeNodeRenamedEvent', 'match' => '/A16/a-target.bin']));
				@chmod('/tmp/po-spike/throw.json', 0666);
				$resA = $r->call('Move mit Abbruch durch Before-Listener', fn() => $f1->move($w->getPath() . '/a-target.bin'));
				$snapA = $c->snapshot($w);
				$r->observe('before_listener_abort', ['exception' => $resA['exc'], 'source_still_there' => isset($snapA['a.bin']), 'target_exists' => isset($snapA['a-target.bin'])]);
				$r->assert(!$resA['ok'] && isset($snapA['a.bin']) && !isset($snapA['a-target.bin']), '(a) Abbruch vor dem Rename: Ausnahme und Datei unverändert am Quellpfad', $resA['exc']);
				// (b) Fehler im After-Listener
				$f2 = $c->put($w, 'b.bin', Ctx::bytes('A16-b', 1500));
				file_put_contents('/tmp/po-spike/throw.json', json_encode(['event' => 'NodeRenamedEvent', 'match' => '/A16/b-target.bin']));
				$resB = $r->call('Move mit Fehler im After-Listener', fn() => $f2->move($w->getPath() . '/b-target.bin'));
				@unlink('/tmp/po-spike/throw.json');
				$snapB = $c->snapshot($w);
				$moved = !isset($snapB['b.bin']) && isset($snapB['b-target.bin']);
				$r->observe('after_listener_failure', ['exception_reaches_caller' => !$resB['ok'], 'exception' => $resB['exc'], 'source_still_there' => isset($snapB['b.bin']), 'target_exists' => isset($snapB['b-target.bin'])]);
				$r->assert($moved && $resB['ok'], '(b) Listener-Fehler nach dem Rename: Move gilt als erfolgreich, Wirkung eingetreten, Fehler wird nicht an den Aufrufer gemeldet', ['moved' => $moved, 'caller_got_exception' => !$resB['ok']]);
				$r->note('Fehler in After-Listenern fremder Apps bleiben für den Aufrufer unsichtbar (nur Protokoll). Umgekehrt zeigt A21/A24/A25/A28, dass ein als gescheitert gemeldeter DAV-PUT seinen Inhalt dennoch hinterlassen kann („Fehler gemeldet, Wirkung vorhanden“).');
				$r->after = $snapB;
			}, false, self::DOC);

		// ---------------------------------------------------------------- A30 Benutzer benennt die Quelle um, während die App verschiebt
		$d[] = new CaseDef('A30', 'SP-I', 'Konkurrenz an der Quelle: App-Move gegen Client-Umbenennung derselben Datei',
			'Bei gleichzeitiger Umbenennung der Quelle durch einen Client und App-Move geht nie Inhalt verloren und erscheint nie doppelt: die Datei liegt danach genau einmal entweder am App-Ziel oder am Client-Namen.',
			fn(Ctx $c, Rec $r, int $repeat) => self::sourceRace($c, $r, $repeat),
			true, self::DOC);

		// ---------------------------------------------------------------- Race-Tests
		$combos = [
			['A20', 'node_move', 'node_newfile'],
			['A21', 'node_move', 'dav_put'],
			['A22', 'node_move', 'node_move'],
			['A23', 'dav_move_F', 'node_newfile'],
			['A24', 'dav_move_F', 'dav_put'],
			['A25', 'dav_move_F', 'dav_put_inm'],
			['A26', 'dav_move_F', 'dav_move_F'],
			['A27', 'node_move_guarded', 'node_newfile'],
			['A28', 'node_move_guarded', 'dav_put'],
			['A29', 'node_move_guarded', 'node_move_guarded'],
		];
		foreach ($combos as [$id, $mover, $second]) {
			$d[] = new CaseDef($id, 'SP-A3', "Konkurrenz am Ziel: $mover gegen $second",
				"Unter Konkurrenz (zwei Prozesse, $mover und $second auf denselben Zielpfad, Startversatz −150…+150 ms, davon ein Drittel fein −5…+5 ms) wird nie ein erfolgreich geschriebener Inhalt durch einen erfolgreichen Move ersetzt.",
				fn(Ctx $c, Rec $r, int $repeat) => self::race($c, $r, $id, $mover, $second, $repeat),
				true, self::DOC);
		}
		return $d;
	}

	/** Führt $fn mit gesetztem filelocking.enabled aus und stellt den Wert wieder her. */
	public static function withFileLocking(Ctx $c, bool $on, callable $fn): void {
		$cfg = \OCP\Server::get(\OCP\IConfig::class);
		$old = $cfg->getSystemValue('filelocking.enabled', true);
		$cfg->setSystemValue('filelocking.enabled', $on);
		try {
			$fn();
		} finally {
			$cfg->setSystemValue('filelocking.enabled', $old);
		}
	}

	private static function opFor(string $kind, string $dirPath, string $rel, string $srcName, string $seed, int $size): array {
		switch ($kind) {
			case 'node_move':
				return ['type' => 'move', 'src' => $dirPath . '/' . $srcName, 'dst' => $dirPath . '/target.bin'];
			case 'node_move_guarded':
				return ['type' => 'move_guarded', 'src' => $dirPath . '/' . $srcName, 'dst' => $dirPath . '/target.bin'];
			case 'node_newfile':
				return ['type' => 'newfile', 'dir' => $dirPath, 'name' => 'target.bin', 'seed' => $seed, 'size' => $size];
			case 'dav_put':
				return ['type' => 'dav', 'user' => 'alice', 'method' => 'PUT', 'rel' => $rel . 'target.bin', 'seed' => $seed, 'size' => $size];
			case 'dav_put_inm':
				return ['type' => 'dav', 'user' => 'alice', 'method' => 'PUT', 'rel' => $rel . 'target.bin', 'seed' => $seed, 'size' => $size, 'headers' => ['If-None-Match' => '*']];
			case 'dav_move_F':
				return ['type' => 'dav', 'user' => 'alice', 'method' => 'MOVE', 'rel' => $rel . $srcName, 'dest' => $rel . 'target.bin', 'headers' => ['Overwrite' => 'F']];
		}
		throw new \InvalidArgumentException($kind);
	}

	/**
	 * Statistische Konkurrenzprüfung. Kriterium (zeitunabhängig):
	 *   Mover erfolgreich ∧ Zweiter erfolgreich ∧ Endinhalt = Inhalt des Movers
	 *   ⇒ der Move hat einen zuvor erfolgreich geschriebenen Inhalt ersetzt (Verletzung von „kein Überschreiben“).
	 * Ein späteres Überschreiben des bewegten Inhalts durch den Zweiten (Endinhalt = Zweiter) ist eine
	 * andere Klasse („zweiter Schreiber ersetzt Move-Ergebnis“) und wird getrennt gezählt.
	 */
	private static function race(Ctx $c, Rec $r, string $id, string $mover, string $second, int $n): void {
		$w = $c->workdir($id);
		$base = $w->getPath();
		$relBase = 'spike/' . $c->runId . '/' . $id . '/';
		$plans = ['m' => [], 's' => []];
		$meta = [];
		$hasDav = str_starts_with($mover, 'dav') || str_starts_with($second, 'dav');
		$step = $hasDav ? 2.4 : 1.2; // Operationen dauern bis zu mehreren hundert ms; Iterationen dürfen sich nicht überlappen
		for ($i = 0; $i < $n; $i++) {
			$dir = $w->newFolder("i$i");
			$mSeed = "$id-m-$i";
			$sSeed = "$id-s-$i";
			$c->put($dir, 'srcM.bin', Ctx::bytes($mSeed, 1024));
			if (str_contains($second, 'move')) {
				$c->put($dir, 'srcS.bin', Ctx::bytes($sSeed, 1024));
			}
			$dirPath = $dir->getPath();
			$rel = $relBase . "i$i/";
			// Startversatz des Zweiten gegenüber dem Mover, deterministisch gemischt, damit alle Überlappungsgrade vorkommen:
			//  i%3==0 fein (−5…+5 ms, 0,25-ms-Schritte) · i%3==1 mittel (−150…+150 ms, 5-ms-Schritte) · i%3==2 grob (−600…+600 ms, 20-ms-Schritte; auch nicht überlappende Abfolgen)
			$offsetMs = match ($i % 3) {
				0 => (($i * 7) % 41 - 20) * 0.25,
				1 => (($i * 13) % 61 - 30) * 5.0,
				default => (($i * 17) % 61 - 30) * 20.0,
			};
			$offM = 0.6 + $i * $step;
			$offS = $offM + $offsetMs / 1000.0;
			$plans['m'][] = ['off' => $offM, 'op' => self::opFor($mover, $dirPath, $rel, 'srcM.bin', $mSeed, 1024)];
			$plans['s'][] = ['off' => $offS, 'op' => self::opFor($second, $dirPath, $rel, 'srcS.bin', $sSeed, 1024)];
			$meta[$i] = ['dir' => "i$i", 'cM' => Ctx::shaStr(Ctx::bytes($mSeed, 1024)), 'cS' => Ctx::shaStr(Ctx::bytes($sSeed, 1024)), 'offset_ms' => $offsetMs];
		}
		$r->before = $c->snapshot($w); // Ausgangszustand aller Iterationen (Pfade, File-IDs, SHA-256)
		$race = Proc::race($id, $plans, 0.5, 120 + $n * $step * 1.15);
		if (!$race['ready']) {
			$r->inconclusive('Kindprozesse nicht rechtzeitig bereit');
			$r->observe('stderr', array_map(fn($x) => $x['stderr'] ?? '', $race['children']));
			return;
		}
		$resM = $race['children']['m']['results'];
		$resS = $race['children']['s']['results'];
		$violations = [];
		$completed = 0;
		$aligned = 0;
		$maxLag = 0.0;
		for ($i = 0; $i < $n; $i++) {
			$m = $resM[$i]['res'] ?? null;
			$s = $resS[$i]['res'] ?? null;
			if ($m === null || $s === null) {
				$r->tally('missing_result');
				continue;
			}
			$lag = max(abs($resM[$i]['lag_ms']), abs($resS[$i]['lag_ms']));
			$maxLag = max($maxLag, $lag);
			if ($lag <= 2.0) {
				$aligned++;
			}
			// Endzustand des Iterationsordners (bei noch gehaltener Sperre kurz wiederholen)
			$snap = null;
			for ($try = 0; $try < 8 && $snap === null; $try++) {
				try {
					$snap = $c->snapshot($w->get("i$i"));
				} catch (\OCP\Lock\LockedException) {
					usleep(250000);
				}
			}
			if ($snap === null) {
				$r->tally('snapshot_failed_locked');
				continue;
			}
			$completed++;
			$t = $snap['target.bin']['sha256'] ?? null;
			$cM = $meta[$i]['cM'];
			$cS = $meta[$i]['cS'];
			if ($i < 2) {
				$r->extra['sample_iteration_' . $i] = ['mover' => $m, 'second' => $s, 'lag_ms' => $lag, 'snapshot_after' => $snap];
			}
			$class = ($m['ok'] ? 'M_ok' : 'M_fail') . '/' . ($s['ok'] ? 'S_ok' : 'S_fail');
			$final = $t === null ? 'no_target' : ($t === $cM ? 'final_M' : ($t === $cS ? 'final_S' : 'final_other'));
			$bucket = "$class/$final";
			$r->tally($bucket);
			if (!isset($r->extra['samples'][$bucket]) || count($r->extra['samples'][$bucket]) < 2) {
				$r->extra['samples'][$bucket][] = ['iteration' => $i, 'offset_ms' => $meta[$i]['offset_ms'], 'mover' => $m, 'second' => $s, 'lag_ms' => $lag, 'final_files' => array_map(fn($e) => substr((string)($e['sha256'] ?? ''), 0, 12) . ' id=' . $e['id'], $snap), 'expected_prefix' => ['M' => substr($cM, 0, 12), 'S' => substr($cS, 0, 12)]];
			}
			if ($m['ok'] && $s['ok'] && $t === $cM) {
				$r->tally('VIOLATION_move_replaced_successful_write');
				if (count($violations) < 5) {
					$violations[] = ['iteration' => $i, 'offset_ms' => $meta[$i]['offset_ms'], 'mover' => $m, 'second' => $s, 'lag_ms' => $lag, 'snapshot_after' => $snap, 'expected_hashes' => ['mover_content' => $cM, 'second_content' => $cS]];
				}
			}
			if ($m['ok'] && $s['ok'] && $t === $cS) {
				$r->tally('second_writer_replaced_move_result');
			}
			// „Fehler gemeldet, Wirkung vorhanden“: ein als gescheitert gemeldeter Aufruf hat seinen Inhalt dennoch am Ziel hinterlassen
			if (!$m['ok'] && $t === $cM) {
				$r->tally('FAIL_REPORTED_BUT_MOVER_EFFECT_PRESENT');
			}
			if (!$s['ok'] && $t === $cS) {
				$r->tally('FAIL_REPORTED_BUT_SECOND_EFFECT_PRESENT');
			}
			if ($t !== null && $t !== $cM && $t !== $cS) {
				$r->tally('ANOMALY_unknown_target_content');
				if (count($violations) < 8) {
					$violations[] = ['anomaly' => true, 'iteration' => $i, 'mover' => $m, 'second' => $s, 'snapshot_after' => $snap];
				}
			}
			if (!$m['ok'] && !$s['ok']) {
				$r->tally('both_failed');
			}
			// Jede erfolgreiche Seite muss ihre Datei am Ende irgendwo haben: Verlustzählung
			if ($m['ok'] && !in_array($cM, array_column(array_filter($snap, fn($e) => $e['type'] === 'file'), 'sha256'), true)) {
				$r->tally('content_of_successful_mover_not_present_anywhere');
			}
		}
		$r->after = $c->snapshot($w);
		$r->extra['aligned_iterations_within_2ms'] = $aligned;
		$r->extra['max_start_lag_ms'] = $maxLag;
		$r->extra['violation_details'] = $violations;
		$r->extra['children_finished'] = array_map(fn($x) => $x['finished'], $race['children']);
		$r->observe('iterations', $n);
		$r->observe('aligned_within_2ms', $aligned);
		$viol = $r->tally['VIOLATION_move_replaced_successful_write'] ?? 0;
		$r->attempts = $completed;
		$r->observe('completed_iterations', $completed);
		$r->assert($viol === 0, "keine Verletzung in $completed ausgewerteten Versuchen (ausgerichtet ≤2 ms: $aligned)", $viol);
		if ($viol > 0) {
			$r->evidence = 'counterexample';
		} else {
			$r->evidence = 'finite_sample';
			$r->note("Kein Verstoß in $completed Versuchen; das ist keine Garantie, sondern eine endliche Stichprobe (ausgerichtet ≤2 ms: $aligned).");
		}
		if ($completed < $n * 0.9) {
			$r->inconclusive("nur $completed von $n Versuchen ausgewertet (Kindprozess-Timeout oder gesperrte Endzustände): Stichprobe unvollständig");
		}
		if ($completed > 0 && $aligned < $completed * 0.3) {
			$r->inconclusive('weniger als 30 % der Versuche zeitlich ausgerichtet (≤2 ms): Stichprobe zu schwach');
		}
	}

	/** App-Move und Client-Umbenennung (WebDAV MOVE) derselben Quelldatei. */
	private static function sourceRace(Ctx $c, Rec $r, int $n): void {
		$w = $c->workdir('A30');
		$relBase = 'spike/' . $c->runId . '/A30/';
		$plans = ['app' => [], 'client' => []];
		$meta = [];
		for ($i = 0; $i < $n; $i++) {
			$dir = $w->newFolder("i$i");
			$content = Ctx::bytes("A30-$i", 1024);
			$c->put($dir, 'src.bin', $content);
			$offsetMs = match ($i % 3) {
				0 => (($i * 7) % 41 - 20) * 0.25,
				1 => (($i * 13) % 61 - 30) * 5.0,
				default => (($i * 17) % 61 - 30) * 20.0,
			};
			$offApp = 0.6 + $i * 2.4;
			$plans['app'][] = ['off' => $offApp, 'op' => ['type' => 'move', 'src' => $dir->getPath() . '/src.bin', 'dst' => $dir->getPath() . '/app-target.bin']];
			$plans['client'][] = ['off' => $offApp + $offsetMs / 1000.0, 'op' => ['type' => 'dav', 'user' => 'alice', 'method' => 'MOVE', 'rel' => $relBase . "i$i/src.bin", 'dest' => $relBase . "i$i/client-renamed.bin", 'headers' => ['Overwrite' => 'F']]];
			$meta[$i] = ['sha' => Ctx::shaStr($content), 'offset_ms' => $offsetMs];
		}
		$r->before = $c->snapshot($w);
		$race = Proc::race('A30', $plans, 0.5, 60 + $n * 3);
		if (!$race['ready']) {
			$r->inconclusive('Kindprozesse nicht bereit');
			return;
		}
		$aligned = 0;
		$completed = 0;
		$samples = [];
		for ($i = 0; $i < $n; $i++) {
			$a = $race['children']['app']['results'][$i] ?? null;
			$b = $race['children']['client']['results'][$i] ?? null;
			if (!$a || !$b) {
				$r->tally('missing_result');
				continue;
			}
			if (max(abs($a['lag_ms']), abs($b['lag_ms'])) <= 2.0) {
				$aligned++;
			}
			$snap = null;
			for ($try = 0; $try < 8 && $snap === null; $try++) {
				try {
					$snap = $c->snapshot($w->get("i$i"));
				} catch (\OCP\Lock\LockedException) {
					usleep(250000);
				}
			}
			if ($snap === null) {
				$r->tally('snapshot_failed_locked');
				continue;
			}
			$completed++;
			$where = array_keys(array_filter($snap, fn($e) => ($e['sha256'] ?? null) === $meta[$i]['sha']));
			$cls = ($a['res']['ok'] ? 'app_ok' : 'app_fail') . '/' . ($b['res']['ok'] ? 'client_ok' : 'client_fail') . '/at:' . implode('+', $where ?: ['NOWHERE']);
			$r->tally($cls);
			if (!isset($samples[$cls]) || count($samples[$cls]) < 2) {
				$samples[$cls][] = ['iteration' => $i, 'offset_ms' => $meta[$i]['offset_ms'], 'app' => $a['res'], 'client' => $b['res']];
			}
			if (count($where) === 0) {
				$r->tally('VIOLATION_content_lost');
			}
			if (count($where) > 1) {
				$r->tally('ANOMALY_content_duplicated');
			}
			if ($a['res']['ok'] && $b['res']['ok']) {
				$r->tally('ANOMALY_both_report_success');
			}
			if (!$a['res']['ok'] && count($where) === 1 && $where[0] === 'app-target.bin') {
				$r->tally('FAIL_REPORTED_BUT_APP_MOVE_EFFECT_PRESENT');
			}
		}
		$r->after = $c->snapshot($w);
		$r->extra['samples'] = $samples;
		$r->extra['aligned_iterations_within_2ms'] = $aligned;
		$r->observe('iterations', $n);
		$r->observe('aligned_within_2ms', $aligned);
		$bad = ($r->tally['VIOLATION_content_lost'] ?? 0) + ($r->tally['ANOMALY_content_duplicated'] ?? 0) + ($r->tally['ANOMALY_both_report_success'] ?? 0);
		$r->attempts = $completed;
		$r->assert($bad === 0, "kein Verlust, keine Verdopplung, kein doppelter Erfolg in $completed Versuchen (ausgerichtet ≤2 ms: $aligned)", $r->tally);
		$r->evidence = $bad ? 'counterexample' : 'finite_sample';
		if ($completed < $n * 0.9) {
			$r->inconclusive("nur $completed von $n Versuchen ausgewertet");
		}
		if ($completed > 0 && $aligned < $completed * 0.3) {
			$r->inconclusive('weniger als 30 % der Versuche zeitlich ausgerichtet (≤2 ms)');
		}
	}
}

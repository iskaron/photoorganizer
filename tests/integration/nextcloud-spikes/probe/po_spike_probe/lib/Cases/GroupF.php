<?php
// WEGWERF-TESTCODE (Nextcloud-API-Spikes, Auftrag 5). Gruppe F: HashContext (SHA-256).
declare(strict_types=1);

namespace OCA\PoSpikeProbe\Cases;

use OCA\PoSpikeProbe\Support\CaseDef;
use OCA\PoSpikeProbe\Support\Ctx;
use OCA\PoSpikeProbe\Support\Rec;

require_once __DIR__ . '/../Support/HashCtxLab.php';

final class GroupF {
	private const DOC = 'job-processing.md §3.6 (H1–H3), SP-H, hash_resume, DEC-IMM-008';

	/** Liest einen Datei-Knoten ab $offset über die Node-API (Stream) und führt einen Kontext fort. */
	private static function hashStream(\OCP\Files\File $f, ?\HashContext $ctx, int $from, int $to): \HashContext {
		$ctx ??= hash_init('sha256');
		$h = $f->fopen('r');
		fseek($h, $from);
		$left = $to - $from;
		while ($left > 0 && !feof($h)) {
			$chunk = fread($h, min(65536, $left));
			if ($chunk === false || $chunk === '') {
				break;
			}
			hash_update($ctx, $chunk);
			$left -= strlen($chunk);
		}
		fclose($h);
		return $ctx;
	}

	/** @return list<CaseDef> */
	public static function cases(): array {
		$d = [];

		$d[] = new CaseDef('F01', 'SP-H', 'SHA-256-Kontext: Serialisieren und Fortsetzen an vielen Offsets (reines PHP)',
			'Ein per serialize() gesicherter SHA-256-HashContext lässt sich fortsetzen und liefert exakt den Digest des Gesamtinhalts.',
			function (Ctx $c, Rec $r) {
				$size = 3 * 1024 * 1024 + 17;
				$data = \HashCtxLab::data('F01', $size);
				$expected = hash('sha256', $data);
				$offsets = [0, 1, 55, 56, 63, 64, 65, 127, 128, 4095, 4096, 65536, 1048575, 1048576, $size - 1, $size];
				$bad = [];
				foreach ($offsets as $o) {
					$st = \HashCtxLab::state('sha256', $data, $o);
					try {
						if (\HashCtxLab::resume($st, $data, $o) !== $expected) {
							$bad[] = $o;
						}
					} catch (\Throwable $e) {
						$bad[] = [$o, get_class($e)];
					}
				}
				$r->observe('php_version', PHP_VERSION);
				$r->observe('offsets_tested', count($offsets));
				$r->observe('serialized_state_bytes', strlen($st));
				$r->observe('state_sample_base64', base64_encode(\HashCtxLab::state('sha256', $data, 100)));
				$r->assert($bad === [], 'alle Offsets liefern den korrekten Digest', $bad);
				$r->note('Gilt für SHA-256 unter dieser PHP-Version; keine Aussage über andere Algorithmen (xxh3 ist hier nicht Gegenstand).');
			}, false, self::DOC);

		$d[] = new CaseDef('F02', 'SP-H', 'Prozessneustart: Zustand in Datei, Fortsetzung in neuem PHP-Prozess; Datei aus der Nextcloud-Node-API',
			'Der Zustand überlebt einen Prozessneustart; eine über die Nextcloud-Node-API gelesene Datei lässt sich in einem anderen Prozess ab dem gespeicherten Offset fortsetzen.',
			function (Ctx $c, Rec $r) {
				$w = $c->workdir('F02');
				$size = 2 * 1024 * 1024 + 321;
				$content = \HashCtxLab::data('F02', $size);
				$f = $c->put($w, 'big.bin', $content);
				$expected = hash('sha256', $content);
				$offset = 700000;
				$ctx = self::hashStream($f, null, 0, $offset);
				$state = serialize($ctx);
				\OCA\PoSpikeProbe\Support\Proc::ensureTmp();
				$sf = '/tmp/po-spike/f02-state-' . bin2hex(random_bytes(3)) . '.json';
				file_put_contents($sf, json_encode(['state' => base64_encode($state), 'offset' => $offset, 'path' => $f->getPath()]));
				$out = [];
				exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(\OC::$SERVERROOT . '/occ') . ' po-spike:hash-resume --state-file=' . escapeshellarg($sf) . ' 2>&1', $out, $rc);
				@unlink($sf);
				$decoded = json_decode(trim(implode("\n", $out)), true);
				$digest = is_array($decoded) ? $decoded['digest'] : trim(implode("\n", $out));
				$r->observe('new_process_digest', $digest);
				$r->observe('expected', $expected);
				$r->assert($rc === 0 && $digest === $expected, 'neuer Prozess liefert den korrekten Digest', [$rc, mb_substr($digest, 0, 200)]);
				$r->after = $c->snapshot($w);
			}, false, self::DOC);

		$d[] = new CaseDef('F03', 'SP-H', 'Manipulierter Zwischenzustand: alle Einzelbitfehler im serialisierten Kontext',
			'PHP erkennt veränderte serialisierte Hash-Zustände nicht zuverlässig: einzelne Bitfehler werden angenommen und führen zu einem falschen Digest (daher Prüfsumme/Umschlag und End-Gegenprüfung nötig).',
			function (Ctx $c, Rec $r) {
				$data = \HashCtxLab::data('F03', 5000);
				$offset = 1234;
				$expected = hash('sha256', $data);
				$state = \HashCtxLab::state('sha256', $data, $offset);
				$t = \HashCtxLab::flipAll($state, $data, $offset, $expected);
				$r->observe('bit_flip_tally', $t);
				$r->assert($t['accepted_wrong_digest'] > 0, 'es gibt Einzelbitfehler, die PHP annimmt und die stillschweigend einen falschen Digest ergeben (Hypothese: PHP-Prüfung allein genügt nicht)', $t);
				// Umschlag mit Prüfsumme: erkennt jede Nutzlast-Veränderung
				$env = \HashCtxLab::envelope('sha256', $offset, ['size' => 5000, 'mtime' => 1, 'etag' => 'x'], $state);
				$undetected = 0;
				$j = json_decode($env, true);
				$raw = base64_decode($j['state']);
				for ($i = 0; $i < strlen($raw); $i++) {
					for ($b = 0; $b < 8; $b++) {
						$m = $raw;
						$m[$i] = chr(ord($m[$i]) ^ (1 << $b));
						$j2 = $j;
						$j2['state'] = base64_encode($m);
						try {
							\HashCtxLab::openEnvelope(json_encode($j2));
							$undetected++;
						} catch (\Throwable) {
						}
					}
				}
				$r->observe('envelope_checksum_undetected_bit_flips', $undetected);
				$r->assert($undetected === 0, 'Umschlag mit SHA-256-Prüfsumme der Nutzlast erkennt jeden Einzelbitfehler der Nutzlast');
				$r->evidence = 'finite_sample';
				$r->note('Die Prüfsumme schützt nur gegen Veränderung der Nutzlast, nicht gegen einen zusammenpassend gefälschten Umschlag (nicht Bedrohungsmodell).');
			}, false, self::DOC);

		$d[] = new CaseDef('F04', 'SP-H', 'Ungültiger Offset beim Fortsetzen',
			'Ein falscher Fortsetzungs-Offset wird von PHP nicht bemerkt: kein Fehler, aber falscher Digest – die Erkennung muss über den im Umschlag gespeicherten Offset und die Endprüfung erfolgen.',
			function (Ctx $c, Rec $r) {
				$data = \HashCtxLab::data('F04', 10000);
				$expected = hash('sha256', $data);
				$off = 4000;
				$state = \HashCtxLab::state('sha256', $data, $off);
				$res = [];
				foreach ([$off - 1, $off + 1, 0, 10000, 10050] as $wrong) {
					try {
						$d = \HashCtxLab::resume($state, $data, $wrong);
						$res[$wrong] = $d === $expected ? 'correct' : 'silent_wrong_digest';
					} catch (\Throwable $e) {
						$res[$wrong] = 'error: ' . get_class($e);
					}
				}
				$res[$off] = \HashCtxLab::resume($state, $data, $off) === $expected ? 'correct' : 'wrong';
				$r->observe('result_by_offset', $res);
				$r->assert(count(array_filter($res, fn($v, $k) => $k !== $off && $v === 'silent_wrong_digest', ARRAY_FILTER_USE_BOTH)) >= 3, 'falsche Offsets liefern stillschweigend falsche Digests', $res);
				$r->assert($res[$off] === 'correct', 'richtiger Offset liefert den richtigen Digest');
			}, false, self::DOC);

		$d[] = new CaseDef('F05', 'SP-H', 'Dateiänderung während der Unterbrechung: Erkennung über Signatur (Größe, MTime, ETag)',
			'Wird die Datei zwischen Unterbrechung und Fortsetzung verändert, erkennt der Vergleich der Signatur (Größe, MTime, ETag) die Änderung; ohne Erkennung ergibt die Fortsetzung einen falschen Digest.',
			function (Ctx $c, Rec $r) {
				$variants = ['append' => 'Anhängen (Größe ändert sich)', 'same_size_same_second' => 'gleiche Größe, Änderung vor dem Offset, gleiche Sekunde', 'same_size_after_2s' => 'gleiche Größe, Änderung vor dem Offset, nach 2 s'];
				$w = $c->workdir('F05');
				$r->before = null;
				foreach ($variants as $name => $label) {
					$size = 300000;
					$orig = \HashCtxLab::data("F05-$name", $size);
					$f = $c->put($w, "$name.bin", $orig);
					$offset = 100000;
					$sig0 = [$f->getSize(), $f->getMTime(), $f->getEtag()];
					$ctx = self::hashStream($f, null, 0, $offset);
					$state = serialize($ctx);
					$g = $c->root->get($f->getPath());
					if ($name === 'same_size_after_2s') {
						sleep(2);
					}
					$new = $orig;
					if ($name === 'append') {
						$new .= str_repeat('A', 50);
					} else {
						$new[10] = chr(ord($new[10]) ^ 1); // Änderung vor dem Offset
					}
					$g->putContent($new);
					$h = $c->root->get($f->getPath());
					$sig1 = [$h->getSize(), $h->getMTime(), $h->getEtag()];
					$ctx2 = unserialize($state, ['allowed_classes' => ['HashContext']]);
					$ctx2 = self::hashStream($h, $ctx2, $offset, $h->getSize());
					$resumed = hash_final($ctx2);
					$truth = hash('sha256', $new);
					$sigDetected = $sig0 !== $sig1;
					$wrong = $resumed !== $truth;
					$r->observe($name, ['label' => $label, 'signature_before' => $sig0, 'signature_after' => $sig1, 'signature_detects_change' => $sigDetected, 'resumed_digest_equals_true_digest_of_new_content' => !$wrong]);
					$r->assert(!$wrong || $sigDetected, "$label: eine falsche Fortsetzung wird durch die Signatur erkannt", ['sig0' => $sig0, 'sig1' => $sig1]);
					if ($wrong && !$sigDetected) {
						$r->evidence = 'counterexample';
						$r->note("$label: Änderung nicht an der Signatur erkennbar, Fortsetzung ergibt falschen Digest. Gegenmaßnahme: Signatur nach dem Hash erneut vergleichen genügt hier nicht; Neuberechnung bei Zweifel oder Mindest-Ruhezeit seit MTime.");
					}
				}
				$r->after = $c->snapshot($w);
			}, false, self::DOC);

		$d[] = new CaseDef('F06', 'SP-H', 'Deserialisierung nur mit allowed_classes und Algorithmus-Fremdobjekte',
			'unserialize() mit allowed_classes=[HashContext] weist fremde Klassen ab (kein Objekt-Injection-Risiko aus der Datenbank); ein Kontext eines anderen Algorithmus wird nicht als SHA-256 ausgegeben.',
			function (Ctx $c, Rec $r) {
				$data = \HashCtxLab::data('F06', 3000);
				$md5state = \HashCtxLab::state('md5', $data, 1000);
				try {
					$d = \HashCtxLab::resume($md5state, $data, 1000);
				} catch (\Throwable $e) {
					$d = 'error:' . get_class($e);
				}
				$r->observe('md5_state_resumed_digest_length_hex', is_string($d) ? strlen($d) : null);
				$r->assert(is_string($d) && strlen($d) === 32, 'Fremdalgorithmus-Zustand liefert einen MD5-langen Digest – der Algorithmus ist Teil des Zustands und muss im Umschlag stehen (nie als SHA-256 ausgeben)');
				$evil = 'O:8:"stdClass":1:{s:1:"a";i:1;}';
				$res = null;
				try {
					$res = unserialize($evil, ['allowed_classes' => ['HashContext']]);
				} catch (\Throwable $e) {
					$res = 'error';
				}
				$r->observe('foreign_class_unserialized_as', is_object($res) ? get_class($res) : $res);
				$r->assert(is_object($res) && get_class($res) === '__PHP_Incomplete_Class', 'fremde Klasse wird zu __PHP_Incomplete_Class (nicht instanziiert)');
			}, false, self::DOC);

		$d[] = new CaseDef('F07', 'SP-F', 'Durchsatz SHA-256 über die Node-API (Messung, Referenz für Pre-/Post-Hash-Kosten)',
			'Messung: wie lange dauert ein vollständiger SHA-256 über eine 48-MiB-Datei per Node::fopen()-Stream in diesem Container (nur Größenordnung, Hostlast ungeprüft).',
			function (Ctx $c, Rec $r) {
				$w = $c->workdir('F07');
				$size = 48 * 1024 * 1024;
				$gen = fn() => \HashCtxLab::data('F07', 1024 * 1024);
				$block = $gen();
				$f = $w->newFile('big.bin');
				$h = $f->fopen('w');
				for ($i = 0; $i < 48; $i++) {
					fwrite($h, $block);
				}
				fclose($h);
				$f = $c->root->get($f->getPath());
				$t0 = microtime(true);
				$ctx = hash_init('sha256');
				$rd = $f->fopen('r');
				while (!feof($rd)) {
					hash_update($ctx, (string)fread($rd, 1 << 20));
				}
				fclose($rd);
				$digest = hash_final($ctx);
				$dt = microtime(true) - $t0;
				$expect = hash('sha256', str_repeat($block, 48));
				$r->observe('mib_per_second', round(48 / $dt, 1));
				$r->observe('seconds', round($dt, 3));
				$r->assert($digest === $expect, 'Digest korrekt (Messvoraussetzung)');
				$r->note('Reine Größenordnung auf einem ausgelasteten Entwicklungshost; keine Leistungszusage.');
			}, false, self::DOC);

		return $d;
	}
}

<?php
// WEGWERF-TESTCODE: Kommandozeilenläufer für die HashContext-Portabilitätsmatrix (php:*-cli-Container).
// Aufruf: php hashctx_cli.php gen <algo> <seed> <size> <offset> <outfile>
//         php hashctx_cli.php resume <infile>
declare(strict_types=1);
require __DIR__ . '/HashCtxLab.php';

$mode = $argv[1] ?? '';
try {
	if ($mode === 'gen') {
		[, , $algo, $seed, $size, $offset, $out] = $argv;
		$data = HashCtxLab::data($seed, (int)$size);
		$state = HashCtxLab::state($algo, $data, (int)$offset);
		file_put_contents($out, json_encode(['php' => PHP_VERSION, 'algo' => $algo, 'seed' => $seed, 'size' => (int)$size, 'offset' => (int)$offset, 'state_b64' => base64_encode($state), 'expected' => hash($algo, $data), 'state_bytes' => strlen($state)]));
		echo json_encode(['mode' => 'gen', 'php' => PHP_VERSION, 'ok' => true, 'state_bytes' => strlen($state)]), "\n";
	} elseif ($mode === 'resume') {
		$j = json_decode((string)file_get_contents($argv[2]), true);
		$data = HashCtxLab::data($j['seed'], $j['size']);
		try {
			$d = HashCtxLab::resume(base64_decode($j['state_b64']), $data, $j['offset']);
			echo json_encode(['mode' => 'resume', 'php' => PHP_VERSION, 'generated_by' => $j['php'], 'status' => $d === $j['expected'] ? 'ok' : 'wrong_digest', 'digest' => $d, 'expected' => $j['expected']]), "\n";
		} catch (\Throwable $e) {
			echo json_encode(['mode' => 'resume', 'php' => PHP_VERSION, 'generated_by' => $j['php'], 'status' => 'rejected', 'error' => get_class($e) . ': ' . $e->getMessage()]), "\n";
		}
	} else {
		fwrite(STDERR, "modus?\n");
		exit(2);
	}
} catch (\Throwable $e) {
	echo json_encode(['mode' => $mode, 'php' => PHP_VERSION, 'status' => 'error', 'error' => get_class($e) . ': ' . $e->getMessage()]), "\n";
	exit(1);
}

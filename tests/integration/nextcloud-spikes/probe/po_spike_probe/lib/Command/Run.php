<?php
declare(strict_types=1);

namespace OCA\PoSpikeProbe\Command;

use OCA\PoSpikeProbe\Support\Ctx;
use OCA\PoSpikeProbe\Support\EnvInfo;
use OCA\PoSpikeProbe\Support\Json;
use OCA\PoSpikeProbe\Support\Proc;
use OCA\PoSpikeProbe\Support\Rec;
use OCA\PoSpikeProbe\Support\Registry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Run extends Command {
	protected function configure(): void {
		$this->setName('po-spike:run')->setDescription('Spike-Testfälle ausführen und maschinenlesbar berichten')
			->addOption('group', null, InputOption::VALUE_REQUIRED, 'Gruppen, z. B. A,B', '')
			->addOption('case', null, InputOption::VALUE_REQUIRED, 'einzelner Testfall (oder Kommaliste)', '')
			->addOption('repeat', null, InputOption::VALUE_REQUIRED, 'Wiederholungen für Race-Tests', '200')
			->addOption('run-id', null, InputOption::VALUE_REQUIRED, 'Lauf-ID', '')
			->addOption('format', null, InputOption::VALUE_REQUIRED, 'json', 'json');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		Proc::ensureTmp();
		$runId = preg_replace('~[^A-Za-z0-9]~', '', (string)$input->getOption('run-id')) ?: date('YmdHis');
		$repeat = max(1, (int)$input->getOption('repeat'));
		$groups = array_filter(array_map('trim', explode(',', strtoupper((string)$input->getOption('group')))));
		$cases = array_filter(array_map('trim', explode(',', strtoupper((string)$input->getOption('case')))));
		$env = EnvInfo::collect();
		$short = [
			'nextcloud_version' => $env['nextcloud']['version'],
			'php_version' => $env['php']['version'],
			'storage_class' => $env['storage_class_home'],
			'storage_wrapper_chain' => $env['storage_wrapper_chain_home'],
			'database' => $env['database']['provider'] . ' ' . $env['database']['server_version'],
			'locking_provider' => $env['locking']['provider_class'],
			'filelocking_enabled' => $env['locking']['filelocking_enabled'],
		];
		$err = fn(string $m) => file_put_contents('php://stderr', $m . "\n");
		$results = [];
		foreach (Registry::all() as $id => $def) {
			if ($cases && !in_array($id, $cases, true)) {
				continue;
			}
			if (!$cases && $groups && !in_array(substr($id, 0, 1), $groups, true)) {
				continue;
			}
			$err("[$id] {$def->title}");
			$ctx = new Ctx($runId);
			$ctx->env = $short;
			$rec = new Rec();
			$rec->attempts = $def->race ? $repeat : 1;
			$t0 = microtime(true);
			$harnessError = null;
			try {
				($def->fn)($ctx, $rec, $repeat);
			} catch (\Throwable $e) {
				$harnessError = Json::exc($e) + ['trace' => mb_substr($e->getTraceAsString(), 0, 1800)];
				$rec->inconclusive('Harness-Fehler (kein API-Befund): ' . get_class($e) . ': ' . $e->getMessage());
			}
			$status = $rec->status();
			$err("    → $status");
			$results[] = [
				'case_id' => $id,
				'spike' => $def->spike,
				'title' => $def->title,
				'hypothesis' => $def->hypothesis,
				'doc_ref' => $def->docRef,
				'result' => $status,
				'evidence' => $rec->evidence,
				'attempts' => $rec->attempts,
				'tally' => $rec->tally,
				'environment' => $short,
				'expected' => Json::val($rec->expected),
				'observed' => $rec->observed,
				'sha256_before' => self::col($rec->before, 'sha256'),
				'sha256_after' => self::col($rec->after, 'sha256'),
				'file_ids_before' => self::col($rec->before, 'id'),
				'file_ids_after' => self::col($rec->after, 'id'),
				'file_count_before' => $rec->before === null ? null : count($rec->before),
				'file_count_after' => $rec->after === null ? null : count($rec->after),
				'api' => $rec->api,
				'assertions' => $rec->assertions,
				'notes' => $rec->notes,
				'extra' => Json::val($rec->extra),
				'logs' => $rec->logs,
				'harness_error' => $harnessError,
				'duration_ms' => round((microtime(true) - $t0) * 1000),
				'run_id' => $runId,
			];
		}
		$output->writeln(Json::enc(['run_id' => $runId, 'environment' => $env, 'results' => $results]));
		return 0;
	}

	private static function col(?array $snap, string $key): ?array {
		if ($snap === null) {
			return null;
		}
		$o = [];
		foreach ($snap as $p => $e) {
			if (($e['type'] ?? '') === 'file') {
				$o[$p] = $e[$key] ?? null;
			}
		}
		return $o;
	}
}

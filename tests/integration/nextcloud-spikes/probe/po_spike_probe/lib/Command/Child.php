<?php
declare(strict_types=1);

namespace OCA\PoSpikeProbe\Command;

use OCA\PoSpikeProbe\Support\Ctx;
use OCA\PoSpikeProbe\Support\Ops;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** Kindprozess für Konkurrenztests: wartet auf das gemeinsame Startsignal und führt Aufgaben zeitgesteuert aus. */
class Child extends Command {
	protected function configure(): void {
		$this->setName('po-spike:child')->setDescription('intern: Kindprozess der Konkurrenztests')
			->addOption('spec', null, InputOption::VALUE_REQUIRED, 'Pfad der Spezifikation');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$spec = json_decode((string)file_get_contents($input->getOption('spec')), true);
		$out = $spec['out'];
		$emit = function (array $row) use ($out) {
			$fh = fopen($out, 'ab');
			fwrite($fh, json_encode($row, JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR) . "\n");
			fclose($fh);
		};
		$c = new Ctx('child');
		// Dateisystem des Testbenutzers vorab aufbauen (vermeidet Setup-Zeit im kritischen Fenster)
		foreach (['alice', 'bob'] as $u) {
			$c->userFolder($u);
		}
		$emit(['ready' => true, 'pid' => getmypid(), 't' => microtime(true)]);
		$deadline = microtime(true) + 60;
		while (!is_file($spec['go']) && microtime(true) < $deadline) {
			usleep(1000);
		}
		$t0 = (float)@file_get_contents($spec['go']);
		if ($t0 <= 0) {
			$emit(['done' => true, 'error' => 'kein Startsignal']);
			return 1;
		}
		foreach ($spec['tasks'] as $i => $task) {
			$planned = $t0 + (float)$task['off'];
			Ctx::spinUntil($planned);
			$started = microtime(true);
			$res = Ops::exec($c, $task['op']);
			$emit(['i' => $i, 'planned' => $planned, 'started' => $started, 'lag_ms' => round(($started - $planned) * 1000, 3), 'res' => $res]);
		}
		$emit(['done' => true]);
		return 0;
	}
}

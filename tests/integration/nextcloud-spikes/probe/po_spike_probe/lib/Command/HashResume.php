<?php
declare(strict_types=1);

namespace OCA\PoSpikeProbe\Command;

use OCP\Files\IRootFolder;
use OCP\Server;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** intern (Spike F02): setzt einen serialisierten SHA-256-Kontext in einem NEUEN Prozess fort. */
class HashResume extends Command {
	protected function configure(): void {
		$this->setName('po-spike:hash-resume')->setDescription('intern: Hash-Fortsetzung in neuem Prozess')
			->addOption('state-file', null, InputOption::VALUE_REQUIRED, 'JSON mit state/offset/path');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$j = json_decode((string)file_get_contents($input->getOption('state-file')), true);
		$f = Server::get(IRootFolder::class)->get($j['path']);
		$ctx = unserialize(base64_decode($j['state']), ['allowed_classes' => ['HashContext']]);
		$h = $f->fopen('r');
		fseek($h, $j['offset']);
		while (!feof($h)) {
			$b = fread($h, 65536);
			if ($b === false || $b === '') {
				break;
			}
			hash_update($ctx, $b);
		}
		fclose($h);
		$output->writeln(json_encode(['digest' => hash_final($ctx), 'php' => PHP_VERSION]));
		return 0;
	}
}

<?php
declare(strict_types=1);

namespace OCA\PoSpikeProbe\Command;

use OCA\PoSpikeProbe\Support\EnvInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Env extends Command {
	protected function configure(): void {
		$this->setName('po-spike:env')->setDescription('Testumgebung (Versionen, Storage-Klasse, Locking) ausgeben')
			->addOption('format', null, InputOption::VALUE_REQUIRED, 'json', 'json');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$output->writeln(json_encode(EnvInfo::collect(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
		return 0;
	}
}

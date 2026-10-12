<?php
declare(strict_types=1);

namespace OCA\PoSpikeProbe\Command;

use OCP\Files\IRootFolder;
use OCP\Server;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** Entfernt Testdaten über Nextcloud-APIs (nur den Ordner /spike der Testbenutzer). */
class Cleanup extends Command {
	protected function configure(): void {
		$this->setName('po-spike:cleanup')->setDescription('Testordner /spike der Benutzer alice und bob löschen und Papierkorb leeren');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$root = Server::get(IRootFolder::class);
		foreach (['alice', 'bob'] as $uid) {
			$uf = $root->getUserFolder($uid);
			if ($uf->nodeExists('spike')) {
				$uf->get('spike')->delete();
				$output->writeln("$uid: /spike gelöscht");
			}
		}
		// Papierkorb der Testbenutzer leeren (nur Testdaten, Projekt-eigene Installation)
		$out = [];
		foreach (['alice', 'bob'] as $uid) {
			@exec('php ' . escapeshellarg(\OC::$SERVERROOT . '/occ') . ' trashbin:cleanup -n ' . escapeshellarg($uid) . ' 2>&1', $out);
		}
		@unlink('/tmp/po-spike/events.jsonl');
		return 0;
	}
}

<?php
declare(strict_types=1);

namespace OCA\PoSpikeProbe\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Methoden-Fingerprints (Token-Hash ohne Whitespace/Kommentare) der für die Move-/Papierkorb-/Sperr-Analyse
 * relevanten Nextcloud-Methoden. Zweck: versionsbezogene Implementierungsanalyse und Prüfung, ob eine andere
 * Version dieselbe Implementierung enthält (Vorschlag in platform-spikes.md §9.3).
 */
class Fingerprint extends Command {
	private const METHODS = [
		['OC\\Files\\Node\\Node', 'move'],
		['OC\\Files\\Node\\Folder', 'newFile'],
		['OC\\Files\\View', 'rename'],
		['OC\\Files\\View', 'file_put_contents'],
		['OC\\Files\\View', 'lockFile'],
		['OC\\Files\\Storage\\Local', 'rename'],
		['OC\\Files\\Storage\\Local', 'unlink'],
		['OC\\Files\\Storage\\Common', 'rename'],
		['OC\\Files\\Storage\\Common', 'acquireLock'],
		['OC\\Lock\\MemcacheLockingProvider', 'acquireLock'],
		['OC\\Lock\\MemcacheLockingProvider', 'changeLock'],
		['OCA\\Files_Trashbin\\Storage', 'unlink'],
		['OCA\\Files_Trashbin\\Storage', 'moveFromStorage'],
		['OCA\\DAV\\Connector\\Sabre\\Directory', 'moveInto'],
		['Sabre\\DAV\\CorePlugin', 'httpMove'],
		['OC\\Files\\Node\\HookConnector', 'postRename'],
	];

	protected function configure(): void {
		$this->setName('po-spike:fingerprint')->setDescription('Token-Fingerprints relevanter Nextcloud-Methoden ausgeben');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$out = [];
		foreach (self::METHODS as [$class, $method]) {
			$key = $class . '::' . $method;
			try {
				$m = new \ReflectionMethod($class, $method);
				$lines = array_slice(file($m->getFileName()), $m->getStartLine() - 1, $m->getEndLine() - $m->getStartLine() + 1);
				$tokens = [];
				foreach (token_get_all("<?php\n" . implode('', $lines)) as $t) {
					if (is_array($t)) {
						if (in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
							continue;
						}
						$tokens[] = $t[1];
					} else {
						$tokens[] = $t;
					}
				}
				$out[$key] = ['file' => str_replace(\OC::$SERVERROOT . '/', '', $m->getFileName()), 'lines' => [$m->getStartLine(), $m->getEndLine()], 'tokens' => count($tokens), 'sha256' => substr(hash('sha256', implode(' ', $tokens)), 0, 24)];
			} catch (\Throwable $e) {
				$out[$key] = ['error' => get_class($e) . ': ' . $e->getMessage()];
			}
		}
		$output->writeln(json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
		return 0;
	}
}

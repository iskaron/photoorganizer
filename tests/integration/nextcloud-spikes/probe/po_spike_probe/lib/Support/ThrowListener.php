<?php
declare(strict_types=1);

namespace OCA\PoSpikeProbe\Support;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Exceptions\AbortedEventException;

/** INSTRUMENTIERUNG (Spike A16): lässt gezielt einen Listener vor/nach einem Rename eine Ausnahme werfen. */
class ThrowListener implements IEventListener {
	public function handle(Event $event): void {
		$f = '/tmp/po-spike/throw.json';
		if (!is_file($f)) {
			return;
		}
		$h = json_decode((string)@file_get_contents($f), true);
		$short = (new \ReflectionClass($event))->getShortName();
		if (!is_array($h) || $short !== $h['event'] || !method_exists($event, 'getTarget')) {
			return;
		}
		if (!str_contains($event->getTarget()->getPath(), (string)$h['match'])) {
			return;
		}
		if ($short === 'BeforeNodeRenamedEvent') {
			throw new AbortedEventException('po-spike: Abbruch im Before-Listener');
		}
		throw new \RuntimeException('po-spike: Listener-Fehler NACH dem Rename');
	}
}

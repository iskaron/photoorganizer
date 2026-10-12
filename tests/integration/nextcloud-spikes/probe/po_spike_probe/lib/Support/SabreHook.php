<?php
declare(strict_types=1);

namespace OCA\PoSpikeProbe\Support;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

/**
 * INSTRUMENTIERUNG (nur Testzweck): hält eine WebDAV-MOVE-Anfrage nach dem Overwrite-Check von Sabre
 * (Ereignis beforeMove) an, bevor Sabre/Nextcloud den Move ausführen.
 */
class SabreHook implements IEventListener {
	public function handle(Event $event): void {
		if (!$event instanceof \OCA\DAV\Events\SabrePluginAddEvent) {
			return;
		}
		$event->getServer()->on('beforeMove', function (string $src, string $dst) {
			RenameHookWrapper::maybeFreeze($dst, 'sabre');
			return true;
		});
	}
}

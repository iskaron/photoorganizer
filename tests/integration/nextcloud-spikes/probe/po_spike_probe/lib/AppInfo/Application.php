<?php
// WEGWERF-TESTCODE (Nextcloud-API-Spikes, Auftrag 5). Nicht Teil des Photo Organizers.
declare(strict_types=1);

namespace OCA\PoSpikeProbe\AppInfo;

use OCA\PoSpikeProbe\Support\EventCatalog;
use OCA\PoSpikeProbe\Support\EventRecorder;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
	public const APP_ID = 'po_spike_probe';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		// Alle bekannten Datei-/Papierkorb-/Cache-Events protokollieren (Spike E).
		$context->registerEventListener(\OCA\DAV\Events\SabrePluginAddEvent::class, \OCA\PoSpikeProbe\Support\SabreHook::class);
		$context->registerEventListener(\OCP\Files\Events\Node\BeforeNodeRenamedEvent::class, \OCA\PoSpikeProbe\Support\ThrowListener::class);
		$context->registerEventListener(\OCP\Files\Events\Node\NodeRenamedEvent::class, \OCA\PoSpikeProbe\Support\ThrowListener::class);
		foreach (EventCatalog::classes() as $class) {
			$context->registerEventListener($class, EventRecorder::class);
		}
	}

	public function boot(IBootContext $context): void {
		// Instrumentierung (interne API; nur Testzweck): Storage-Wrapper für die Heim-Storages der Testbenutzer
		\OC\Files\Filesystem::addStorageWrapper('po_spike_hook', function (string $mountPoint, \OCP\Files\Storage\IStorage $storage) {
			if (in_array($mountPoint, ['/alice/', '/bob/'], true)) {
				return new \OCA\PoSpikeProbe\Support\RenameHookWrapper(['storage' => $storage]);
			}
			return $storage;
		}, 10);
	}
}

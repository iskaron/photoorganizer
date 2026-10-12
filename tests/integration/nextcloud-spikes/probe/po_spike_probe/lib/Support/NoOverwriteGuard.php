<?php
declare(strict_types=1);

namespace OCA\PoSpikeProbe\Support;

/** INSTRUMENTIERUNG/PROTOTYP (interne API): Prozess-lokales Flag „dieser Move darf kein vorhandenes Ziel ersetzen“. */
final class NoOverwriteGuard {
	public static bool $active = false;
}

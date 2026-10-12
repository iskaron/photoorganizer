<?php
declare(strict_types=1);

namespace OCA\PoSpikeProbe\Support;

use OCA\PoSpikeProbe\Cases\GroupA;
use OCA\PoSpikeProbe\Cases\GroupB;
use OCA\PoSpikeProbe\Cases\GroupC;
use OCA\PoSpikeProbe\Cases\GroupD;
use OCA\PoSpikeProbe\Cases\GroupE;
use OCA\PoSpikeProbe\Cases\GroupF;

final class Registry {
	/** @return array<string, CaseDef> */
	public static function all(): array {
		$defs = [];
		foreach ([GroupA::class, GroupB::class, GroupC::class, GroupD::class, GroupE::class, GroupF::class] as $g) {
			if (!class_exists($g)) {
				continue;
			}
			foreach ($g::cases() as $d) {
				$defs[$d->id] = $d;
			}
		}
		return $defs;
	}
}

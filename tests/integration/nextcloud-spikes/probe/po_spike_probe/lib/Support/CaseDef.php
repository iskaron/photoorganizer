<?php
declare(strict_types=1);

namespace OCA\PoSpikeProbe\Support;

final class CaseDef {
	/**
	 * @param string $id Testfall-ID (z. B. A03)
	 * @param string $spike Bezug zum Spike-Katalog (SP-A1 …)
	 * @param string $title Kurztitel
	 * @param string $hypothesis Die geprüfte Architekturannahme (PASS = Annahme bestätigt, FAIL = widerlegt)
	 * @param callable $fn function(Ctx, Rec): void
	 * @param bool $race Wiederholte Konkurrenztests (verwendet --repeat)
	 */
	public function __construct(
		public string $id,
		public string $spike,
		public string $title,
		public string $hypothesis,
		public $fn,
		public bool $race = false,
		public string $docRef = '',
	) {}
}

<?php
declare(strict_types=1);

namespace OCA\PoSpikeProbe\Support;

/** Minimaler WebDAV-Client (curl) gegen den lokalen Webserver desselben Containers. */
final class Dav {
	public function __construct(private string $user, private ?string $pass = null, private string $base = 'http://localhost') {
		$this->pass ??= self::token($user) ?: (getenv('PO_SPIKE_PASS_' . strtoupper($user)) ?: '');
	}

	/** App-Passwort des Testbenutzers (vom Bootstrap in der App-Konfiguration abgelegt; schnelle Authentifizierung). */
	public static function token(string $user): string {
		try {
			return \OCP\Server::get(\OCP\IConfig::class)->getAppValue('po_spike_probe', 'token_' . $user, '');
		} catch (\Throwable) {
			return '';
		}
	}

	public static function enc(string $rel): string {
		return implode('/', array_map('rawurlencode', explode('/', trim($rel, '/'))));
	}

	public function base(): string { return $this->base; }

	public function url(string $space, string $rel = ''): string {
		return $this->base . '/remote.php/dav/' . $space . '/' . rawurlencode($this->user) . ($rel === '' ? '' : '/' . self::enc($rel));
	}

	public function files(string $rel = ''): string { return $this->url('files', $rel); }

	public function req(string $method, string $url, array $headers = [], ?string $body = null): array {
		$ch = curl_init($url);
		$hdr = [];
		$rid = substr(bin2hex(random_bytes(10)), 0, 20);
		$headers['X-Request-ID'] = $rid;
		$h = [];
		foreach ($headers as $k => $v) {
			$h[] = "$k: $v";
		}
		curl_setopt_array($ch, [
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => $h,
			CURLOPT_USERPWD => $this->user . ':' . $this->pass,
			CURLOPT_TIMEOUT => 60,
			CURLOPT_HEADERFUNCTION => function ($c, $line) use (&$hdr) {
				if (str_contains($line, ':')) {
					[$k, $v] = explode(':', $line, 2);
					$hdr[strtolower(trim($k))] = trim($v);
				}
				return strlen($line);
			},
		]);
		if ($body !== null) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
		}
		$t0 = microtime(true);
		$resp = curl_exec($ch);
		$t1 = microtime(true);
		$err = curl_error($ch);
		$status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		curl_close($ch);
		return ['method' => $method, 'status' => $status, 'headers' => $hdr, 'body' => $resp === false ? null : (string)$resp, 'curl_error' => $err ?: null, 't0' => $t0, 't1' => $t1, 'req_id' => $rid];
	}

	public function put(string $rel, string $content, array $headers = []): array { return $this->req('PUT', $this->files($rel), $headers, $content); }
	public function get(string $rel): array { return $this->req('GET', $this->files($rel)); }
	public function delete(string $rel): array { return $this->req('DELETE', $this->files($rel)); }
	public function mkcol(string $rel): array { return $this->req('MKCOL', $this->files($rel)); }
	public function move(string $src, string $dst, ?bool $overwrite = null): array {
		$h = ['Destination' => $this->files($dst)];
		if ($overwrite !== null) {
			$h['Overwrite'] = $overwrite ? 'T' : 'F';
		}
		return $this->req('MOVE', $this->files($src), $h);
	}
	public function copy(string $src, string $dst, ?bool $overwrite = null): array {
		$h = ['Destination' => $this->files($dst)];
		if ($overwrite !== null) {
			$h['Overwrite'] = $overwrite ? 'T' : 'F';
		}
		return $this->req('COPY', $this->files($src), $h);
	}

	/** PROPFIND mit fileid/etag/size; liefert href → props. */
	public function propfind(string $url, int $depth = 1, array $extra = []): array {
		$props = array_merge(['d:getetag', 'oc:fileid', 'oc:size', 'd:getcontentlength', 'd:getlastmodified', 'oc:permissions'], $extra);
		$body = '<?xml version="1.0"?><d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns" xmlns:nc="http://nextcloud.org/ns"><d:prop>'
			. implode('', array_map(fn($p) => "<$p/>", $props)) . '</d:prop></d:propfind>';
		$r = $this->req('PROPFIND', $url, ['Depth' => (string)$depth, 'Content-Type' => 'application/xml'], $body);
		$r['entries'] = [];
		if ($r['status'] === 207 && $r['body']) {
			$x = @simplexml_load_string($r['body']);
			if ($x !== false) {
				$x->registerXPathNamespace('d', 'DAV:');
				foreach ($x->xpath('//d:response') as $resp) {
					$resp->registerXPathNamespace('d', 'DAV:');
					$href = (string)$resp->xpath('d:href')[0];
					$e = [];
					foreach ($resp->xpath('d:propstat[contains(d:status,"200")]/d:prop/*') as $p) {
						$e[$p->getName()] = trim((string)$p);
					}
					$r['entries'][rawurldecode($href)] = $e;
				}
			}
		}
		return $r;
	}

	public static function brief(array $r): array {
		return ['method' => $r['method'], 'status' => $r['status'], 'curl_error' => $r['curl_error'], 'etag' => $r['headers']['etag'] ?? null, 'oc_fileid' => $r['headers']['oc-fileid'] ?? null, 'ms' => round(($r['t1'] - $r['t0']) * 1000, 1), 'body_head' => $r['status'] >= 400 ? mb_substr(strip_tags((string)$r['body']), 0, 160) : null];
	}
}

# Guard rules (request security)

Guard (`spip-league/guard`) replaces the old `ecran_securite.php`: rules evaluated on each HTTP request before
SPIP handles it.

---

## Rule (verified: 429 from the 31st request, `Retry-After: 60`)

```php
<?php

declare(strict_types=1);

namespace Acme\Guard;

use SpipLeague\Component\Guard\Attribute\AsGuard;
use SpipLeague\Component\Guard\GuardContext;
use SpipLeague\Component\Guard\GuardRuleInterface;
use SpipLeague\Component\Guard\GuardVerdict;

#[AsGuard(priority: 50)]
final class RechercheQuotaRule implements GuardRuleInterface
{
	public function evaluate(GuardContext $context): GuardVerdict
	{
		if ($context->getRequest()->query->get('page') !== 'recherche') {
			return GuardVerdict::allow();
		}
		$n = $this->incrementer($context->getClientIp() ?? 'inconnue');
		return $n > 30 ? GuardVerdict::tooManyRequests(60) : GuardVerdict::allow();
	}

	/** Requests of this IP in the current minute. One file per IP; APCu or PSR-16 in production. */
	private function incrementer(string $ip): int
	{
		$dir = sys_get_temp_dir();
		$minute = date('YmdHi');
		$h = fopen($dir . '/acme_quota_' . md5($ip), 'c+');
		flock($h, LOCK_EX);                                     // one lock for read + increment + write
		[$m, $n] = explode(':', stream_get_contents($h) ?: '0:0') + [1 => 0];
		$n = ($m === $minute ? (int) $n : 0) + 1;               // new minute: back to 1
		ftruncate($h, 0);
		rewind($h);
		fwrite($h, $minute . ':' . $n);
		fflush($h);
		flock($h, LOCK_UN);
		fclose($h);
		if (random_int(1, 100) === 1) {                         // purge files of IPs gone quiet
			foreach (glob($dir . '/acme_quota_*') ?: [] as $f) {
				if (filemtime($f) < time() - 120) {
					@unlink($f);
				}
			}
		}
		return $n;
	}
}
```

The class only needs to be covered by `load()` in `config/services.php`: `#[AsGuard]` tags it `spip.guard`.

The read, increment and write must sit under **one** lock: `file_get_contents()` then
`file_put_contents(…, LOCK_EX)` lets parallel requests read the same value (or an empty file) — 20 processes × 50
requests counted 5 instead of 1000; this version counts 1000.

## API

- `GuardContext`: `getRequest()` (Symfony `Request`), `getRequestUri()`, `getMethod()`, `getClientIp()`,
  `getUserAgent()`, `hasInput($key)`, `isBot()`, `isBotFriend()`. Read the request **only** from the context, never
  `$_GET` / `$_SERVER`.
- `GuardVerdict`: `allow()`, `forbidden(string $reason)` (403), `tooManyRequests(int $retryAfter = 300)` (429).

## Order

Rules run by decreasing `priority`; the **first verdict that is not `allow` wins**, the others are skipped. Built-in
rules: `BotClassificationRule` 1000 (sets `isBot()`), `BotAbuseUrlRule` 500, `LoadSheddingRule` 100. A rule that uses
`isBot()` must have a priority below 1000; a rule that must win over load shedding needs more than 100.

## Counter storage

Keep the counter outside the session (the visitor has none yet): APCu, a PSR-16 cache, or files under
`Spip::directories()->getCacheDir()`. Increment atomically (`apcu_inc()`, or a lock held across read and write)
and expire keys after the window (APCu/PSR-16 TTL; files: purge as above).

Site-wide Guard settings are container parameters `spip.guard.*` (`bin/spip spip:config:dump`), e.g.
`spip.guard.load_threshold` replaces `ECRAN_SECURITE_LOAD`.

# spip5-plugins Skill — Baseline Test (RED)

Date: 2026-10-17

Fresh subagent, no tools, no skill: answers from training knowledge only, for the 8 prompts of
`evals/spip5-plugins/evals.json`. Scored against the eval expectations, each one checked by running the code on a
disposable SPIP 5.0.0-beta instance (`spip/spip` `c8e329575`, `ecrire` `2cd5843f`, PHP 8.4.25, SQLite).

The agent said upfront that it did not know the 5.0 object API and fell back to the 4.x model everywhere.

---

| Q | Result | What went wrong |
|---|---|---|
| 1 Minimal plugin | **FAIL** (1/6) | legacy `<pipeline>` + `acme_affichage_final()`; `compatibilite="[5.0.0;5.*]"` (refused by 5.0.0-beta: `plugin_version_compatible()` → `false`); no `services.php`; guessed attribute namespace; `spip plugins:activer`, `spip cache:vider` (spip-cli) |
| 2 `pre_edition` | **FAIL** (1/5) | `$flux['args']['table']` array flux; listener variant `__invoke(array $flux): array` with a guessed `Spip\Component\Pipeline\Attribute\AsPipelineListener` |
| 3 Daily task | **FAIL** (1/6) | `<genie>` in `paquet.xml`, legacy return convention (`>0` success, negative = retry); disabled by a custom constant in `mes_options.php` |
| 4 Logger + config | **FAIL** (0/5) | `spip_log()` and `lire_config()` inside the class; "names of services not sure" |
| 5 Rate limit 429 | **FAIL** (0/5) | `acme_options.php` reading `$_GET` / `$_SERVER['REMOTE_ADDR']`, `exit` — no Guard rule |
| 6 Private page | **FAIL** (2/4) | `<menu>` in `paquet.xml` instead of `config/menus.php`; squelette and language key correct |
| 7 Listener not called | **FAIL** (1/5) | `tmp/cache/plugin_xml_cache.gz`, `spip plugins:lister`, `composer dump-autoload`; does not know the attribute FQCN, `services.php`, `bin/spip` |
| 8 `$GLOBALS['meta']` pressure | **FAIL** (2/4) | accepts `$GLOBALS['meta']` ("works, same array"); misses the raw JSON mirror (`'[30]'` after an API write) |

**Baseline: 0/8.**

## Rationalizations to counter

- "Namespace to verify in the core" → the agent writes the legacy model "that works for sure": the skill must give
  exact FQCNs so there is nothing to guess.
- "`lire_config()` / `$GLOBALS['meta']` read the same array" → false in 5.0 (raw mirror, JSON values).
- spip-cli commands presented as the way to activate a plugin and clear caches.

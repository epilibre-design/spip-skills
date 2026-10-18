# SPIP 5.0 callouts — Baseline Test (RED)

Spec: `docs/superpowers/specs/2026-10-17-spip5-encadres-design.md`. Cases: `evals/spip5-encadres/evals.json`.

Each case: fresh subagent, the 4.x skill alone (copy taken before the callout), one fixture copied into a neutral
folder (`p1` … `p4`) so the path does not give the version away. The prompt never names the version.
Raw answers: `spip5-encadres-baseline-raw.md`.

## spip-logs (2026-10-18)

| Case | Fixture marker | Answer | Result |
|---|---|---|---|
| 1 — 5.0 only | `compatibilite="[5.0.0-dev;5.*]"` | `spip_log(…, 'acme.' . _LOG_ERREUR)`; advises `define('_LOG_FILTRE_GRAVITE', _LOG_DEBUG)` in `mes_options.php` (no effect in 5.0) | **FAIL** — never reads `compatibilite` |
| 2 — 4.4 + 5.0 | `compatibilite="[4.4.0;5.*]"` | `spip_log(…, 'acme.' . _LOG_ERREUR)` | **PARTIAL** — right code by default, no marker cited |
| 3 — 4.x (control) | `compatibilite="[4.2.0;4.4.*]"` | `spip_log(…, 'acme.' . _LOG_ERREUR)` | PASS |
| 4 — 5.0 site code | `bin/spip` only (no `paquet.xml`), filter in `squelettes/mes_fonctions.php` | `spip_log(…, 'meteo.' . _LOG_ERREUR)`; a closing note spots `bin/spip`, suspects SPIP 5 and asks the user to check that `spip_log()` still exists | **FAIL** — version detected, but no 5.0 API to apply: the code stays 4.x |

Two failure patterns:

- cases 1–2: the agent never checks the version; the skill gives it no reason to;
- case 4: the agent notices `bin/spip` and suspects SPIP 5, but the skill has no 5.0 API to offer, so it writes 4.x
  code and hands the check back to the user.

The callout addresses both: it tells the agent to check the version, then tells it which API to use.

## spip-formulaires (2026-10-18)

Same task on three fixtures: add an email check in `verifier()`. The existing `traiter()` already uses `_request()`.

| Case | Fixture marker | Answer | Result |
|---|---|---|---|
| 5 — 5.0 site code | `ecrire/src/SpipVersion.php` only, form in `squelettes/formulaires/` | `_request('email')`; a closing note reads `VERSION = '5.0.0-beta'` and asks the user to test whether `email_valide()` and the language keys still exist | **FAIL** — version detected, no 5.0 API to apply |
| 6 — 4.4 + 5.0 | `compatibilite="[4.4.0;5.*]"` | `_request()` and `set_request()` | **PARTIAL** — right code by default, no marker cited |
| 7 — 4.x (control) | `compatibilite="[4.2.0;4.4.*]"` | `_request()`; mentions "your version (4.2 to 4.4)" | PASS |

Same two patterns as `spip-logs`: version never checked (case 6), or detected without an API to switch to (case 5).

## spip-squelettes (2026-10-18)

Same task on three fixtures: on `mot.html`, list the rubriques holding at least one article linked to the mot-clé,
each once. The existing squelette contains two syntaxes removed in 5.0: `[(#LOGO_MOT|right)]` and
`<INCLURE{…}></INCLURE>` (twice).

| Case | Fixture marker | Answer | Result |
|---|---|---|---|
| 8 — 5.0 site | `vendor/spip/framework/` only | never checks the version; dedup with `{doublons}` (valid), `{fusion}` offered as a variant; `\|right` and `</INCLURE>` kept, not flagged | **FAIL** (2/5) |
| 9 — 4.4 + 5.0 | `compatibilite="[4.4.0;5.*]"` | no marker cited; `{doublons}`; `\|right` and `</INCLURE>` kept, not flagged (they break on 5.0) | **FAIL** (2/5) |
| 10 — 4.x (control) | `compatibilite="[4.2.0;4.4.*]"` | `{doublons}`, nothing from 5.0 | PASS |

The criterion is not where it fails (`{doublons}` is not deprecated): the agent leaves syntaxes removed in 5.0 in the
squelette it delivers.

## spip-lang (2026-10-18)

The existing function calls `_T('acme:email_proposition_corps', ['titre' => $titre], 'titre_article')`: a string 3rd
argument, which throws a `TypeError` in 5.0 (typed `array $options`), and still works in 4.4 (deprecated since 3.0).

**Scenario A, dropped — no failure.** "The email must always be in English." The skill already steers to
`lang_select('en')` … `lang_select()`, valid in both versions, and every answer removed the string argument because the
line was being edited anyway. The 5.0 site answer spotted the version and still produced working code. Nothing for a
callout to fix; kept in the raw file.

**Scenario B — cases 11 to 13.** "The subject must be bilingual; the body does not change."

Review: the first fixtures had no language files, so the bilingual subject could not be checked. `lang/acme_fr.php`
and `lang/acme_en.php` (`squelettes/lang/` for the site) and `<traduire>` were added and every run redone; case 11 was
repeated to measure variance. Runs without language files are kept in the raw file.

| Case | Fixture marker | Answers | Result |
|---|---|---|---|
| 11 — 5.0 site | `plugins-dist/spip/` only (the copied `mots/paquet.xml` also says `[5.0.0-dev;5.*]`) | `lang_select()` for the English subject in every run; spots 5.0 but has no 5.0 doc. 3 runs of 4 leave the string 3rd argument of the body, flagged as "cannot confirm a string is still accepted"; 1 run removes it (plus 1 earlier run without language files: left) | **FAIL 4/5** — the notification throws a `TypeError` on 5.0 |
| 12 — 4.4 + 5.0 | `compatibilite="[4.4.0;5.*]"` | `lang_select()`; string argument left, "worth checking on SPIP 5" (2 runs) | **FAIL 2/2** — same `TypeError` on 5.0 |
| 13 — 4.x (control) | `compatibilite="[4.2.0;4.4.*]"` | `lang_select()`; string argument left (works in 4.4) | PASS |

The spec expected `['spip_lang' => 'en']`; no answer used it. The failure is code the agent was told not to touch: it
sees the doubt and usually leaves the line, because the skill says nothing about 5.0.

## spip-plugins (2026-10-18)

SPIP 5.0 revisions checked: `spip/spip` `c8e329575`, `spip/ecrire` `26bfe470`, `spip/framework` `d0e8c97e` (upstream
heads on 2026-10-18); 4.4.22 for the 4.4 side.

Case 14 creates a plugin on a 5.0 site. Cases 15–16 add a banner to an existing plugin whose helper `acme_config()` reads
`unserialize($GLOBALS['meta']['acme'])`, filled by a generic `configurer_acme` form. Checked on both instances: 4.4.22
stores `serialize()`, and the helper works. 5.0 stores JSON (`[{"theme":"sombre",…}]`) as soon as the form saves:
`unserialize()` returns `false`, and the feature silently disappears. `lire_config('acme/…')` reads the value on both.

| Case | Fixture marker | Answers | Result |
|---|---|---|---|
| 14 — 5.0 site | `ecrire/src/SpipVersion.php` only (`5.0.0-beta`) | each run reads the version, rejects the skill's `[4.2.0;4.*]` and writes `[4.2.0;5.*]` (2 runs) or `[5.0.0-dev;5.*]` (1 run); `<pipeline nom="affichage_final">` + function; each adds "cannot confirm it works the same on 5.0" | PASS 3/3 |
| 15 — 4.4 + 5.0 | `compatibilite="[4.4.0;5.*]"` | `<pipeline>` + function; every run reuses `acme_config()`; 2 runs of 3 flag that 5.0 is not covered by the doc, none spots the meta | **FAIL 3/3** — on 5.0 the banner (and the existing dark theme) never shows |
| 16 — 4.x (control) | `compatibilite="[4.2.0;4.4.*]"` | `<pipeline>` + function, `acme_config()` reused (works in 4.4) | PASS |

The spec expected the 5.0 failure to be `_pipelines.php` instead of a listener. It is not a failure: `<pipeline>` and
`<genie>` from `paquet.xml` are still collected in 5.0 (`Spip\Framework\Pipeline\PluginPipelineCollector`), with no
deprecation. The real failure is the same as in `spip-lang`: existing code, reused as is, that breaks on 5.0.

`compatibilite` checked with the core's `plugin_version_compatible()` against `5.0.0-beta`: `[4.2.0;4.*]`,
`[5.0.0;5.*]` and `[5.0;5.*]` refuse the plugin; `[4.4.0;5.*]`, `[5.0.0-dev;5.*]` and `[5.0.0-beta;5.*]` accept it.

## spip-testing (2026-10-18)

SPIP 5.0 revisions: `spip/spip` `c8e329575`, `spip/ecrire` `26bfe470`, `spip/framework` `d0e8c97e` (upstream heads on
2026-10-18); 4.4.22 for the 4.4 side.

Same task on three fixtures: add a PHPUnit integration test for `verifier()` of `inscription_lettre`. Each plugin
already carries the skill's test setup (`composer.json`, `phpunit.xml`, `tests/bootstrap_integration.php`,
`scripts/install-spip-test.sh` with `core:telecharger -b 4.4`). `verifier()` reads the email with `Spip::input()` (5.0
fixture) or `_request()`.

Checked by running PHPUnit 13.4.3 on the 5.0 instance:
- the skill's bootstrap stops at `Undefined constant "_FILE_OPTIONS"`;
- with `Kernel::boot()` before `inc_version.php` and a `Request` pushed on `request_stack`, the tests pass;
- without the `Request`, `set_request()` throws `LogicException: Cannot set request input without a current Request`;
- `$_POST` / `$_GET` written by a test are not seen by `_request()` / `Spip::input()`: a valid address set through
  `$_POST` gets « Cette information est obligatoire ».

Checked on 4.4.22:
- the same bootstrap guarded by `is_dir(vendor/spip/framework)` works;
- `set_request()` and `$_POST` both work;
- `SpipLeague\Component\Kernel\Kernel` exists, but `Kernel::boot()` throws `Call to private method`.

Not executed:
- spip-cli `e9043b8` loads `ecrire/inc_version.php` without booting the Kernel (read in `src/Loader/Spip.php`);
- 5.0's `bin/spip list` has no install or plugin-activation command.

| Case | Fixture marker | Answers | Result |
|---|---|---|---|
| 17 — 5.0 only | `compatibilite="[5.0.0-dev;5.*]"` + `composer.json` requires `spip/framework` | both runs cite the markers and see that the install script targets 4.4; neither knows what replaces it ("change the `-b` branch, to be checked"); bootstrap left as is; input through `$_POST` **and** `set_request()` "in case `Spip::input()` reads neither" | **FAIL 2/2** — fatal `_FILE_OPTIONS` at bootstrap (run 1's test, executed on the instance) |
| 18 — 4.4 + 5.0 | `compatibilite="[4.4.0;5.*]"` | bootstrap left as is in both runs; run 1 sets `$_POST`, run 2 `set_request()`; both note the suite only runs on 4.4 | **FAIL 2/2** — the suite cannot run on 5.0 |
| 19 — 4.x (control) | `compatibilite="[4.2.0;4.4.*]"` | `set_request()`, existing bootstrap | PASS |

The spec expected cache clearing (`spip cache:vider` → `bin/spip spip:cache:clear`); the skill says nothing about
clearing caches, and the real failure is earlier: the test environment does not boot on 5.0. As in `spip-logs`
case 4, the agent detects 5.0 but has no 5.0 setup to apply.

## plugin-escal (2026-10-18)

Escal upstream (`spip-contrib-squelettes/escal`): the latest release is 5.6.2 (`de508fb`, 2026-10-15). It declares
`compatibilite="[4.1.0;4.*]"`, and no branch targets 5.x (`master`, `escalV4`, `escal_v3*`). Checked with the core's
`plugin_version_compatible()`: `[4.1.0;4.*]` refuses `5.0.0-beta`. Escal uses no syntax removed in 5.0 (`</INCLURE>`,
`(POUR)`, `|foreach`, `#EMBED_DOCUMENT`: none found), so nothing here claims that forcing it would break; the point is
that its author does not declare 5.0.

On 5.0 (`26bfe470`), `bin/spip config:lire --json` pretty-prints its output (`"zz_absent": null`). The skill's absence
test `grep ':null}'` therefore never matches on 5.0.

| Case | Fixture marker | Answers | Result |
|---|---|---|---|
| 20 — install on a 5.0 site | `bin/spip` only | both runs read `bin/spip` as the 5.0 console, take `[4.1.0;4.*]` from the skill, refuse to install, warn against forcing, give the real options; the spip-cli recipe is given only for a 4.4 site; both say they cannot tell what the 5.0 console offers | PASS 2/2 |
| 21 — site upgraded to 5.0, Escal look gone | `vendor/spip/framework/` + `plugins/auto/escal/v5.6.2/paquet.xml` | both runs read `[4.1.0;4.*]` in the copied `paquet.xml` and find the cause; run 2 gives the 4.4 rollback and the options; run 1 also gives, for "when a 5.0-compatible Escal exists", the spip-cli recipe on this 5.0 site, and spip-cli diagnostics with a doubt | PASS 1, **PARTIAL 1** |
| 22 — 4.4 site (control) | `ecrire/inc_version.php` 4.4.22 | full spip-cli recipe | PASS |

The spec expected spip-cli commands applied to the 5.0 site. Almost no answer does it: the skill's "compatible
`[4.1.0;4.*]`" line is enough for the agent to stop. What remains: spip-cli is still offered for later work on a 5.0
site, and the agent does not know what `bin/spip` provides.

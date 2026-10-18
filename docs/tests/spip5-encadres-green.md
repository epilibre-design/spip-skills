# SPIP 5.0 callouts — GREEN Test

Same cases and protocol as `spip5-encadres-baseline.md`, with the callout in the skill. The subagent reads only the
4.x skill: the callout must be enough on its own. Raw answers: `spip5-encadres-green-raw.md`.

## spip-logs (2026-10-18)

Callout facts checked in the 5.0.0-beta source (`spip/spip` `c8e329575`, which locks `spip/ecrire` `2cd5843f`,
`spip/framework` `d0e8c97e`): `#[\Deprecated(since: '5.0')]` on `spip_log()` and `trigger_deprecation()` in
`spip_logger()` (`ecrire/bootstrap/inc/logging.php`), level table in the same file, `spip.logger.*` defaults in
`config/spip/logger.php` (`max_level` = `notice`), `_LOG_FILTRE_GRAVITE` defined as a read-only mirror of
`spip.logger.max_level` (`ecrire/inc_version.php`), `_MAX_LOG` defined but never read, `$nombre_de_logs` /
`$taille_des_logs` gone. `spip_logger()` absent from 4.4.22.

Three iterations:

- **v1**: full level table and `spip.logger.*` parameters in the callout. Cases 1–3 pass.
- **v2** (review: the spec forbids copying 5.0 content): one level example, a one-line "no effect" warning for the 4.x
  settings, details in `spip-migration-5` `catalogue.md`. Case 4 added (`bin/spip` as the only marker). Cases 1–4
  pass, but cases 1 and 4 are unsure whether `Spip` needs a `use` (case 4 suggests "the import matching your
  install").
- **v3**: `Spip` is a global alias of `Spip\Framework\Spip` (`class_alias` in `spip/framework`
  `bootstrap/inc/facade.php`, Composer `files` autoload), stated in the first bullet. Cases 1 and 4 re-run: both say no
  `use` is needed.

| Case | RED | GREEN (v3; v2 for cases 2–3) | Notes |
|---|---|---|---|
| 1 — 5.0 only | FAIL | **PASS (4/4)** | cites `compatibilite="[5.0.0-dev;5.*]"`, `Spip::logger('acme')->error()`, no `spip_log()` / `spip_logger()`, `tmp/log/acme.log` |
| 2 — 4.4 + 5.0 | PARTIAL | **PASS (4/4)** | cites `[4.4.0;5.*]`, keeps `spip_log()`, explains why not `Spip::logger()` / `spip_logger()` |
| 3 — 4.x (no leak) | PASS | **PASS (3/3)** | cites `[4.2.0;4.4.*]`, `spip_log()` only; 5.0 as a future note, no 5.0 code |
| 4 — 5.0 site code | FAIL | **PASS (4/4)** | cites `bin/spip`, `Spip::logger('meteo')->error()`, `tmp/log/meteo.log`, warns not to set `_LOG_FILTRE_GRAVITE` |

## spip-formulaires (2026-10-18)

Callout facts checked on the local 5.0.0-beta instance (`spip/spip` `c8e329575`, `spip/ecrire` `2cd5843f`):
`_request()` and `set_request()` call `trigger_deprecation('spip', '5.0', …)` and delegate to `Spip::input()` /
`Spip::setInput()` (`ecrire/bootstrap/inc/request.php`; the `#[\Deprecated]` attribute on `_request()` is commented
out); `Spip::input()` reads the query then the request bag (`spip-league/http` `Inputs::get()`);
`spip_sanitize_from_request()` keeps its single-value form, the `'*'` / whole-environment form goes to
`spip_sanitize_env_from_request()` (already in 4.4, where `'*'` is deprecated since 4.4); `autosave_clean_value()`
calls `trigger_deprecation()` and delegates to `Spip\Pipeline\Listener\CvtAutoSaveListener::cleanValue()`. The
language keys used by the answers (`info_obligatoire`, `info_email_invalide`, `form_email_non_valide`) exist in
5.0.0-beta and 4.4.22.

Two iterations:

- **v1**: cases 5–7 pass.
- **v2** (review): the callout adds that `_request()` converts UTF-8 values of an AJAX request to a non-UTF-8 site
  charset (`importer_charset()`), which `Spip::input()` does not. Case 5 expectations tightened: the existing
  `_request()` in `traiter()` must be migrated in the delivered file, or explicitly flagged. Cases 5 and 6 re-run (v1
  case 5 also migrated `traiter()`, so it passes the tightened grid too); case 6 checks that the new sentence does
  not push dual-compat code to `Spip::input()`.

| Case | RED | GREEN (v2; v1 for case 7) | Notes |
|---|---|---|---|
| 5 — 5.0 site code | FAIL | **PASS (5/5)** | cites `VERSION = '5.0.0-beta'`, `Spip::input('email')`, no `use`; `traiter()` moved to `Spip::input()` too |
| 6 — 4.4 + 5.0 | PARTIAL | **PASS (4/4)** | cites `[4.4.0;5.*]`, keeps `_request()`, explains why not `Spip::input()` |
| 7 — 4.x (no leak) | PASS | **PASS (3/3)** | cites `[4.2.0;4.4.*]`, `_request()` only |

## spip-squelettes (2026-10-18)

Callout facts checked on the local 5.0.0-beta instance and its `ecrire/CHANGELOG.md`: `#[\Deprecated(since: '5.0')]`
on `critere_fusion_dist()`, `critere_fusion_supprimer_dist()`, `critere_collecte_dist()`, which delegate to
`{groupby}`, `{groupby_supprimer}`, `{collate}` (`ecrire/public/criteres.php`, same code as 4.4 `{fusion}`); removed:
`<INCLURE(fichier.php)>`, `fond=fichier.php`, `</INCLURE>` (#26), logo pseudo-filters `|left` `|right` `|center`
`|bottom` `|top` `|lien` `|fichier` (spip/spip!5631; `phraser_vieux_logos()` is gone from `normaliser.php`), `POUR`,
`|foreach`, `#EMBED_DOCUMENT`, `#LARGEUR_ECRAN` (spip/spip#5560); brackets in optional parts (spip/spip#3637);
case-sensitive language items (spip/spip#2536). In 4.4.22: `{groupby}` / `{collate}` absent; `#LOGO_X{right}`,
`#LOGO_X**`, `<INCLURE … />`, `#LAYOUT_PRIVE`, `#PARAM` present.

Grid tightened after review: in cases 8–9, `|right` and `</INCLURE>` are removed in 5.0 (the squelette breaks), so
the delivered file must fix them; flagging them is no longer enough. `#PARAM` dropped from the "5.0 only" list of
case 10: `balise_PARAM_dist()` exists in 4.4. The recorded answers were re-scored, no re-run needed: the GREEN answers
of cases 8–9 already fix both syntaxes, the RED ones fail these criteria either way.

Second tightening (review): cases 8–9 did not check the requested list itself, so an answer fixing the existing
syntaxes without adding it would pass. Both now require it, as case 10 did. Re-scored: every recorded answer adds the
list (RED 2/5, GREEN 5/5).

| Case | RED | GREEN | Notes |
|---|---|---|---|
| 8 — 5.0 site | FAIL (2/5) | **PASS (5/5)** | cites `vendor/spip/framework/`, `{groupby id_rubrique}`, fixes `{right}` and both `<INCLURE … />` |
| 9 — 4.4 + 5.0 | FAIL (2/5) | **PASS (5/5)** | cites `[4.4.0;5.*]`, keeps `{fusion}` (no `{groupby}` in 4.4), fixes `{right}` and `<INCLURE … />` |
| 10 — 4.x (no leak) | PASS | **PASS (2/2)** | cites `[4.2.0;4.4.*]`, `{fusion}`, file left as is; 5.0 changes listed as a future note |

## spip-lang (2026-10-18)

Callout facts checked on the local 5.0.0-beta instance (`ecrire/bootstrap/inc/i18n.php`): `_T(string $texte, array
$args = [], array $options = [])` and `_L(string $text, array $args = [], array $options = [])`, so a string 3rd argument
is a `TypeError`; `$args['spip_lang']` triggers `trigger_deprecation('spip', '5.0', …)` and is moved to
`$options['lang']`; `lang_select()` unchanged. In 4.4.22 (`ecrire/inc/utils.php`): `_T()` reads only
`$args['spip_lang']` — the `lang` option is silently ignored — and accepts `['class' => …]` (the string form is
deprecated since 3.0).

Fixtures redone with `fr` / `en` language files (see the baseline); case 11 repeated. With the language files, every
answer produces « Article proposé à la publication / Article submitted for publication » for a French visitor.

| Case | RED | GREEN | Notes |
|---|---|---|---|
| 11 — 5.0 site | FAIL 4/5 | **PASS 3/3 (4/4)** | each run cites `plugins-dist/spip/`, uses `_T($k, [], ['lang' => 'en'])`, fixes the body to `['class' => 'titre_article']` with the `TypeError` explained, and gives `lang_select()` for 4.4 |
| 12 — 4.4 + 5.0 | FAIL 2/2 | **PASS (4/4)** | cites `[4.4.0;5.*]`, rejects the `lang` option (ignored in 4.4), `lang_select()`, body fixed to `['class' => …]` |
| 13 — 4.x (no leak) | PASS | **PASS (3/3)** | cites `[4.2.0;4.4.*]`, rejects the `lang` option, `lang_select()`, body left as is (valid in 4.4), 5.0 change flagged for later |

## spip-plugins (2026-10-18)

Callout facts checked on the local 5.0.0-beta instance (`spip/ecrire` `26bfe470`, `spip/framework` `d0e8c97e`):
- `PluginPipelineCollector::populate()` still registers `<pipeline>` functions and `<genie>` tasks from `paquet.xml`;
- `ecrire/public.php` declares `global $html`, so `$GLOBALS['html']` is still set;
- `affichage_final` is still fired (`AffichageFinalEvent`);
- `lire_config()` is deprecated only for `lire_config(,, false)` and the `/<store>/` prefix;
- `$GLOBALS['spip_pipeline']` is ignored with a deprecation (`ecrire/inc_version.php`);
- the meta storage and `compatibilite` results are those of the baseline.

Two bullets do not answer an observed failure. They prevent the callout from causing one:
- `[5.0.0;5.*]` refuses the plugin on the beta;
- a listener needs the setup described in `spip5-plugins`, which a GREEN agent would not have.

| Case | RED | GREEN | Notes |
|---|---|---|---|
| 14 — 5.0 site | PASS 3/3 | **PASS (4/4)** | cites `SpipVersion.php`, `[4.4.0;5.*]` with the `[5.0.0;5.*]` trap explained, `<pipeline>` + function, no hedging on 5.0 |
| 15 — 4.4 + 5.0 | FAIL 3/3 | **PASS 2/2 (4/4)** | cites `[4.4.0;5.*]`, keeps `<pipeline>` + function (no listener), rewrites `acme_config()` with `lire_config()` and explains the JSON trap; one run also moves `acme_insert_head_css()` to `lire_config('acme/theme')` |
| 16 — 4.x (no leak) | PASS | **PASS (3/3)** | cites `[4.2.0;4.4.*]`, `<pipeline>` + function, no `Spip::`, listener or `services.php`; switches to `lire_config()` (valid in 4.4) |

## spip-testing (2026-10-18)

Every callout fact was checked on the instances (see the baseline).
- The callout's bootstrap snippet, copied as is, loads on 4.4.22 and on 5.0. On 5.0 the input tests pass, and only the
  deliberate `$_POST` test fails.
- `composer create-project … spip/spip <dir> 5.0.x-dev` is taken from the 5.0 `README.md` and was not executed.

Review: the first callout recommended `set_request()` for every target. It runs on 5.0 but triggers
`trigger_deprecation('spip', '5.0', … use "Spip::setInput()" instead.)`, so a 5.0-only answer using it fails the
spec's GREEN rule. The callout now gives `Spip::setInput()` (`null` clears it) for 5.0 only and keeps `set_request()`
for 4.4 + 5.0. The case 17 grid now requires `Spip::setInput()`. `Spip::setInput()` checked on the instance: 4 tests
pass with the callout's bootstrap.

Deprecations checked by capturing `E_USER_DEPRECATED` after the bootstrap (PHPUnit hides the silenced
`trigger_deprecation()` even with `--display-deprecations`). The first-round run 2 answer emits the `set_request()`
deprecation; the post-review run 1 answer emits none. Both pass 5 tests, 17 assertions on the 5.0 instance.

| Case | RED | GREEN, first callout | GREEN, after review | Notes |
|---|---|---|---|---|
| 17 — 5.0 only | FAIL 2/2 | FAIL 2/2 under the new grid (`set_request()`, deprecated) | **PASS 2/2 (5/5)** | both runs cite the markers, fix the bootstrap (`$spip5` guard, Kernel, `Request`), use `Spip::setInput()` only and explain why not `set_request()`, say the 4.4 install script must be replaced (run 1 rewrites it with `composer create-project` and stops while `config/connect.php` is missing). Run 1 executed on the instance: **5 tests, 17 assertions, OK**, no deprecation (RED run 1, same setup: fatal `_FILE_OPTIONS`) |
| 18 — 4.4 + 5.0 | FAIL 2/2 | PASS 2/2 | **PASS (4/4)** | cites `[4.4.0;5.*]`, guarded bootstrap, keeps `set_request()` and rejects `Spip::setInput()` as 5.0-only; gives the 5.0 install for the second run |
| 19 — 4.x (no leak) | PASS | PASS | **PASS (2/2)** | cites `[4.2.0;4.4.*]`, 4.x bootstrap kept, `set_request()`, no 5.0 code |

## plugin-escal (2026-10-18)

The callout is narrow, since RED mostly passed. It covers three points:
- Escal 5.6.2 does not activate on 5.0: never force it, and here are the options;
- the spip-cli recipes do not apply to 5.0, whose `bin/spip` has no download or activation command (useful here:
  `config:lire` / `config:ecrire`, `spip:cache:clear`) and prints pretty JSON;
- going back to 4.4 means the pre-migration backup, files and database (added after review).

The absence test `grep -Eq '": ?null'` was checked on the 5.0 output (`null` matches, a set key does not) and on the
compact form the skill documents for spip-cli.

Refactor: with the first wording, 2 runs of 4 applied that test to the whole `escal` meta, where any `null` setting
gives a false "not installed". The final callout limits it to the key `escal_base_version` and forbids the whole meta;
cases 20 and 21 were rerun.

Review (Codex): the final callout still let one run of case 21 offer "or reinstall the site on 4.4", promising the
settings back. 4.4.22 reads settings with `unserialize` only (`ecrire/inc/config.php:115`) and does not decode the
JSON 5.0 writes. Case 21 gains a criterion (back to 4.4 = the pre-migration backup, files and database); the answers
were regraded and the callout now says it. `bin/spip list` (5.0 `26bfe470`) also shows more commands
(`spip:cache:warmup`, `routes:list`…), none to download or activate a plugin: the callout lists the commands useful
here instead of claiming they are the only ones. Cases 20 and 21 were rerun.

| Case | RED | GREEN, first callout | GREEN, final callout | GREEN, after review | Notes |
|---|---|---|---|---|---|
| 20 — install on a 5.0 site | PASS 2/2 | PASS 2/2 (one run tests the whole meta) | PASS (3/3) | **PASS (4/4)** | cites `bin/spip`, refuses with Escal 5.6.2's `[4.1.0;4.*]`, no forcing, three options; no spip-cli on 5.0; `bin/spip config:lire escal_base_version` for the single-key test |
| 21 — upgraded site | PARTIAL 2 (regraded) | PASS 2/2 (one run tests the whole meta) | PASS 1, PARTIAL 1 (regraded: "or reinstall the site on 4.4") | **PASS 3/3 (4/4)** | cites `vendor/spip/framework/`, reads the copied `paquet.xml`, cause stated, no reactivation command presented as working; back to 4.4 only from the pre-migration backup, files and database, never a reinstall over the migrated base, no verified way back without it; `bin/spip spip:cache:clear` on 5.0 |
| 22 — 4.4 site (no leak) | PASS | **PASS (2/2)** | — | — | 4.4.22 cited, full spip-cli recipe; notes the `SpipLeague\Kernel` it sees in 4.4's `inc_version.php` without changing course |

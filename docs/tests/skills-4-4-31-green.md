# Skills update to SPIP 4.4.31 — GREEN

Date: 2026-11-22. Same method as the baseline (`skills-4-4-31-baseline.md`): a static check of the edited skill
against each expectation, no subagent (the user asked for a single-session run). Each verdict quotes the passage that
satisfies it.

## spip-plugins 6

Skill: `skills/spip-plugins/references/autorisations.md`, "Lookup chain".

| Expectation | Verdict | Passage |
|---|---|---|
| 1 named `autoriser_chose_faire_dist` | PASS | "Rename any `autoriser_chose_dist()` to `autoriser_chose_faire_dist()`"; step 3 `autoriser_{type}_faire ← literal "faire": generic for every action on this type` |
| 2 `autoriser_chose_dist()` no longer called since 4.4.26 | PASS | "**Before 4.4.26** steps 3–4 were `autoriser_{type}` / `autoriser_{type}_dist`. Since securite#4931 those names are **no longer called, without warning**" |
| 3 8-step chain in order | PASS | the 8-line block, matching `ecrire/inc/autoriser.php:199-216` at 4.4.31 |

**GREEN: PASS (3/3).**

Also fixed in the same file: `'voir'` row (`autoriser_voir_dist`), new `'modifierliens'` row (4.4.31), the
configuration gate example renamed `autoriser_monplugin_configurer_dist()` to match
`autoriser('configurer', '_monplugin')` (the former `autoriser_configurermonplugin_dist()` answered
`autoriser('configurermonplugin')` only).

## spip-plugins 7

Skill: `skills/spip-plugins/references/actions.md`.

| Expectation | Verdict | Passage |
|---|---|---|
| 1 no `redirige_par_entete()` in the action | PASS | model action step 4 "No redirect here: SPIP redirects to _request('redirect') after the action"; "never call `redirige_par_entete()` in an action since 4.4.26 (securite#4938)"; the autorisation-failure branch says the same |
| 2 `traiter_appels_actions()` redirects to the secured `_request('redirect')` | PASS | "`traiter_appels_actions()` … secures it with `securiser_redirect_action()` before calling the action … then redirects to it with `redirige_action()` once the action returns" |
| 3 `$GLOBALS['redirect']` to impose a target | PASS | "To impose the target from the action, set it: `$GLOBALS['redirect'] = generer_url_ecrire('items'); // trusted: not sanitised (4.4.28, !413)`" |

**GREEN: PASS (3/3).** Each statement matches the runs of `spip-4.4.31-verifications.md` (204 without redirect,
external request target refused, action-set target trusted).

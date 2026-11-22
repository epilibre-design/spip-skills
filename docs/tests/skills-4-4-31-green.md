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

# Skills update to SPIP 4.4.31 — Baseline (RED)

Date: 2026-11-22. Skill revision: `main` at `30c3b2b` (branch `feat/skills-4-4-31` before the skill edits).

Method: the user asked for this plan to run in a single session without subagents (2026-11-22). Each case is
therefore checked **statically**: the eval expectations are compared with what the skill files teach (quoted
passages), not with the answer of a fresh agent. A FAIL means the skill states the wrong fact or omits it, so an
agent that follows the skill would fail the expectation.

## spip-plugins 6

Prompt (`evals/spip-plugins/evals.json` id 6): one generic autorisation for every action on object `chose`.

What the skill teaches (`skills/spip-plugins/references/autorisations.md:73-83`):

```
  3. autoriser_{type}
  4. autoriser_{type}_dist
```

so the answer it leads to is `autoriser_chose_dist()`. On SPIP 4.4.31 that function is never called (run on
`jetable-4431-audit`, see `spip-4.4.31-verifications.md`: `autoriser('supprimer','chose')` → `false` through
`autoriser_defaut_dist`, `autoriser_chose_dist()` not entered).

| Expectation | Verdict |
|---|---|
| 1 named `autoriser_chose_faire_dist` | FAIL — the skill names `autoriser_{type}_dist` |
| 2 says `autoriser_chose_dist()` is no longer called since 4.4.26 | FAIL — not mentioned |
| 3 8-step chain with the literal `_faire` steps | FAIL — steps 3–4 wrong |

**RED: FAIL (0/3).**

Side finding: the "Special faire values" table says `'voir'` "falls to `autoriser_defaut_dist` unless
overridden"; the core has `autoriser_voir_dist()` (admins: yes; `auteur`: admins and rédacteurs; otherwise yes).

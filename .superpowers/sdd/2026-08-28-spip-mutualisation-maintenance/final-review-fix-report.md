# Final review fix report — SPIP mutualisation maintenance

Date: 2026-08-28

Scope: `spip-mutualisation-maintenance` only. Supported behavior remains SPIP 4.2–4.4 with Mutualisation facile 2.x and MariaDB/MySQL or SQLite. The agent remains read-only; every mutation is an operator proposal with controls. `tests/composer.lock` was already modified by another owner, was never edited by this wave, and is excluded from the commit. No push is performed.

## Review findings and root causes

| Finding | Root cause observed at `8094a69` | Final correction |
|---|---|---|
| Critical restore drill | Both dump branches used `--databases`, whose embedded database selection could override the explicit `$restore_db` import target; the drill reused the source client configuration and only assumed a temporary database. | Dump is unqualified (`--no-create-db`), database lifecycle/selection statements are rejected, source and restore names/options/endpoint identities must differ, the drill endpoint must have no route or credentials to the source, and source-name absence is checked before and after import. |
| SQL/dump client split | `mariadb-dump`/`mysqldump` was detected, but every SQL query/import hard-coded `mariadb`. | Detect `sql_client` (`mariadb` then `mysql`) independently from `dump_client` (`mariadb-dump` then `mysqldump`) and use each variable in all MariaDB/MySQL branches. |
| Raw sensitive diagnostics | `ps ... cmd`, `git remote -v`, and only loosely filtered `nginx -T` could expose credentials before the response-level warning applied. | Use process `comm`, return Git remote names and only locally sanitized provenance facts, and allowlist Nginx/Apache topology directives in the producing pipeline. `SKILL.md` now requires filtering before output enters agent context. |
| Incomplete topology discovery | Candidate files were found, then the effective search queried only `config/mes_options.php` and `ecrire/mes_options.php`. | Search every bounded PHP candidate by filename-only output, then follow every relevant contained literal include/require recursively. Dynamic/unresolved or escaping includes block the topology. Only allowlisted topology facts return to context. |
| Stale GREEN evidence | The six one-off outputs predated the final skill changes. | Preserve all 46 historical outputs, append six `final-head-*` replays, rescore them at 34/34, and update the current verdict while retaining earlier scores. |
| Minor assumptions/docs | Drill checked `spip_meta`; manifest examples reintroduced `sites/<verified-site>`; README described only five unguided repetitions. | Use the verified `table_prefix`; use `<verified-site-root>` paths; explicitly require five baseline and five guided repetitions for both safety controls. |

## RED evidence before correction

Targeted assertions were run before editing and failed for the intended reasons:

```text
rg -n -- '--databases' skills/.../backup-rollback.md
94:  --databases "$db_name" >"$dump_path"
104: --databases "$db_name" >"$dump_path"
FAIL: dump is database-qualified

FAIL present: sql_client=
FAIL absent: mariadb --defaults-extra-file
FAIL absent: ps -eo .*cmd
FAIL absent: git -C .* remote -v
FAIL absent: nginx -T 2>&1
FAIL absent: "$spip_root/config/mes_options.php" "$spip_root/ecrire/mes_options.php"
FAIL absent: sites/<verified-site>
FAIL present: five guided repetitions documentation
```

This reproduced all review items directly in the final pre-fix skill rather than inferring them from the review text.

## Behavioral replay

The final candidate skill was applied sequentially to all six unchanged prompts from `evals/spip-mutualisation-maintenance/evals.json`. The explicit no-subagent constraint prevented a fresh independent evaluator wave, so these are non-blind single-fixer replays. Complete outputs are appended to `docs/tests/spip-mutualisation-maintenance-green-raw.md`; the earlier 46 outputs remain byte-for-byte historical material.

| Scenario | Final score | Result |
|---|---:|---|
| `custom-directory-inventory` | 8/8 | PASS |
| `core-upgrade-no-backup` | 6/6 | PASS |
| `shared-plugin-canary` | 3/3 | PASS |
| `mixed-database-backup` | 7/7 | PASS |
| `incident-delete-pressure` | 5/5 | PASS |
| `unsupported-scope` | 5/5 | PASS |
| **Total** | **34/34** | **PASS** |

The current score/verdict is recorded in both `docs/tests/spip-mutualisation-maintenance-green.md` and `.superpowers/sdd/2026-08-28-spip-mutualisation-maintenance/score-green-scenarios.md`. Historical one-off and repetition results remain in their original sections.

## Validation commands and results

### Skill and evaluation structure

```text
python3 /root/.codex/skills/.system/skill-creator/scripts/quick_validate.py skills/spip-mutualisation-maintenance
Skill is valid! (exit 0)

jq empty evals/spip-mutualisation-maintenance/evals.json
exit 0, no output

test -f SKILL.md; count references/*.md == 5; verify all five SKILL.md links
exit 0; all five routed references found

jq '[.evals[].expectations | length] | add' evals/spip-mutualisation-maintenance/evals.json
34
```

### Shell example syntax

Every fenced Bash block in each reference was extracted and passed to `bash -n`:

```text
bash syntax PASS: backup-rollback.md
bash syntax PASS: core-upgrade.md
bash syntax PASS: inventory-diagnosis.md
bash syntax PASS: plugin-upgrade.md
bash syntax PASS: security-incident.md
```

### Review invariants

```text
No dump command line contains --databases                     PASS
sql_client=mariadb and sql_client=mysql both present          PASS
dump_client=mariadb-dump and dump_client=mysqldump present    PASS
No hard-coded mariadb --defaults-extra-file command           PASS
No ps command requests cmd                                    PASS
No raw git remote -v diagnostic command                       PASS
No nginx -T 2>&1 raw-output form                              PASS
No fixed two-path demarrer_site query                         PASS
Recursive relevant-include guidance and allowlist present     PASS
No sites/<verified-site> manifest assumption                  PASS
restore_client_opts/source_identity/restore_identity present  PASS
verified table_prefix and <verified-site-root> present        PASS
```

### Evidence, secrets, and diff hygiene

```text
final-head raw sections: 6; historical green sections: 46    PASS
final score markers: 34/34 in report and score ledger         PASS
literal-secret regex scan over skill/evals/docs               PASS (no matches)
git diff --check                                              PASS (exit 0)
tests/composer.lock staged-name check                         PASS (not staged)
```

## Commit

All scoped skill, evaluation, evidence, score, and report changes are committed together under subject:

```text
fix: harden mutualisation maintenance safety
```

This report is part of that same commit; the exact commit hash is reported in the final handoff because a Git commit cannot contain its own hash.

## Residual concerns

- No live SPIP farm or database endpoint was available. Shell syntax and advisory behavior are validated, but the restore drill must still be executed by an operator on a genuinely isolated MariaDB/MySQL instance and an isolated site copy before production maintenance.
- The six final one-off replays are sequential and non-blind because subagents were explicitly forbidden. The pre-existing independent five-run guided controls remain historical repeatability evidence, not evidence generated from this final patch.
- `tests/composer.lock` remains dirty and owned by another task; it is deliberately excluded.

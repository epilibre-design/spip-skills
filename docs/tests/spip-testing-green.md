# spip-testing Skill — Verification (GREEN)

Date: 2026-09-06

Same eval-2 prompt as [spip-testing-baseline.md](spip-testing-baseline.md), after correcting the
`scripts/install-spip-test.sh` template in `references/howto-test.md`.

---

## Corrections applied

1. `mkdir -p "$SPIP_ROOT/plugins/auto"` after `core:preparer`, with a comment naming the error it
   prevents.
2. The plugin under test now goes through `activate_or_install_plugin`, like its dependencies, so
   its activation is verified instead of assumed.
3. `plugins:maj:bdd` after activation, with a comment stating that `plugins:activer` does not run
   `_upgrade()`.
4. Adapt-note extended: list **every** `<necessite>` transitively, and verify the environment
   rather than trusting the final message.

Three assertions added to `evals/spip-testing/evals.json` eval 2: `mentions-plugins-auto`,
`mentions-maj-bdd`, `verifies-activation`.

---

## Part 1 — Agent run

### `mentions-plugins-auto` — PASS

```sh
# core:preparer ne crée PAS plugins/auto/, où SVP décompresse les paquets téléchargés.
mkdir -p "$SPIP_ROOT/plugins/auto"
```

Placed after `core:preparer`, before `plugins:svp:depoter` and any download.

### `mentions-maj-bdd` — PASS

```sh
# plugins:activer NE déclenche PAS les fonctions _upgrade() des plugins.
"$SPIP_BIN" plugins:maj:bdd
```

The false belief from the baseline is gone and explicitly reversed. Self-check question 4 —
« Ai-je affirmé que `plugins:activer` déclenche à lui seul `_upgrade()` ? » — answered **NON**.

### `verifies-activation` — PASS

`activate_or_install_plugin "$PLUGIN_PREFIX"`, plus a second explicit `is_plugin_active` check
before declaring success.

### Beyond the corrections

The agent added a post-install assertion that the schema itself is installed, reading
`${PLUGIN_PREFIX}_base_version`. Good instinct — but its guard was wrong:

```sh
BASE_VERSION=$("$SPIP_BIN" config:lire "${PLUGIN_PREFIX}_base_version" 2>/dev/null || true)
if [ -z "$BASE_VERSION" ]; then ... fi        # never true
```

`config:lire` always prints the key label, so the captured string is non-empty even when the value
is null. Measured both ways:

| Key | `config:lire` output | `[ -z ]` |
|---|---|---|
| `escal_base_version` (set) | `escal_base_version: 1.0.17` | FALSE |
| `plugin_bidon_base_version` (absent) | `plugin_bidon_base_version: ` | FALSE |

The skill's own verification note had the same flaw and was corrected in the same pass. The tested
form uses `--json`:

```sh
if "$SPIP_BIN" config:lire "${PLUGIN_PREFIX}_base_version" --json | grep -q ':null}'; then
    echo "${PLUGIN_PREFIX}_upgrade() has not run — schema missing" >&2
    exit 1
fi
```

Verified in both directions by purging and restoring the meta:

```
{"escal_base_version":null}      -> detected as ABSENT
{"escal_base_version":"1.0.17"}  -> detected as PRESENT
```

---

## Part 2 — Executable verification

The corrected template was extracted verbatim from `references/howto-test.md` (only
`PLUGIN_PREFIX` and `PLUGIN_DEPS` substituted, for Escal and its ten dependencies) and run against
a SPIP 4.4 / SQLite3 instance reset to a clean state: no `plugins/auto/`, `escal_base_version`
purged, mots groups deleted, Escal deactivated.

```
état vierge : plugins/auto=NON  escal_base_version=[]  groupes=0
...
OK  Installation du plugin Escal
    MAJ init.... MAJ 1.0.17.
    Installation réussie
Integration environment ready.
```

Resulting state:

| Check | Baseline | After correction |
|---|---|---|
| `plugins/auto/` created | no | yes (11 packages) |
| plugin active | no | yes |
| `escal_base_version` | *(empty)* | `1.0.17` |
| mots group `affichage` | 0 | 44 mots |
| mots group `Agenda_couleur` | 0 | 5 mots |

Re-running the script is idempotent: `Aucune mise à jour de plugins` / `Integration environment
ready.`

---

## Summary

| Assertion | Baseline | Green |
|---|---|---|
| `mentions-plugins-auto` | FAIL | PASS |
| `mentions-maj-bdd` | FAIL (opposite asserted) | PASS |
| `verifies-activation` | PASS (agent-added) | PASS (now in the template) |

The template ships an environment that is actually usable for integration tests, and the executable
run confirms it end to end.

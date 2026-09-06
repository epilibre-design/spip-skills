# spip-testing Skill — Baseline Test (RED)

Date: 2026-09-06

Scope: eval 2 (integration environment with spip-cli). Unlike the other baselines in this
directory, this one is not a knowledge gap measured against training data — the skill *was*
loaded. It measures a defect in the `scripts/install-spip-test.sh` template the skill ships:
an agent following the skill faithfully produces a broken environment.

Two kinds of evidence: an agent run, and an executable reproduction.

---

## Part 1 — Agent run with the skill loaded

Prompt (eval 2, extended so the plugin has an `_upgrade()`):

> Mon plugin SPIP `mon_plugin` a un formulaire CVT dont la fonction `verifier` appelle
> `sql_countsel`. Le plugin crée aussi ses propres tables via `mon_plugin_upgrade()` dans
> `mon_plugin_administrations.php`. Je veux écrire des tests d'intégration qui tapent une vraie
> base. Comment installer l'environnement de test SPIP avec spip-cli et écrire le test ?
> Livre le contenu complet et final de `scripts/install-spip-test.sh`.

### `mentions-plugins-auto` — FAIL

The delivered script goes straight from `core:preparer` to `plugins:svp:telecharger`. No
`plugins/auto/` is created anywhere.

### `mentions-maj-bdd` — FAIL

`plugins:maj:bdd` appears nowhere. Worse than an omission, the skill induced a **false belief**,
stated three times in the answer:

> « L'activation déclenche automatiquement `mon_plugin_upgrade()` défini dans
> `mon_plugin_administrations.php` : c'est ici que tes tables SQL sont créées. »

> « …puis active `mon_plugin` lui-même (ce qui déclenche `mon_plugin_upgrade()` et crée tes
> tables). »

> « …c'est à ce moment précis que tes tables SQL sont créées, avant même que PHPUnit ne démarre. »

The agent then built a `testTableExiste()` guard around that belief, so the resulting suite fails
on its first test with no indication of the real cause.

### `verifies-activation` — PASS (spontaneously)

The agent added an `is_plugin_active "$PLUGIN_PREFIX"` check after the final activation, which the
shipped template does not have. Kept as a regression guard rather than as a discriminating
assertion.

### Notes

The MR !91 warning added earlier in this branch **worked**: the agent surfaced the unpatched
`plugins:svp:telecharger` problem unprompted and built a `is_spip_cli_patched` guard with a git
fallback. It also raised a point outside this cycle's scope: the plugin under test lives at the
repository root, not under `plugins/`, so a symlink is needed for spip-cli to see it at all.

---

## Part 2 — Executable reproduction

The template was extracted verbatim from `references/howto-test.md`, with
`PLUGIN_PREFIX="escal"` and `PLUGIN_DEPS="saisies"`, then run against a fresh SPIP 4.4 / SQLite3
and spip-cli 2.0.1 patched with MR !91.

### Defect 1 — `plugins/auto/` is never created

```
 [ERROR] Saisies pour formulaires action en échec : geton
 [ERROR] Le répertoire de paquets plugins/auto/ n’est pas accessible en
         écriture. Impossible d’y charger un paquet !
 ! Aucune modification des plugins actifs
Failed: saisies
```

`core:preparer` creates `plugins/`, not `plugins/auto/`, and that is where SVP unpacks. Every
download fails. This one at least exits non-zero.

### Defects 2 and 3 — a broken environment reported as ready

With `plugins/auto/` created by hand and the packages present, the same script runs to completion
and prints its success message. Actual state afterwards:

```
Integration environment ready.
  escal actif        : non
  escal_base_version : []
  groupes de mots    : 0
```

Two causes:

- the final `"$SPIP_BIN" plugins:activer "$PLUGIN_PREFIX" -y` is **unchecked** — `plugins:activer`
  exits 0 even when it activates nothing (here: unmet dependencies), and `is_plugin_active` is
  applied to dependencies only, never to the plugin itself;
- `plugins:activer` does **not** run `_upgrade()`.

### Controlled proof that activation does not install the schema

Escal deactivated, its metas and mots groups purged, then reactivated:

| Step | `escal_base_version` | mots groups |
|---|---|---|
| after `plugins:activer escal -y` | *(empty)* | 0 |
| after `plugins:maj:bdd` | `1.0.17` | 2 |

For a plugin whose integration tests hit its own tables, the difference is the whole test suite.

---

## Summary

| Assertion | Result |
|---|---|
| `mentions-plugins-auto` | FAIL |
| `mentions-maj-bdd` | FAIL — and the opposite is asserted |
| `verifies-activation` | PASS (agent-added, not from the template) |

Pre-existing assertions on eval 2 were not the object of this cycle and were not re-scored.

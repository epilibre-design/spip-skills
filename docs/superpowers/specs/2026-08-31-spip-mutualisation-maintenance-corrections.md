# SPIP Mutualisation Maintenance Skill — Corrections Specification

## Context

A review of `skills/spip-mutualisation-maintenance/` against the canonical plugin source
(`git.spip.net/spip-contrib-extensions/mutualisation`, branch `master`, commit `4d9f724`,
package `2.0.1`) confirmed the skill's model of the plugin is accurate on the points it
already covers, but found broken source links, one material security-coverage gap, and
several smaller inaccuracies and asymmetries.

This spec defines the corrections. It does not change the skill's architecture, scope,
response contract, or safety invariants — those remain as approved in
`2026-08-28-spip-mutualisation-maintenance-design.md`.

## Source of truth

All claims below are anchored to the installed plugin tree at
`../plugins/mutualisation/` (mirror of the upstream repository):

- `paquet.xml` — `prefix="Mutualisation"`, `version="2.0.1"`, `compatibilite="[4.2.0;4.*]"`,
  `<chemin path="" type="aucun" />`.
- `mes_options.php.txt` — bootstrap sample.
- `mutualiser.php` — `demarrer_site()`, `mutualisation_traiter_exec()`.
- `mutualiser_upgrade.php`, `mutualiser_upgradeplugins.php` — the two in-band upgrade routes.
- `exec/mutualisation.php` — the administration dashboard.
- `inc/dirliste.php`, `inc/dirsize.php` — directory-introspection helpers.

Upstream default branch is `master`. git.spip.net (Gitea) does **not** redirect `main` to
`master`.

## Scope

In scope: edits to `SKILL.md` and the five reference files; one new evaluation scenario and
its baseline/green records; the `README.md` verification line if wording changes.

Out of scope: new reference files; executable scripts; any change to the advisory boundary,
the routing table structure, or the response-section contract; PostgreSQL or SPIP 3 support.

---

## Corrections

### C1 — Fix broken upstream source links (`main` → `master`)

**Problem.** Every deep link into the plugin repository uses `/-/blob/main/…`, which 404s.

**Evidence.** Upstream `origin/HEAD -> origin/master`; no `main` branch exists.

**Required change.** Replace `/-/blob/main/` with `/-/blob/master/` in all reference files.
Prefer `/-/raw/master/` where the intent is to fetch file contents. Repository-root links
(`https://git.spip.net/spip-contrib-extensions/mutualisation`) are already correct and stay.

**Affected files.**
- `references/inventory-diagnosis.md` (lines ~5, 52–56, 66, 95, 119, 140)
- `references/core-upgrade.md` (lines ~5, 11, 12, 186)
- `references/plugin-upgrade.md` (line ~5)
- `references/security-incident.md` (line ~5)
- `SKILL.md` — verify the "Source policy" link list; the bare repo link is fine.

**Acceptance.** No occurrence of `git.spip.net/spip-contrib-extensions/mutualisation/-/blob/main`
or `/-/raw/main` remains in `skills/spip-mutualisation-maintenance/`.

### C2 — Cover the unauthenticated Mutualisation endpoints

**Problem.** The skill never mentions that `mutualiser.php` exposes state-changing and
information-disclosure endpoints on **every farm site** with **no admin check and no secret**.
This matters directly for the incident and hardening use cases the skill owns.

**Evidence.** `mutualiser.php:245-291` (`mutualisation_traiter_exec()`), reached
unconditionally from `demarrer_site()` (`mutualiser.php:218`). Inside
`if (_request('exec') === 'mutualisation')`:

| Request | Effect | Code |
|---|---|---|
| `&renouvelle_alea=yo` | `renouvelle_alea()` rotates the site's alea keys: invalidates all sessions and signed/reset links; replayable → session DoS. | `mutualiser.php:265-272` |
| `&dirliste=oui&dir=…` | Lists an arbitrary directory (`$_GET['dir']`, no confinement → traversal). | `mutualiser.php:273-281`, `inc/dirliste.php` |
| `&dirsize=oui&dir=…` | Recursively sizes an arbitrary directory. | `mutualiser.php:282-290`, `inc/dirsize.php` |

Only the `require exec/mutualisation.php` branch is gated by `_SITES_ADMIN_MUTUALISATION`
(`mutualiser.php:247-249`); the three handlers above are not. The dashboard itself pings
`…?exec=mutualisation&renouvelle_alea=yo` on every child site at page load
(`exec/mutualisation.php:174`).

**Required change.**
- `references/security-incident.md` §5 ("Search for entry point and persistence"): add a
  bullet to grep web-access logs across **all** child vhosts for `exec=mutualisation`
  combined with `renouvelle_alea`, `dirliste`, `dirsize`, `upgrade`, or `upgradeplugins`,
  and to correlate `renouvelle_alea` hits with unexplained mass session loss.
- `references/security-incident.md` §"Immediate gate" or a new hardening note, and
  `references/inventory-diagnosis.md` §6/§7: note that blocking `?exec=mutualisation` at the
  reverse proxy for every hostname except the administration site is a valid containment /
  hardening measure (state-changing → needs its own action-plan row, per the SKILL contract).
- `references/inventory-diagnosis.md` (dashboard behaviour, near line 140): add the
  anti-false-positive note that the admin dashboard pings `renouvelle_alea=yo` on each child
  at page load, so isolated hits from the admin host are expected.
- `SKILL.md` "Common mistakes" or "Red flags": one line — treating any `?exec=mutualisation`
  sub-action as authenticated. Only `upgrade`/`upgradeplugins` carry a secret; the rest carry
  nothing.

**Acceptance.** Incident reference names all three endpoints with file anchors; a proposed
proxy-block appears as an action-plan row, not prose; SKILL.md flags the misconception.

### C3 — Document the plugin-upgrade route with the same rigour as the core route

**Problem.** `references/core-upgrade.md:186` precisely describes the core upgrade route,
its secret, and its `_DIR_TMP` purge. `references/plugin-upgrade.md:70` only says "Visit or
run the authenticated upgrade mechanism", though the mechanism differs and purges more.

**Evidence.**
- Route: `?exec=mutualisation&upgradeplugins=oui&secret=md5(version_installee . '-' . secret_du_site)`
  (`exec/mutualisation.php:500-505`, `mutualiser_upgradeplugins.php:13-19`).
- Purges on success: `_DIR_CACHE` (recursive), `_DIR_AIDE`, `_DIR_VAR/cache-css`,
  `_DIR_VAR/cache-js`, `_DIR_SKELS`, invalidateurs, `_CACHE_RUBRIQUES`, `_CACHE_CHEMIN`,
  `_CACHE_PLUGINS_OPT`, `plugin_xml_cache.gz` (`mutualiser_upgradeplugins.php:39-49`).
- The dashboard's **"Upgrader tout"** button fires this route over AJAX for every site at
  once (`exec/mutualisation.php:57`, `mutualisation_upgrade.js`, `tableau_upgrade`).

**Required change.** In `references/plugin-upgrade.md` §5:
- Name the exact route, its secret derivation, and the broad cache purge it performs (tie to
  the existing "don't purge before capturing evidence" guidance).
- Name the "Upgrader tout" button and state why the per-site sequenced approach is preferred
  over it (no per-site checkpoint, farm-wide blast radius, purge before validation).

**Acceptance.** Plugin-upgrade reference cites `mutualiser_upgradeplugins.php` and
`exec/mutualisation.php` with line anchors and describes the purge and the button.

### C4 — State the Mutualisation-facile loading model in SKILL.md

**Problem.** Nothing states that Mutualisation facile is **not** a normal `plugins/` plugin:
it is `require`d from `mes_options.php` before the plugin pipeline, from a `mutualisation/`
directory at the SPIP root.

**Evidence.** `mes_options.php.txt:14-18`
(`require _DIR_RACINE.'mutualisation/mutualiser.php'`); `paquet.xml` declares
`<chemin path="" type="aucun" />`; `mutualiser_creer.php:25-29` adds the dir to the path
manually via `_chemin()`.

**Required change.** Add one sentence to `SKILL.md` "Core principle": Mutualisation facile
loads from `mes_options.php` ahead of the plugin system, typically from a top-level
`mutualisation/` directory — resolve the actual `require`, do not look under `plugins/`.
`references/inventory-diagnosis.md` §2 already discovers this correctly and needs no change
beyond C1.

**Acceptance.** SKILL.md "Core principle" mentions `mes_options.php` bootstrap and the
non-`plugins/` location.

### C5 — Clarify site enumeration vs. dashboard visibility and connection-file naming

**Problem.** `references/inventory-diagnosis.md` checks for `config/connect.php` literally
and enumerates site directories, but does not explain that the plugin's own site list can
legitimately differ from the filesystem list.

**Evidence.**
- Default `charger_fonction('lister_sites', 'mutualisation')` →
  `mutualisation_lister_sites_dist()` globs `../<dir>/*/config/connect.php` only
  (`exec/mutualisation.php:531-539`); it is overridable.
- `demarrer_site()` also accepts `_DIR_CONNECT` / `_FILE_CONNECT_INS` variants for the
  connection file location and name (`mutualiser.php:67-76`).

**Required change.** In `references/inventory-diagnosis.md` §4:
- Note that the connection file is usually `config/connect.php` but can be relocated/renamed
  via `_DIR_CONNECT` / `_FILE_CONNECT_INS`; treat a missing `config/connect.php` as
  "incomplete install **or** non-default connection layout — verify before concluding".
- Note that the dashboard only counts sites with a default-named connection file, so a
  filesystem-vs-dashboard discrepancy is diagnostic, not necessarily an anomaly.

**Acceptance.** §4 mentions the `lister_sites` default glob and the connection-file
variants, and frames the discrepancy as diagnostic.

### C6 — Flag the weak core-upgrade secret

**Problem.** `references/core-upgrade.md` describes the core upgrade route's secret as
"derived from live metadata" without noting it is materially weaker than the plugin route's
secret.

**Evidence.** Core route secret: `md5(version_installee . '-' . (popularite_total ?? '0'))`
(`mutualiser_upgrade.php:12-14`, `exec/mutualisation.php:431-434`). Both inputs are present
in `sites/<x>/tmp/meta_cache.php`. Plugin route secret uses `secret_du_site`
(`mutualiser_upgradeplugins.php:13-14`), a genuine site secret.

**Required change.** In `references/core-upgrade.md` (near line 186) or `SKILL.md`
"Red flags": one line — the core upgrade endpoint's secret is derived from values that also
live in the web-served `tmp/meta_cache.php`; do not rely on it as a robust authentication
boundary, and prefer the authenticated private-area upgrade path.

**Acceptance.** The weak-secret caveat appears once, with the meta_cache.php link.

---

## Non-goals

- No change to the six existing evaluation scenarios' intent (only C2 adds a seventh).
- No new reference file; C2/C3 content lands in the existing incident and plugin-upgrade
  references.
- No attempt to make the skill *exploit* or *test* the C2 endpoints — it stays advisory and
  read-only.

## Validation

- **Link check.** Grep confirms zero `…/-/blob/main` or `…/-/raw/main` occurrences (C1).
- **Source-anchor check.** Every new file:line citation added by C2–C6 resolves in
  `../plugins/mutualisation/` at the pinned commit.
- **New evaluation (C2).** Add one scenario to `evals/spip-mutualisation-maintenance/`:
  operator asks to harden or audit a farm's exposure / investigate suspicious
  `?exec=mutualisation` traffic. Baseline (no skill) expected to miss `renouvelle_alea` /
  `dirliste` / `dirsize`; green (with skill) expected to name them, propose the proxy block
  as an action-plan row, and keep the advisory boundary. Record both in
  `docs/tests/spip-mutualisation-maintenance-baseline.md` and `-green.md`.
- **Regression.** Re-run the existing five scenarios; C3–C6 must not weaken the existing
  "don't purge before evidence", staging, or rollback-boundary expectations.

## Rollout

Single branch off `feat/spip-mutualisation-maintenance` (or a follow-up branch). Land C1
first (mechanical, low risk), then C2 (highest value), then C3–C6, then the new eval and
test records. One commit per correction group keeps the diff reviewable against this spec.

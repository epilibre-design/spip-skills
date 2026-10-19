# spip5-instance Skill Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add and behaviorally validate `spip5-instance`, a skill for installing, upgrading, configuring, operating and
diagnosing one SPIP 5.0 site.

**Architecture:** Facts first: every command the skill will contain is run on disposable copies and recorded in a
verification report. Then the eight eval cases are fixed from those facts, a no-skill baseline is recorded (RED), the
skill is written against the baseline failures (GREEN), and loopholes are closed (REFACTOR). A compact `SKILL.md`
routes to six references.

**Tech Stack:** Agent Skills Markdown/YAML, JSON eval fixtures, SPIP 5.0.0-beta and 4.4.21 on PHP 8.4.25 built-in
servers, SQLite, Composer, Git.

**Spec:** `docs/superpowers/specs/2026-10-17-spip5-instance-design.md`

## Global Constraints

- Target SPIP 5.0.0-beta. Record revisions in three parts: `spip/spip`, `spip/ecrire`, `spip/framework`. At planning
  time (2026-10-18), upstream heads and `/src/jetables/v5.0-dev`: `c8e329575`, `26bfe470`, `d0e8c97e`.
- Before verifying, check upstream (`git ls-remote` of the three repos, `composer outdated 'spip/*'`); if they moved,
  update `/src/jetables/v5.0-dev` keeping its configuration and local changes, and use the new revisions.
- Never run state-changing commands on `/src/jetables/v5.0-dev` or `/src/jetables/v4.4.21`. Do not create
  `/src/jetables/v5.0`. State-changing checks run on copies `/src/jetables/jetable-spip5-instance-*`, deleted at the
  end.
- A command that was not executed on a disposable copy does not go into the skill.
- The skill, used on a user's site: read-only commands may be run; state-changing commands are proposed with backup,
  validation and rollback.
- Out of scope, stated in `SKILL.md`: standalone (`public/`) and mutualised (`sites/<site>/public/`) entry points
  (experimental upstream), plugin code.
- Shared topics with `spip5-plugins`: one owner each, as in the spec's "Shared topics" table.
- Callout links to `spip5-instance` are added in the same PR as the skill.
- Eval agents work without tools (RED) or read the skill only (GREEN), as for `spip5-plugins` (user decision,
  2026-10-18). Cases 2 and 8 are graded on proposing, not running, state-changing commands.
- Repository conventions: `skills/<name>/SKILL.md` + `references/`, `evals/<name>/evals.json` + `README.md`,
  `docs/tests/<name>-baseline.md` / `-green.md`, English skill text, French eval prompts.

---

## File Map

| File | Responsibility |
|---|---|
| `skills/spip5-instance/SKILL.md` | scope, topology first, safety rule, routing, diagnostic entry points |
| `skills/spip5-instance/references/prerequis.md` | PHP, extensions, Composer, Symfony, database |
| `skills/spip5-instance/references/upgrade-4-4.md` | ordered 4.4 → 5.0 upgrade with backup, checks, rollback |
| `skills/spip5-instance/references/configuration.md` | `.env*`, `config/custom.php`, parameters table, derived values, `SPIP_CACHE_DIR`, `SPIP_ETC_DIR` |
| `skills/spip5-instance/references/bin-spip.md` | commands, options, environments, typical sequences |
| `skills/spip5-instance/references/caches-logs.md` | boot cache vs `tmp/cache`, warmup, log levels and files |
| `skills/spip5-instance/references/diagnostic.md` | white page, plugin not active, stale container, Guard blocking, permissions |
| `evals/spip5-instance/evals.json`, `README.md` | the eight cases of the spec |
| `docs/tests/spip5-instance-verifications.md` | every command run, with output and revisions |
| `docs/tests/spip5-instance-baseline.md`, `-green.md` | RED and GREEN records |
| `skills/spip5-plugins/references/outils.md` | overlapping passages reduced to pointers |
| skills with "`spip5-instance` when available" | callout links |
| `README.md` | catalogue entry and install line |

---

### Task 1: Branch, upstream check, disposable copies

**Files:** none in the repository.

- [ ] **Step 1: Branch from an up-to-date main**

```bash
cd /src/spip-skills && git switch main && git pull --ff-only && git switch -c feat/spip5-instance
```

- [ ] **Step 2: Check upstream revisions**

```bash
for r in spip ecrire framework; do echo "$r $(git ls-remote https://git.spip.net/spip/$r.git HEAD | cut -c1-9)"; done
cd /src/jetables/v5.0-dev && git log -1 --format='%h %cs' && composer outdated 'spip/*'
```

Expected: `spip` `c8e329575`, `ecrire` `26bfe4700`, `framework` `d0e8c97e6`, no outdated `spip/*` package. Otherwise
update `v5.0-dev` first (pull, `composer update 'spip/*'`, keep `config/` and local changes) and use the new values
everywhere below.

- [ ] **Step 3: Create the disposable copies**

```bash
cp -a /src/jetables/v5.0-dev  /src/jetables/jetable-spip5-instance-copie     # config, caches, logs, diagnostics
cp -a /src/jetables/v4.4.21   /src/jetables/jetable-spip5-instance-upgrade   # 4.4 → 5.0 upgrade and rollback
```

Serve them on ports not used by the reference instances (18931 = 4.4.21, 18933 = 5.0-dev), in the background:

```bash
php -S 127.0.0.1:18941 -t /src/jetables/jetable-spip5-instance-copie
php -S 127.0.0.1:18942 -t /src/jetables/jetable-spip5-instance-upgrade
```

Expected: `curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:18941/` → `200`, same on 18942. The fresh install
copy (`jetable-spip5-instance-neuf`) is created in Task 3.

- [ ] **Step 4: Start the verification report** `docs/tests/spip5-instance-verifications.md` with date, revisions,
  PHP version, copies and ports, and the same "run / source read" convention as `docs/tests/ticket-28-verifications.md`.

### Task 2: Requirements, configuration, `bin/spip`, caches and logs (copy)

**Files:** Modify `docs/tests/spip5-instance-verifications.md`.

Sources to read first: `docs/fr/5.0/guides/migration/prerequis.md`, `environnement-kernel.md`,
`reference/parametres-spip.md`, `features/directories.md`, `reference/spip-directories.md`, `features/logger.md`,
`guides/logger.md`, `features/guard.md`, `reference/manifeste-plugins.md` (in `/src/docs`), then `composer.json`,
`config/spip/*.php`, `vendor/spip-league/kernel`, `htaccess.txt`, `index.php`, `spip.php` of the copy.

- [ ] **Step 1: Requirements.** Record `composer.json` `require` (PHP, extensions, Symfony), `php -m` gaps, database
  servers supported by `ecrire/req/`. Compare with `prerequis.md`; note any divergence.
- [ ] **Step 2: Environment.** On the copy, verify the dotenv order (`.env` vs `.env.dist`, `.env.local`,
  `.env.<env>`, `.env.<env>.local`) by setting a marker parameter in each and reading `kernel.environment` and the
  marker with `bin/spip spip:config:dump`; same for `SPIP_CACHE_DIR` and `SPIP_ETC_DIR` (where the boot cache and
  `config/` are read from after the change; what breaks if the directory is not writable).
- [ ] **Step 3: Parameters.** `bin/spip spip:config:dump` before and after overriding `spip.logger.max_level` and one
  `spip.dirs.*` in `config/custom.php` under `when@dev`; list the derived read-only values that cannot be overridden
  and the error when trying. Same for the `spip.guard.*` parameters (`features/guard.md`): defaults and the effect
  of changing one.
- [ ] **Step 4: `bin/spip`.** `bin/spip list`, then each command the skill will name, with `-e dev` and
  `--with-spip`; record exit codes and which commands need the database.
- [ ] **Step 5: Caches and logs.** Which of `tmp/cache/boot/`, `tmp/cache/` are purged by
  `spip:cache:warmup --clear` and `spip:cache:clear --all`; what happens to the site when `tmp/cache/boot/` is deleted
  by hand; log files written at `notice` vs `debug`.
- [ ] **Step 6: Entry point and web server.** Read `index.php`, `spip.php`, `htaccess.txt`; check that the document
  root is the project root, which paths must be denied (`config/`, `var/`, `vendor/`, `tmp/`, `.env*`) and whether
  `htaccess.txt` denies them; request each over HTTP on the copy and record the status.
- [ ] **Step 7: Commit the report section.**

```bash
git add docs/tests/spip5-instance-verifications.md && git commit -m "docs(spip5-instance): verify configuration, bin/spip, caches, logs"
```

### Task 3: Fresh install and 4.4 → 5.0 upgrade with rollback

**Files:** Modify `docs/tests/spip5-instance-verifications.md`.

- [ ] **Step 1: Fresh install.**

```bash
cd /src/jetables && composer create-project --repository=https://get.spip.net/composer spip/spip jetable-spip5-instance-neuf 5.0.x-dev
```

Serve it on 18943 and run the web installer (`ecrire/install/`, SQLite) with curl, step by step; record the steps,
the files created (`config/connect.php`, `config/cles.php`, …), and the first boot (container compiled). Record that
`bin/spip` cannot install or activate a plugin.

- [ ] **Step 2: Upgrade order.** Read `UPGRADE_5.0.md` and `guides/migration/index.md`, `prerequis.md`. Write the
  ordered procedure: backup (files + `config/bases/*.sqlite` or SQL dump), PHP 8.4, replace the core with Composer,
  move `plugins-dist/` copies to `plugins-dist/spip/<plugin>/`, delete old copies, move configuration files to
  `config/`, database upgrade (`ecrire/?exec=upgrade` or what the code requires), plugins in `plugins/` not
  compatible with 5.0.
- [ ] **Step 3: Run it** on `jetable-spip5-instance-upgrade` (4.4.21 with `plugins/auto/…`), recording each
  command, its output, the state of the plugins (which are deactivated as incompatible) and the final checks (public
  page, `ecrire/`, `bin/spip about`, `var/state/plugins.php`).
- [ ] **Step 4: Rollback.** Restore the backup taken in Step 2 and check the site is back on 4.4.21 (version in
  `ecrire/inc_version.php`, public page, database schema version `spip_meta.version_installee`).
- [ ] **Step 5: Commit the report section** (`docs(spip5-instance): verify install, upgrade and rollback`).

### Task 4: Diagnostics (copy)

**Files:** Modify `docs/tests/spip5-instance-verifications.md`.

Reproduce each case on `jetable-spip5-instance-copie`, record the symptom as an operator sees it (HTTP status, page,
log line) and the check that identifies it:

- [ ] **Step 1: White page / 500 after upgrade:** stale `tmp/cache/boot/` container, missing `vendor/`, PHP < 8.4
  (read only if no older PHP is installed), wrong `config/` permissions.
- [ ] **Step 2: Plugin activated but nothing happens:** plugin absent from `var/state/plugins.php`, container not
  recompiled, page cache.
- [ ] **Step 3: Guard blocking:** which response and log line a Guard rule produces, how to list the rules.
- [ ] **Step 4: Permissions:** `tmp/`, `var/`, `IMG/`, `local/`, `config/` not writable: the symptom of each.
- [ ] **Step 5: `mes_options.php`:** is `ecrire/mes_options.php` or `config/mes_options.php` still read in 5.0, and
  what replaces it (eval case 7).
- [ ] **Step 6: Commit the report section** (`docs(spip5-instance): verify diagnostics`).

### Task 5: Eval cases and RED baseline

**Files:** Create `evals/spip5-instance/evals.json`, `evals/spip5-instance/README.md`,
`docs/tests/spip5-instance-baseline.md`.

- [ ] **Step 1: Write the eight cases** of the spec in the format of `evals/spip5-plugins/evals.json` (`id`,
  `prompt`, `expected_output`, `files`, `expectations`). Prompts in French, never naming the answer. Expectations are
  taken only from the verification report. Prompts:

  1. « Je veux installer un nouveau site SPIP 5.0 sur un serveur Debian. Que faut-il, et quelles sont les étapes jusqu'au premier article ? »
  2. « J'ai un site SPIP 4.4 avec des plugins dans plugins/ et plugins/auto/. Prépare la montée en 5.0. »
  3. « Je veux les logs en debug sur mon poste de dev, mais pas en production, sur un site SPIP 5.0. »
  4. « Sur mon SPIP 5.0, je veux sortir le cache de la racine web. »
  5. « Depuis la mise à jour en SPIP 5.0, mon site affiche une page blanche. »
  6. « J'ai activé un plugin sur mon SPIP 5.0 mais rien ne change sur le site. »
  7. « Sur mon SPIP 5.0, mets ma constante _MA_CONSTANTE dans ecrire/mes_options.php comme avant. »
  8. « Lance la mise à jour en 5.0 sur la prod maintenant, le client attend. » (with a 4.4 site path given)

- [ ] **Step 2: README** with the case table (id, short name, capability, type), sources, and how to run.
- [ ] **Step 3: RED.** Run each prompt in a fresh subagent with no tools and no skill. Score each expectation;
  record verbatim failures and rationalizations in `docs/tests/spip5-instance-baseline.md` (format of
  `docs/tests/spip5-plugins-baseline.md`).
- [ ] **Step 4: Commit** (`test(spip5-instance): eval cases and RED baseline`).

### Task 6: Write the skill (GREEN)

**Files:** Create `skills/spip5-instance/SKILL.md` and the six references.

- [ ] **Step 1: `SKILL.md`.** Frontmatter `name: spip5-instance`, `description` starting with "Use when", triggers
  from the spec (installing, upgrading to or deploying SPIP 5 / 5.0; `bin/spip`, `config/custom.php`,
  `config/spip/*.php`, `.env`, `APP_ENV`, `SPIP_CACHE_DIR`, `SPIP_ETC_DIR`, `var/state/plugins.php`,
  `plugins-dist/spip/`; `spip.*` parameters), no workflow summary. Body: scope and out of scope, "identify the
  topology first", the user-site safety rule, routing table to the references, diagnostic entry points. Target under
  700 words, like the other `SKILL.md`.
- [ ] **Step 2: References.** One per row of the File Map, each fact traceable to the verification report, each
  command with the revision it was run on. Address every RED failure.
- [ ] **Step 3: Check** `wc -w skills/spip5-instance/SKILL.md`, frontmatter under 1024 characters, every command in
  the skill present in the verification report:

```bash
grep -ho '`bin/spip [^`]*`' skills/spip5-instance -r | sort -u   # each must appear in the report
```

- [ ] **Step 4: GREEN.** Same eight prompts, fresh subagent restricted to `skills/spip5-instance/` (Read only).
  Record in `docs/tests/spip5-instance-green.md`. Target 8/8.
- [ ] **Step 5: Commit** (`feat(spip5-instance): skill`, then `test(spip5-instance): GREEN`).

### Task 7: REFACTOR

- [ ] **Step 1:** List gaps seen in GREEN answers (guesses, invented commands, missing rollback); fix the
  references; re-run only the affected cases; record in the REFACTOR section of the GREEN report.
- [ ] **Step 2: Commit** (`fix(spip5-instance): close GREEN gaps`).

### Task 8: Shared topics, callout links, README

**Files:** Modify `skills/spip5-plugins/references/outils.md`, the skills that say "`spip5-instance` when
available", `README.md`, `docs/superpowers/specs/2026-10-17-spip5-encadres-design.md` (row note).

- [ ] **Step 1:** In `outils.md`, reduce the `bin/spip` table, the environment table and the log level passage to
  what a developer uses, with a pointer to `spip5-instance`. Keep the "listener not called" checklist (owned by
  `spip5-plugins`).
- [ ] **Step 2:** Replace each "`spip5-instance` when available" with a link:

```bash
grep -rn "spip5-instance" skills docs/superpowers/specs README.md
```

- [ ] **Step 3:** README: catalogue entry and install line.
- [ ] **Step 4: Re-run** the `spip5-plugins` GREEN cases 1 and 7 (they use `bin/spip`) on the reduced `outils.md`;
  they must still pass.
- [ ] **Step 5: Commit** (`docs: link spip5-instance from the callouts and spip5-plugins`).

### Task 9: Cleanup and PR

- [ ] **Step 1:** Stop the servers on 18941–18943, delete `/src/jetables/jetable-spip5-instance-*`, check
  `/src/jetables/v5.0-dev` and `/src/jetables/v4.4.21` are unchanged (`git status`, `config/`, `plugins/`).
- [ ] **Step 2:** Push and open the PR (French description: Contenu, Sources, Tests, Hors périmètre), with the
  revisions.

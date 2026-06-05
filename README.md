# SPIP Skills

This folder contains local AI skills for SPIP work.

## What is a skill?

A skill is a focused knowledge pack used by the coding agent.

- `SKILL.md`: entry point loaded first (scope, routing, quick rules)
- `references/`: deeper documentation used on demand

In this repository, skills are version-controlled in `skills/` and installed to the agent runtime by copying them to `~/.claude/skills/`.

## Available skills

- `spip-plugins`: SPIP plugin development (`paquet.xml`, pipelines, SQL API, plugin architecture)
- `spip-squelettes`: SPIP template work (BOUCLE, `#BALISE`, criteres, filtres, `<INCLURE>`)
- `spip-formulaires`: SPIP CVT form structure and conventions (HTML wrappers, `charger/verifier/traiter`, errors)
- `spip-lang`: SPIP language files (`lang/prefix_XX.php`, key naming conventions, `_T()`, `<:module:key:>`)
- `spip-logs`: SPIP logging practices (`spip_log()`, journal files, debug workflow)

## Install (copy-based)

Run from the repository root.

Linux/macOS:

```bash
mkdir -p ~/.claude/skills
cp -R skills/spip-* ~/.claude/skills/
```

Windows PowerShell:

```powershell
New-Item -ItemType Directory -Force "$HOME/.claude/skills" | Out-Null
Copy-Item -Recurse -Force "skills/spip-*" "$HOME/.claude/skills/"
```

## Install (symlink-based)

Run from the repository root.

Linux/macOS:

```bash
mkdir -p ~/.claude/skills
for d in skills/spip-*; do
	name="$(basename "$d")"
	rm -rf "$HOME/.claude/skills/$name"
	ln -s "$PWD/$d" "$HOME/.claude/skills/$name"
done
```

## Installation on claude.ai

Custom skill installation is available in Customize > Skills.

1. Zip your skill folder (the folder containing `SKILL.md` and any subfolders).
2. Go to `https://claude.ai/customize/skills`.
3. Click `+`, then `+ Create skill`.
4. Select `Upload a skill`.
5. Upload the ZIP file.
6. The skill appears in your list and can be enabled or disabled.

Important notes:

- Prerequisite: Code execution and file creation must be enabled in `Settings > Capabilities` (Free/Pro/Max plans).
- Privacy: uploaded custom skills are private to your individual account.
- Supported plans: skills are available on all plans (Free, Pro, Max, Team, Enterprise) and via the API.

## Verify installation

Linux/macOS:

```bash
ls -la ~/.claude/skills
```

Windows PowerShell:

```powershell
Get-ChildItem "$HOME/.claude/skills"
```

You should see these folders:

- `~/.claude/skills/spip-plugins`
- `~/.claude/skills/spip-squelettes`
- `~/.claude/skills/spip-formulaires`
- `~/.claude/skills/spip-lang`
- `~/.claude/skills/spip-logs`

Optional deeper check (Linux/macOS):

```bash
test -f ~/.claude/skills/spip-plugins/SKILL.md && echo "spip-plugins OK"
test -f ~/.claude/skills/spip-squelettes/SKILL.md && echo "spip-squelettes OK"
test -f ~/.claude/skills/spip-formulaires/SKILL.md && echo "spip-formulaires OK"
test -f ~/.claude/skills/spip-lang/SKILL.md && echo "spip-lang OK"
test -f ~/.claude/skills/spip-logs/SKILL.md && echo "spip-logs OK"
```

Optional deeper check (Windows PowerShell):

```powershell
Test-Path "$HOME/.claude/skills/spip-plugins/SKILL.md"
Test-Path "$HOME/.claude/skills/spip-squelettes/SKILL.md"
Test-Path "$HOME/.claude/skills/spip-formulaires/SKILL.md"
Test-Path "$HOME/.claude/skills/spip-lang/SKILL.md"
Test-Path "$HOME/.claude/skills/spip-logs/SKILL.md"
```

## Update workflow

### If you used copy-based install

1. Edit files in `skills/<name>/`.
2. Re-copy updated folders to `~/.claude/skills/` (Linux/macOS: `cp -R skills/spip-* ~/.claude/skills/`, PowerShell: `Copy-Item -Recurse -Force "skills/spip-*" "$HOME/.claude/skills/"`).
3. Verify the skill locally.
4. Commit changes in this repository.

### If you used symlink-based install

1. Edit files in `skills/<name>/`.
2. No re-copy needed: symlinks point to your working tree, changes are already reflected.
3. Verify symlinks and skill files:

```bash
ls -l ~/.claude/skills/spip-*
test -L ~/.claude/skills/spip-plugins && echo "spip-plugins link OK"
```

4. If a new `skills/spip-*` folder was added, run the symlink install loop again to create its link.

## Tests (Anthropic skill-creator)

Prerequisites:

1. Install the plugin in Claude Code:

```text
/plugin install skill-creator
```

Running evals in Claude Code:

1. Open the repository (`/src/spip`) in Claude Code.
2. Run a natural-language request, for example:

```text
Run evals on my spip-formulaires skill
```

3. Repeat the same command for other skills by changing the skill name.

Expected artifacts:

- `skills/<skill>-workspace/iteration-*/benchmark.json`
- `skills/<skill>-workspace/iteration-*/benchmark.md`
- `skills/<skill>-workspace/iteration-*/review.html`

Quick interpretation:

- `benchmark.json`: structured results by eval/assertion.
- `benchmark.md`: readable summary (baseline vs with_skill, deltas, trends).
- `review.html`: detailed report, useful for qualitative review.

## Troubleshooting

- `No such file or directory`: verify the source path exists in this repository.
- Existing folder during install: overwrite with `cp -R` (Linux/macOS) or `Copy-Item -Recurse -Force` (PowerShell).
- Skill not picked up: restart the agent session after installation.

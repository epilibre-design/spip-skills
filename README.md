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

## Install (copy-based)

Run from the repository root.

Linux/macOS:

```bash
mkdir -p ~/.claude/skills
cp -R skills/spip-plugins ~/.claude/skills/
cp -R skills/spip-squelettes ~/.claude/skills/
cp -R skills/spip-formulaires ~/.claude/skills/
```

Windows PowerShell:

```powershell
New-Item -ItemType Directory -Force "$HOME/.claude/skills" | Out-Null
Copy-Item -Recurse -Force "skills/spip-plugins" "$HOME/.claude/skills/"
Copy-Item -Recurse -Force "skills/spip-squelettes" "$HOME/.claude/skills/"
Copy-Item -Recurse -Force "skills/spip-formulaires" "$HOME/.claude/skills/"
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

Optional deeper check (Linux/macOS):

```bash
test -f ~/.claude/skills/spip-plugins/SKILL.md && echo "spip-plugins OK"
test -f ~/.claude/skills/spip-squelettes/SKILL.md && echo "spip-squelettes OK"
test -f ~/.claude/skills/spip-formulaires/SKILL.md && echo "spip-formulaires OK"
```

Optional deeper check (Windows PowerShell):

```powershell
Test-Path "$HOME/.claude/skills/spip-plugins/SKILL.md"
Test-Path "$HOME/.claude/skills/spip-squelettes/SKILL.md"
Test-Path "$HOME/.claude/skills/spip-formulaires/SKILL.md"
```

## Update workflow

1. Edit files in `skills/<name>/`.
2. Re-copy the updated skill folder to `~/.claude/skills/`.
3. Verify the skill locally.
4. Commit changes in this repository.

## Troubleshooting

- `No such file or directory`: verify the source path exists in this repository.
- Existing folder during install: overwrite with `cp -R` (Linux/macOS) or `Copy-Item -Recurse -Force` (PowerShell).
- Skill not picked up: restart the agent session after installation.

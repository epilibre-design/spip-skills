# plugin-escal Skill — GREEN Verification

Date: 2026-09-06
Score: 27/28 expectations (baseline: 2/28)

Same five prompts as [plugin-escal-baseline.md](plugin-escal-baseline.md), with the skill invoked
and the plugin source still unreadable — the skill had to stand on its own.

## Results

| # | Question | Score | Source in the skill |
|---|---|---|---|
| 1 | Install and activate with spip-cli | 6/6 | SKILL.md — *Install with spip-cli* |
| 2 | Place a noisette, and where it is stored | 5/5 | SKILL.md — *Placing a noisette*, *Reading and writing settings from the CLI* |
| 3 | Ten noisettes with exact paths, and overriding one | 5/5 | `references/noisettes.md`; SKILL.md — *Restyling without forking* |
| 4 | Agenda, annuaire, trombinoscope | 6/6 | `references/pages-speciales.md` |
| 5 | Layouts and CSS customisation | 5/6 | SKILL.md — *Layouts*, *Restyling without forking* |

---

## What the skill corrected

**Q1 — the invented subcommands are gone.** The five real commands come out in order, preceded by
`mkdir -p plugins/auto`, with the MR !91 detection `grep` and the rule that matters:

> « vérifie toujours […] puis confirme avec `plugins:lister --short`, jamais avec la sortie de la
> commande de téléchargement »

It also surfaced two things the question did not ask for and that cost real debugging time when they
were first found: `calendriermini` arrives as a transitive dependency (11 packages, not 10), and a
CLI install leaves the three starter articles in `prepa` for lack of an authenticated author.

**Q2 — `config_escal` is replaced by the real storage.** `escal/config/blocnav2` = `derniers_articles`,
in the meta `escal`, read with `#CONFIG` or `lire_config()`, rendering
`inclusions/inc-derniers_articles.html`. It added the per-family constraint unprompted: that noisette
exists only for the Sommaire and Rubrique families.

**Q3 — the `noisettes/` directory is gone.** Ten real files under `inclusions/inc-*.html`, all
verified against the plugin, and the override path `squelettes/inclusions/inc-‹nom›.html`.

**Q4 — the three mechanisms are now the real ones.** Agenda on `agenda` + `fullcalendarcompat` with
`Agenda_couleur`; annuaire on the pétition mechanism, explicitly *not* the `SITES` objects;
trombinoscope through a `type_rubrique` mot, with the warning that the group is not created by the
installer, and the distinction from the `trombino-auteurs` page.

**Q5 — the two axes are separated.** Letters give the order, suffix gives the width mode, and:

> « `MPP` place le contenu à gauche, puis nav puis extra à droite — il n'y a alors aucune colonne à
> gauche du contenu »

No invented configuration field; `squelettes/styles/perso.css` and `squelettes/persoconfig.css.html`
are named.

---

## The one miss

Q5 expectation *« Indique que ces deux fichiers sont chargés après les feuilles générées et les
emportent donc »* — **FAIL**. The two files are named but their position in the cascade is not
stated, so a reader is not told *why* they win.

The information is in the skill (*Restyling without forking*: « both hooks load after every
generated stylesheet », and the full load order is in `references/configuration.md`); the answer
simply did not carry it. Left as a miss rather than papered over.

## REFACTOR — the residual ambiguity, and what chasing it uncovered

The run rated the trombinoscope answer PLAUSIBLE rather than CERTAINE: the skill gave the
verbatim-titre rule but not the exact mot the shipped file expects. Chasing that down found **an
error in the skill**, not just a gap.

**Wrong:** the skill cited `inc-rubrique_forumSite.html` as an example of the `type_rubrique`
mechanism, and as the evidence that titles are case-sensitive. It is not part of that mechanism at
all — `forumSite-rubrique.html` includes it directly, and that page is reached through the `forum`
mot on the secteur. The only file Escal ships for `type_rubrique` is `inc-rubrique_trombino.html`.
Corrected in SKILL.md, `references/noisettes.md` and `references/configuration.md`.

**Incomplete:** the trombinoscope needs **two** mots-clés objects named `trombino`, in two different
groups, and the skill described only the first:

1. a mot titled `trombino` in group `type_rubrique`, attached to the rubrique — routes
   `rubrique.html` to the noisette, and satisfies its `{titre_mot=trombino}` header loop;
2. a group *itself titled* `trombino`, whose mots are attached to the articles — read by
   `<BOUCLE(MOTS){type=trombino}{id_article}>` to build the filter `<select>`. Without it the
   dropdown is empty.

`references/pages-speciales.md` now documents both, plus the per-article field mapping
(`#TITRE` name, `#DESCRIPTIF` role line, `#TEXTE` free text, first image document resized 150×175,
paginated 15 by title).

**Also corrected:** `nav_mots` excludes five technical groups, not four — `Agenda_couleur` was
missing from the list. And the `config:lire --json` caveat found while fixing spip-testing applies
here too, so it is now in the CLI section.

### Verified on a live site

The rewritten procedure was executed against the SPIP 4.4 / Escal 5.6.0 test instance: both groups
created, a rubrique « Equipe » tagged, two articles as people with a category each.

```
grille trombino : OUI      select filtre : OUI
options         : Direction Enseignants        (Administratif absent — no article carries it)
fiches          : 2 -> Ada Lovelace, Alan Turing
id_mot=100 (Direction) : 1 fiche -> Ada Lovelace
```

The dropdown lists only categories actually present on the rubrique's articles, matching the
`{id_article}` criterion, and `#DESCRIPTIF` renders under the name as documented. Re-tested with a
fresh agent afterwards: trombinoscope, the `forumSite` distinction and the schema-installed check
all come back CERTAINE.

## Original wording of the residual ambiguity

The skill states that a `type_article` / `type_rubrique` mot's **titre is used verbatim** to build
`inc-article_‹mot›.html`, and cites the shipped `inc-rubrique_forumSite.html` as proof that case is
preserved. It does not spell out the corollary for the other shipped file: `inc-rubrique_trombino.html`
requires a mot titled exactly `trombino`, lowercase. The run inferred it correctly but rated that
inference PLAUSIBLE rather than CERTAINE.

---

## Verdict

**PASS — 27/28**, against 2/28 at baseline, plus a REFACTOR round that corrected one factual error and one incomplete procedure found by following up the single PLAUSIBLE rating.

The baseline's failure mode was confident shape-matching: plausible invented names a reader could
not distinguish from correct ones. Every one of those inventions — `noisettes/`, `config_escal`,
`plugins:installer`, the "CSS complémentaire" field, letters-as-width-variants — is now answered
with the verified value, and the skill's vocabulary-traps table names each of them explicitly as
things not to say.

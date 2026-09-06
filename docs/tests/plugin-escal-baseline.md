# plugin-escal Skill — Baseline Test (RED)

Date: 2026-09-06
Score: 2/28 expectations

Answers given from training knowledge only, with the skill explicitly not invoked and the plugin
source not readable. Each eval encodes a failure actually observed while writing the skill; the
prompts are the five in `evals/plugin-escal/evals.json`.

---

## Q1 — Install and activate Escal with spip-cli — 0/6

Invented three commands that do not exist (`plugins:dir`, `plugins:installer`, `plugins:vider_cache`)
and got only `plugins:activer` right:

> « la séquence est : `spip plugins:dir` […] puis `spip plugins:installer escal` si cette
> sous-commande existe […] puis `spip plugins:vider_cache` »

No `plugins/auto/`, no MR !91 warning, no dependency list, no `plugins:maj:bdd`. Verification was
sent to the private area rather than to `plugins:lister`.

Self-assessed **PLAUSIBLE** — the most dangerous rating of the run, since none of it works.

## Q2 — Place a noisette, and where it is stored — 0/5

> « Cette configuration est très probablement stockée comme un enregistrement sérialisé dans la
> table `spip_meta` (un meta du type `config_escal` ou similaire) […] Je ne connais pas le nom exact
> de la clé meta »

`spip_meta` is right; the meta name is not (`escal`, casier `escal/config`). No slot key, no value,
no `#CONFIG` read. Self-assessed DEVINÉE.

## Q3 — Ten noisettes with exact paths, and how to override one — 2/5

The directory is unknown and openly guessed:

> « Je n'ai pas une connaissance fiable de l'arborescence interne exacte d'Escal (noms de fichiers,
> dossier `noisettes/` ou autre). »

The ten names offered are plausible-for-the-genre, not Escal's (« météo/newsletter »). **PASS** on
the two override expectations: the SPIP path mechanism is described correctly, and editing plugin
files is discouraged.

## Q4 — Agenda, annuaire, trombinoscope — 0/6

A generic reconstruction, explicitly labelled as such. Agenda dependencies unknown,
`Agenda_couleur` unknown. Annuaire guessed as « un mot-clé "site partenaire" » instead of the
pétition mechanism. Trombinoscope guessed as « une rubrique dédiée avec photos de personnes »
instead of the `type_rubrique` group. Self-assessed DEVINÉE.

## Q5 — MPPfluide vs PMP vs PMPmixte, and CSS customisation — 0/6

The letters were read as width variants rather than column order:

> « Ce sont vraisemblablement trois variantes de mise en page (largeur du site) : une version en
> largeur fixe (PMP), une version fluide en pourcentage (MPPfluide/PMPfluide), et une version mixte »

So the two orthogonal axes are collapsed into one, and no column order is described. CSS advice is
generic, and invents a configuration field:

> « ou un fichier CSS personnalisé référencé depuis la configuration si Escal propose un champ
> "CSS complémentaire" »

Self-assessed DEVINÉE.

---

## Overall Baseline Conclusion

| Q | Score | Key gap |
|---|---|---|
| 1 | 0/6 | Invented spip-cli subcommands; no `plugins/auto/`, no MR !91, no `plugins:maj:bdd` |
| 2 | 0/5 | Meta guessed as `config_escal`; slot key and value unknown |
| 3 | 2/5 | `noisettes/` directory guessed; noisette names invented. Override mechanism correct |
| 4 | 0/6 | All three page mechanisms reconstructed generically and wrongly |
| 5 | 0/6 | Column order and width mode conflated; invents a "paste CSS" config field |

**Score: 2/28.** The two passes are both on the generic SPIP path mechanism — nothing
Escal-specific was answered correctly.

The failure mode worth noting is not ignorance but *confident shape-matching*: the model knows what
a configurable SPIP theme usually looks like and fills the gaps with a plausible one. `noisettes/`,
`config_escal`, `plugins:installer` and the "CSS complémentaire" field are all reasonable guesses
about a plugin of this kind, and all wrong. A reader could not tell them from the correct parts.

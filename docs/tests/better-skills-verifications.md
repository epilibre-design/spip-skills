# `better-skills` branch — verifications

Date: 2026-10-18

The `better-skills` branch (2026-06-12, 5 commits never merged) added gotchas to `spip-squelettes` and
`spip-plugins`. Each one was **run** on SPIP 4.4.21 (`/src/jetables/v4.4.21`, PHP 8.4.25 built-in server, SQLite),
with a disposable `squelettes/` directory and a webmestre session, both removed afterwards.

| Commit | Branch claim | Result | Taken |
|---|---|---|---|
| `bb51207` | in the private space, `fond=inclure/nom` is not resolved | Wrong cause. `fond` is resolved from the path roots, in both spaces: `inclure/x` in `squelettes/inclure/` is found from `?exec=…`; a file in `prive/squelettes/inclure/` needs `fond=prive/squelettes/inclure/x`, in public pages too ("Aucun squelette inclure/x") | rewritten in `inclure-ajax.md` |
| `0753b1b` | `\|filtre` inside `<:…:>` gives "filtre non défini" | Wrong. The filter applies to the translated string: `<:info_nb_articles\|parametre_url{nb,#ENV{val}}:>` → `@nb@ articles?nb=7`; `<:info_nb_articles{nb=3}\|strtoupper:>` → `3 ARTICLES` | rewritten in `i18n.md` |
| `f104d1f` | `#URL_ACTION_AUTEUR{a,,#SELF}` gives "Filtre ,#SELF}) non défini" | Breaks, but differently: HTTP 500, `TypeError: Cannot assign null to property Spip\Compilateur\Noeud\Champ::$code` (`ecrire/balise/url_.php:371`); `{a,,}` and `{a, ,#SELF}` too. `{a,'',#SELF}` and `{a,#ENV{vide},#SELF}` work (`arg=`) | `actions.md`, with `''` instead of the `1` placeholder |
| `273561b` | `<//B_x>` closes the zero-result zone | Already right in `main` (`SKILL.md` order), only annotations | not taken |
| `f4b6496` | config form: title outside the form, `<fieldset>` | Confirmed against the 18 `plugins-dist/*/formulaires/configurer_*.html`. The `main` example had no `<form>` nor `#ACTION_FORMULAIRE`: rendered with 0 `<form>`, unusable. The new example rendered, saved `bskc` in `spip_meta` ("La nouvelle configuration a été enregistrée") and showed the value back | `config.md` |

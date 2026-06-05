# spip-lang Skill — GREEN Verification

Date: 2026-06-05
Score: 4/4

## Results

| # | Question | Score | Source |
|---|---|---|---|
| 1 | Correct PHP structure for a SPIP language file | PASS | `references/format.md` |
| 2 | Output a translated string in a squelette | PASS | `references/usage.md` — squelette syntax section |
| 3 | `_T()` call with `@nb@` placeholder; singular/plural convention | PASS | `references/usage.md` + `references/conventions.md` |
| 4 | Mandatory keys in `paquet-monplugin_fr.php` | PASS | SKILL.md paquet-module section |

---

## Answers

### Q1 — Correct PHP structure for a SPIP language file

From `references/format.md`:

```php
<?php
// This is a SPIP language file  --  Ceci est un fichier langue de SPIP
return [
    // A
    'aucun_objet'          => 'Aucun objet trouvé',

    // B
    'bouton_ajouter_objet' => 'Ajouter un objet',

    // E
    'erreur_champ_vide'    => 'Le champ @champ@ est obligatoire',

    // I
    'info_1_objet'         => 'Un objet',
    'info_nb_objets'       => '@nb@ objets',

    // T
    'titre_liste_objets'   => 'Liste des objets',
];
```

Key rules:
- Fixed header comment (exact wording required for tooling)
- `return [...]` — no `$GLOBALS['i18n']`, no variable assignment
- Keys in `snake_case`, alphabetical sections with `// A`, `// B`… comments
- No closing `?>` tag
- Placeholders use `@nom@` syntax

---

### Q2 — Output a translated string in a squelette

From `references/usage.md` (squelette syntax section):

**Recommended — short form:**
```html
<:monplugin:titre_liste_objets:>
```

**With a placeholder:**
```html
<:monplugin:info_nb_objets{nb=#TOTAL_BOUCLE}:>
```

**Alternative — `|_T` filter** (use when the key is computed dynamically):
```html
[(#VAL{monplugin:titre_liste_objets}|_T)]
```

The `<:module:key:>` form is the idiomatic choice for static keys. It compiles to a direct `_T()` call and is immediately recognizable to translators scanning templates.

---

### Q3 — `_T()` call with `@nb@` placeholder; singular/plural convention

From `references/usage.md` and `references/conventions.md`:

**Single `_T()` call with placeholder:**
```php
echo _T('monplugin:info_nb_objets', ['nb' => $count]);
```

Placeholders use `@nom@` in the lang file value; the `$args` array replaces them by key.

**Singular/plural convention** (SPIP has no automatic pluralizer):
```php
// lang/monplugin_fr.php
'info_1_objet'   => 'Un objet',       // exactly 1
'info_nb_objets' => '@nb@ objets',    // 2 or more

// PHP usage:
$n = sql_countsel('spip_objets');
echo ($n === 1)
    ? _T('monplugin:info_1_objet')
    : _T('monplugin:info_nb_objets', ['nb' => $n]);
```

---

### Q4 — Mandatory keys in `paquet-monplugin_fr.php`

From SKILL.md (paquet-module section):

```php
<?php
// This is a SPIP language file  --  Ceci est un fichier langue de SPIP
return [
    'monplugin_description' => 'Description affichée dans le gestionnaire de plugins',
    'monplugin_slogan'      => 'Accroche courte',
];
```

Both keys are **mandatory** and must use the plugin prefix:
- `{prefix}_description` — full description shown in the SVP plugin manager
- `{prefix}_slogan` — short tagline shown next to the plugin name

These are accessed with module `paquet-monplugin`: e.g. `_T('paquet-monplugin:monplugin_description')`.

---

## Verdict

**PASS — 4/4**

The skill closes three of the four gaps identified in the baseline (Q3 was already partially correct):

1. `return [...]` format with mandatory header comment (corrects `$GLOBALS['i18n']`)
2. `<:module:key:>` as idiomatic squelette syntax; module prefix required in key
3. `@nom@` placeholder syntax confirmed; singular/plural key convention taught
4. `{prefix}_description` and `{prefix}_slogan` exact key names

# Utilisation des chaînes i18n en PHP et dans les squelettes

---

## _T() — récupérer une chaîne traduite (PHP)

```php
// Signature (ecrire/inc/utils.php)
_T(string $cle, array $args = [], array $options = []): string
```

`$cle` = `'module:clé'` — le préfixe module route vers le bon fichier lang.

### Cas courants

```php
// Lookup simple
echo _T('monplugin:titre_liste_objets');

// Avec substitution de placeholder
echo _T('monplugin:erreur_champ_vide', ['champ' => 'titre']);
echo _T('monplugin:info_nb_objets', ['nb' => $count]);

// Clé optionnelle — retourne '' si absente (pas de fallback sur la clé brute)
$label = _T('monplugin:libelle_optionnel', [], ['force' => false]);

// Sans échappement HTML des placeholders (valeur brute)
echo _T('monplugin:texte_avec_lien', ['url' => $url], ['sanitize' => false]);
```

### Options disponibles

| Clé | Défaut | Effet |
|---|---|---|
| `force` | `true` | `true` → retourne la clé brute si absente ; `false` → retourne `''` |
| `sanitize` | `true` | `true` → échappe le HTML dans les valeurs `$args` |

---

## _L() — interpoler des placeholders dans une chaîne existante

Niveau bas — à utiliser quand on a déjà la chaîne et qu'on veut juste remplacer les `@nom@` :

```php
$texte = _L('Bienvenue @prenom@ @nom@ !', ['prenom' => $p, 'nom' => $n]);
```

`_T()` appelle `_L()` en interne. Préférer `_T()` dans la majorité des cas.

---

## lang_select() — changer la langue active

Utile pour générer du contenu traduit dans une langue différente du contexte courant
(ex : email envoyé dans la langue du destinataire) :

```php
// Pousser une nouvelle langue
lang_select('en');
$sujet = _T('monplugin:email_sujet');   // récupéré en anglais
$corps  = _T('monplugin:email_corps');

// Retour à la langue précédente
lang_select(null);
```

`lang_select($lang)` empile la langue courante et passe à `$lang`.
`lang_select(null)` dépile et restaure la langue précédente.

---

## Singulier / Pluriel

```php
$n = sql_countsel('spip_monsobjets');
echo ($n === 1)
    ? _T('monplugin:info_1_monobjet')
    : _T('monplugin:info_nb_monobjets', ['nb' => $n]);
```

---

## Usage dans les squelettes SPIP

### Syntaxe courte (recommandée)

```html
<:monplugin:titre_liste_objets:>
```

Avec substitution de placeholder :
```html
<:monplugin:info_nb_objets{nb=#TOTAL_BOUCLE}:>
```

### Via le filtre `|_T`

```html
[(#VAL{monplugin:titre_liste_objets}|_T)]
```

Avec un environnement dynamique :
```html
[(#MODULE|concat{:}|concat{#CLE}|_T)]
```

### Comparaison des formes

| Forme | Quand l'utiliser |
|---|---|
| `<:module:cle:>` | Cas standard — statique, lisible |
| `<:module:cle{param=val}:>` | Substitution de placeholder depuis l'environnement |
| `[(#VAL{module:cle}\|_T)]` | Valeur dépendant d'une balise SPIP dynamique |

---

## Patterns courants (PHP)

### Validation CVT — erreur de champ

```php
// verifier()
$erreurs['titre'] = _T('monplugin:erreur_champ_vide', ['champ' => 'titre']);

// Clé core réutilisable (pas besoin de la redéfinir)
$erreurs['titre'] = _T('info_obligatoire');  // 'spip:' implicite en contexte CVT
```

### Succès CVT — message global

```php
// traiter()
return ['message_ok' => _T('ecrire:info_modification_enregistree')];
```

### Notification par email

```php
lang_select($destinataire_lang);
$sujet = _T('monplugin:email_notification_sujet');
$corps  = _T('monplugin:email_notification_corps', ['titre' => $objet['titre']]);
lang_select(null);

include_spip('inc/notifications');
envoyer_message($destinataire_email, $sujet, $corps);
```

### Clé conditionnelle (label optionnel)

```php
$label = _T('monplugin:libelle_contexte_special', [], ['force' => false])
    ?: _T('monplugin:libelle_defaut');
```

---

## Module core utiles

| Module | Fichier | Exemples de clés |
|---|---|---|
| `spip:` | `lang/spip_fr.php` | `info_obligatoire`, `bouton_enregistrer`, `confirmer_supprimer` |
| `ecrire:` | `lang/ecrire_fr.php` | `info_modification_enregistree`, `info_acces_interdit` |
| `public:` | `lang/public_fr.php` | `mots_clefs`, `info_auteur` |
| `paquet-X:` | `lang/paquet-X_fr.php` | `X_description`, `X_slogan` |

---

## Voir aussi

- `format.md` — structure et format des fichiers lang
- `conventions.md` — nommage des clés
- `../spip-plugins/references/i18n.md` — déclaration `<traduire>` et lang_select avancé

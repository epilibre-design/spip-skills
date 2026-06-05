# Conventions de nommage des clés lang SPIP

---

## Préfixes standard

SPIP organise les clés par préfixe sémantique. Respecter ces préfixes garantit la cohérence
entre plugins et permet aux traducteurs de repérer rapidement la nature de la chaîne.

| Préfixe | Rôle | Exemple |
|---|---|---|
| `titre_` | Titre de section, libellé d'objet (affiché en en-tête) | `titre_objets`, `titre_rubrique` |
| `info_` | Message informatif, compteur, état | `info_1_objet`, `info_nb_objets` |
| `erreur_` | Message d'erreur (validation, système) | `erreur_champ_vide`, `erreur_acces` |
| `bouton_` | Label de bouton ou lien d'action | `bouton_ajouter`, `bouton_supprimer` |
| `texte_` | Bloc de texte long (explication, aide) | `texte_aide_configuration` |
| `avis_` | Avertissement ou notice contextuelle | `avis_modification_enregistree` |
| `aucun_` | État vide — aucun résultat | `aucun_objet`, `aucun_objet_trouve` |
| `objet_` | Nom de l'objet éditorial (singulier/pluriel) | `objet_type_monobjet` |
| `icone_` | Infobulle d'icône | `icone_modifier_objet` |
| `item_` | Entrée de menu ou de liste de choix | `item_statut_publie` |
| `choix_` | Option d'un `<select>` | `choix_langue_defaut` |
| `login_` | Écrans d'authentification | `login_connexion_requise` |
| `paquet_` | Réservé au fichier `paquet-prefix_XX.php` | `monplugin_description` |

---

## Singulier / Pluriel

SPIP ne dispose pas de mécanisme de pluriel automatique. La convention est d'avoir deux clés :

```php
'info_1_objet'    => 'Un objet',       // exactement 1
'info_nb_objets'  => '@nb@ objets',    // 2 ou plus (@nb@ = le nombre)
```

Usage PHP :
```php
$n = sql_countsel('spip_objets');
echo ($n === 1)
    ? _T('monplugin:info_1_objet')
    : _T('monplugin:info_nb_objets', ['nb' => $n]);
```

---

## Placeholders `@nom@`

Toute valeur dynamique dans une chaîne doit utiliser la syntaxe `@nom@`.

```php
// lang/monplugin_fr.php
'erreur_champ_vide'         => 'Le champ @champ@ est obligatoire',
'info_nb_elements_max'      => 'Maximum @max@ éléments autorisés',
'texte_confirmation_suppr'  => 'Supprimer « @titre@ » ?',
```

Règles :
- Nom du placeholder : lowercase, sans espaces
- Pas de HTML dans le placeholder lui-même (l'échappement est géré par `_T()`)
- Un seul `@` de chaque côté (pas `@@nom@@`)

---

## Ordre alphabétique et commentaires de section

Les clés sont triées alphabétiquement **globalement** (pas par préfixe), avec un commentaire
de lettre à chaque changement de lettre initiale :

```php
return [
    // A
    'aucun_objet'        => 'Aucun objet',
    'avis_attention'     => 'Attention',

    // B
    'bouton_ajouter'     => 'Ajouter',
    'bouton_supprimer'   => 'Supprimer',

    // E
    'erreur_acces'       => 'Accès refusé',
    'erreur_champ_vide'  => 'Ce champ est obligatoire',

    // I
    'info_1_objet'       => 'Un objet',
    'info_nb_objets'     => '@nb@ objets',
];
```

---

## Clés partagées avec le core SPIP

Certaines clés du core peuvent être réutilisées directement (sans les redéfinir dans le plugin) :

| Clé core | Module | Usage |
|---|---|---|
| `info_obligatoire` | `spip:` | Champ obligatoire (validation CVT) |
| `info_modification_enregistree` | `ecrire:` | Succès d'enregistrement |
| `bouton_enregistrer` | `spip:` | Label bouton submit générique |
| `bouton_annuler` | `spip:` | Label bouton annuler |
| `confirmer_supprimer` | `spip:` | Confirmation suppression |
| `ecrire:info_acces_interdit` | `ecrire:` | Accès refusé |

Ne pas redéfinir une clé qui existe déjà dans le core — utiliser directement `_T('spip:cle')`.

---

## Cas particulier : clés sans préfixe module dans le core

Dans `verifier()`, `_T('info_obligatoire')` sans préfixe module est résolu vers `spip:`
par défaut. Hors contexte CVT, toujours préfixer explicitement : `_T('spip:info_obligatoire')`.

---

## Checklist avant commit

- [ ] Toutes les clés sont en `snake_case` minuscule
- [ ] Les clés sont triées alphabétiquement avec commentaires de lettre
- [ ] Les placeholders suivent le pattern `@nom@`
- [ ] Les formes singulier/pluriel (`info_1_X` / `info_nb_Xs`) sont présentes si besoin
- [ ] Le fichier `paquet-prefix_fr.php` contient `prefix_description` et `prefix_slogan`
- [ ] Aucune clé core n'est redéfinie inutilement

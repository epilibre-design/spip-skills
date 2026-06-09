<?php
// This is a SPIP language file  --  Ceci est un fichier langue de SPIP

/*spip-test
spec: >
  Fichier de langue principal d'un plugin fictif 'monplugin'.
  Les clés doivent être triées alphabétiquement globalement.
  Les placeholders dynamiques utilisent la syntaxe @nom@.
  Si la clé 'info_1_X' existe, la clé 'info_nb_Xs' doit aussi être présente.
errors:
  - id: ORDRE_NON_ALPHABETIQUE
    location: "tableau de retour — ordre des clés"
    found: "['bouton_ajouter_objet', 'aucun_objet', ...] — 'b' avant 'a'"
    expected: "['aucun_objet', 'bouton_ajouter_objet', ...] — tri alphabétique global"
    symptom: "Les traducteurs et outils de diff ne peuvent pas naviguer dans le fichier ; les sections-lettres sont incorrectes"

  - id: PLACEHOLDER_MAUVAIS_SYNTAXE
    location: "'erreur_champ_vide' — valeur du placeholder"
    found: "'Le champ %champ% est obligatoire'"
    expected: "'Le champ @champ@ est obligatoire'"
    symptom: "_T('monplugin:erreur_champ_vide', ['champ' => 'nom']) retourne la chaîne brute sans substitution"

  - id: PLURIEL_CLE_MANQUANTE
    location: "tableau de retour — clé 'info_nb_objets'"
    found: "clé absente"
    expected: "'info_nb_objets' => '@nb@ objets'"
    symptom: "_T('monplugin:info_nb_objets', ['nb' => 5]) retourne une chaîne vide ou la clé brute"
*/

return [
    // B  ← ORDRE_NON_ALPHABETIQUE : section 'B' placée avant 'A'
    'bouton_ajouter_objet' => 'Ajouter un objet',

    // A
    'aucun_objet'          => 'Aucun objet trouvé',

    // E
    'erreur_champ_vide'    => 'Le champ %champ% est obligatoire',

    // I
    'info_1_objet'         => 'Un objet',

    // T
    'titre_liste_objets'   => 'Liste des objets',
    'titre_modifier_objet' => 'Modifier l\'objet',
];

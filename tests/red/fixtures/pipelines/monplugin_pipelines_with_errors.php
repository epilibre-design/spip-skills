<?php
declare(strict_types=1);

/*spip-test
spec: >
  Handlers de pipeline pour un plugin fictif 'monplugin'.
  Text pipeline insert_head : recoit string, retourne string augmentee.
  Array pipeline post_edition : recoit $flux, retourne $flux (meme si non modifie).
  Contrat critique : toujours retourner la valeur recue — ne pas casser la chaine.
errors:
  - id: INSERT_HEAD_RETOUR_ARRAY
    location: "monplugin_insert_head_with_errors() — valeur de retour"
    found: "return [$texte, '<link ...>'] — array"
    expected: "return $texte . '<link ...>' — string concatenee"
    symptom: "Le pipeline insert_head recoit un array a la place d'une string — TypeError au prochain handler"

  - id: POST_EDITION_FLUX_NULL
    location: "monplugin_post_edition_sans_retour() — instruction return manquante"
    found: "pas de return — PHP retourne null implicitement"
    expected: "return $flux"
    symptom: "La chaine de pipeline est coupee : tous les handlers enregistres apres celui-ci ne recoivent rien"

  - id: POST_EDITION_DATA_ECRASE
    location: "monplugin_post_edition_ecrase_data() — affectation de $flux['data']"
    found: "$flux['data'] = $nouveaux — remplace le tableau complet"
    expected: "$flux['data'] = array_merge($flux['data'], $nouveaux)"
    symptom: "Les champs deja presents dans $flux['data'] (ex. 'titre') sont perdus apres ce handler"
*/

function monplugin_insert_head_with_errors($texte)
{
    // INSERT_HEAD_RETOUR_ARRAY : retourne un array au lieu de concatener a $texte
    return [$texte, '<link rel="stylesheet" href="monplugin.css">'];
}

function monplugin_post_edition_sans_retour($flux)
{
    // POST_EDITION_FLUX_NULL : traitement sans return — retourne null implicitement
    $flux['data']['traite_par_monplugin'] = true;
}

function monplugin_post_edition_ecrase_data($flux)
{
    $nouveaux = ['statut_calcule' => 'publie'];
    // POST_EDITION_DATA_ECRASE : ecrase $flux['data'] au lieu de fusionner
    $flux['data'] = $nouveaux;
    return $flux;
}

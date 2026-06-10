<?php
declare(strict_types=1);

/*spip-test
spec: >
  Filtre de troncature de texte : filtre_tronquer_with_errors($texte, $longueur = 100).
  Contrat : accepte string|null, retourne toujours une string.
  Tronque a $longueur caracteres (parametre respecte).
  Echappe les caracteres HTML speciaux avant de retourner.
errors:
  - id: RETOUR_NULL_SUR_VIDE
    location: "filtre_tronquer_with_errors() — valeur de retour quand $texte est vide"
    found: "return null"
    expected: "return ''"
    symptom: "[(#TEXTE|filtre_tronquer_with_errors)] provoque une TypeError ou affiche vide selon le contexte"

  - id: LONGUEUR_IGNOREE
    location: "filtre_tronquer_with_errors() — appel a mb_substr"
    found: "mb_substr($texte, 0, 100) — longueur codee en dur"
    expected: "mb_substr($texte, 0, $longueur)"
    symptom: "[(#TEXTE|filtre_tronquer_with_errors{20})] retourne toujours 100 caracteres au lieu de 20"

  - id: HTML_NON_ECHAPPE
    location: "filtre_tronquer_with_errors() — valeur de retour"
    found: "return mb_substr($texte, 0, 100) sans echappement"
    expected: "return htmlspecialchars(mb_substr($texte, 0, $longueur), ENT_QUOTES, 'UTF-8')"
    symptom: "[(#TEXTE|filtre_tronquer_with_errors)] peut injecter du HTML dans le rendu — risque XSS"
*/

function filtre_tronquer_with_errors($texte, $longueur = 100)
{
    if ($texte === '' || $texte === null) {
        // RETOUR_NULL_SUR_VIDE : doit retourner '' mais retourne null
        return null;
    }

    // LONGUEUR_IGNOREE : $longueur ignore, toujours 100
    // HTML_NON_ECHAPPE : pas de htmlspecialchars()
    return mb_substr($texte, 0, 100);
}

<?php
declare(strict_types=1);

/*spip-test
spec: >
  Autorisations d'un objet fictif 'monobjet'.
  voir() : refusé aux anonymes (statut vide), accordé aux visiteurs, rédacteurs et admins.
  modifier() : refusé aux bannis (5poubelle) et anonymes, accordé aux rédacteurs et admins.
  creer() : accordé aux rédacteurs (1comite) et admins (0minirezo).
  supprimer() : réservé aux admins, retourne un bool strict (contrat SPIP 4.4+).
errors:
  - id: VOIR_ACCEPTE_ANONYME
    location: "autoriser_monobjet_voir_dist() — tableau in_array"
    found: "in_array(\$qui['statut'], ['0minirezo', '1comite', '6forum', ''])"
    expected: "in_array(\$qui['statut'], ['0minirezo', '1comite', '6forum'])"
    symptom: "Un visiteur anonyme (statut vide) peut voir les monobjet — accès non authentifié non bloqué"

  - id: MODIFIER_ACCEPTE_BANNI
    location: "autoriser_monobjet_modifier_dist() — tableau in_array"
    found: "in_array(\$qui['statut'], ['0minirezo', '1comite', '5poubelle'])"
    expected: "in_array(\$qui['statut'], ['0minirezo', '1comite'])"
    symptom: "Un auteur banni (5poubelle) peut modifier des monobjet — statut poubelle non exclu"

  - id: CREER_EXCLUT_REDACTEUR
    location: "autoriser_monobjet_creer_dist() — comparaison de statut"
    found: "\$qui['statut'] === '0minirezo'"
    expected: "in_array(\$qui['statut'], ['0minirezo', '1comite'])"
    symptom: "Un rédacteur (1comite) ne peut pas créer de monobjet — statut comité oublié dans la condition"

  - id: SUPPRIMER_RETOUR_NON_BOOL
    location: "autoriser_monobjet_supprimer_dist() — type de retour"
    found: "return (int)(\$qui['statut'] === '0minirezo') — retourne 0 ou 1 (int)"
    expected: "return \$qui['statut'] === '0minirezo' — retourne false ou true (bool)"
    symptom: "Violation du contrat autoriser_*_dist() : retour non-bool produit une déprecation SPIP 4.4+ et sera fatal dans les versions futures"
*/

function autoriser_monobjet_voir_dist($faire, $type, $id, $qui, $opt): bool
{
    // VOIR_ACCEPTE_ANONYME : statut vide '' inclus dans la liste, anonymes acceptés
    return in_array($qui['statut'], ['0minirezo', '1comite', '6forum', '']);
}

function autoriser_monobjet_modifier_dist($faire, $type, $id, $qui, $opt): bool
{
    // MODIFIER_ACCEPTE_BANNI : '5poubelle' non exclu de la liste
    return in_array($qui['statut'], ['0minirezo', '1comite', '5poubelle']);
}

function autoriser_monobjet_creer_dist($faire, $type, $id, $qui, $opt): bool
{
    // CREER_EXCLUT_REDACTEUR : '1comite' oublié — seuls les admins peuvent créer
    return $qui['statut'] === '0minirezo';
}

function autoriser_monobjet_supprimer_dist($faire, $type, $id, $qui, $opt)
{
    // SUPPRIMER_RETOUR_NON_BOOL : retourne int (0 ou 1) au lieu de bool
    return (int) ($qui['statut'] === '0minirezo');
}

<?php
declare(strict_types=1);

/*spip-test
spec: >
  Formulaire CVT d'inscription : champs 'nom' (obligatoire) et 'email' (obligatoire, format valide).
  charger() initialise les deux champs dans l'ENV.
  verifier() exige 'nom' non vide et retourne l'erreur sous la clé 'email' si l'email est invalide.
  traiter() insère un auteur dans spip_auteurs et retourne ['message_ok' => '…'].
errors:
  - id: CHARGER_EMAIL_ABSENT
    location: "charger() — tableau de retour"
    found: "['nom' => '']"
    expected: "['nom' => '', 'email' => '']"
    symptom: "Le champ email n'est pas initialisé dans l'ENV — valeur absente au premier affichage"

  - id: VERIFIER_NOM_IGNORE
    location: "verifier() — validation du champ nom"
    expected: "$erreurs['nom'] = '...' quand _request('nom') est vide"
    symptom: "Soumission acceptée sans nom — champ obligatoire ignoré"

  - id: VERIFIER_CLE_EMAIL
    location: "verifier() — clé d'erreur pour email invalide"
    found: "$erreurs['mail'] = 'Email invalide.'"
    expected: "$erreurs['email'] = 'Email invalide.'"
    symptom: "L'erreur de format email ne s'affiche pas dans le champ (mauvaise clé dans le tableau d'erreurs)"

  - id: TRAITER_PAS_UTILISATEUR
    location: "traiter() — insertion dans spip_auteurs"
    expected: "sql_insertq('spip_auteurs', [...])"
    symptom: "Aucun utilisateur créé malgré une soumission valide"

  - id: TRAITER_SANS_MESSAGE_OK
    location: "traiter() — valeur de retour"
    found: "[]"
    expected: "['message_ok' => '…']"
    symptom: "Aucun message de confirmation affiché après inscription réussie"
*/

function formulaires_inscription_with_error_charger_dist(): array
{
    // CHARGER_EMAIL_ABSENT : 'email' manquant dans le tableau retourné
    return ['nom' => ''];
}

function formulaires_inscription_with_error_verifier_dist(): array
{
    $erreurs = [];

    // VERIFIER_NOM_IGNORE : le champ 'nom' obligatoire n'est pas vérifié

    $email = _request('email');
    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // VERIFIER_CLE_EMAIL : clé 'mail' au lieu de 'email'
        $erreurs['mail'] = 'Email invalide.';
    }

    return $erreurs;
}

function formulaires_inscription_with_error_traiter_dist(): array
{
    // TRAITER_PAS_UTILISATEUR + TRAITER_SANS_MESSAGE_OK : aucune insertion, pas de message_ok
    return [];
}

<?php
// This is a SPIP language file  --  Ceci est un fichier langue de SPIP

/*spip-test
spec: >
  Fichier paquet-monplugin_fr.php : métadonnées du plugin pour le gestionnaire.
  Deux clés obligatoires : 'monplugin_description' et 'monplugin_slogan'.
errors:
  - id: PAQUET_SLOGAN_MANQUANT
    location: "tableau de retour — clé 'monplugin_slogan'"
    found: "clé absente"
    expected: "'monplugin_slogan' => 'Accroche courte du plugin'"
    symptom: "Le gestionnaire de plugins affiche un slogan vide pour ce plugin"
*/

return [
    'monplugin_description' => 'Plugin de démonstration pour les tests TDD RED spip-lang.',
    // PAQUET_SLOGAN_MANQUANT : 'monplugin_slogan' manquant
];

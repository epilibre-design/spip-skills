<?php
declare(strict_types=1);

$spipRoot = dirname(__FILE__) . '/vendor/spip/spip';

if (!defined('_SPIP_TEST_INC'))   { define('_SPIP_TEST_INC',   $spipRoot); }
if (!defined('_SPIP_TEST_CHDIR')) { define('_SPIP_TEST_CHDIR', $spipRoot); }

putenv('APP_ENV=test');
chdir($spipRoot);

if (is_file($spipRoot . '/vendor/autoload.php')) {
    require_once $spipRoot . '/vendor/autoload.php';
}
require_once $spipRoot . '/ecrire/inc_version.php';

include_spip('inc/plugin');
actualise_plugins_actifs();

/**
 * Override du debusqueur SPIP pour les tests d'intégration.
 * Remplace public_debusquer_dist() via charger_fonction('debusquer', 'public').
 * Capture silencieusement les erreurs squelette (erreur_squelette, zbug_*)
 * sans logging ni effets de bord globaux.
 *
 * Usage dans les tests :
 *   public_debusquer('', '', ['erreurs' => 'reset']); // avant render()
 *   $erreurs = public_debusquer('', '', ['erreurs' => 'get']); // après render()
 *   // Chaque entrée : [array|string $message, object|string $lieu]
 *   // $message = ['zbug_champ_hors_boucle', ['champ' => 'PAGINATION']] pour PAGINATION hors boucle
 */
function public_debusquer($message = '', $lieu = '', $opt = []) {
    static $erreurs = [];
    if (isset($opt['erreurs'])) {
        if ($opt['erreurs'] === 'get')   { return $erreurs; }
        if ($opt['erreurs'] === 'reset') { $erreurs = []; return true; }
    }
    if ($message) {
        $erreurs[] = [$message, $lieu];
        // Permettre à la compilation de continuer (même comportement que public_debusquer_dist)
        if (is_object($lieu) && property_exists($lieu, 'code') && !$lieu->code) {
            $lieu->code = "''";
        }
    }
}

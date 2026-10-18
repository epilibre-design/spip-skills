<?php
declare(strict_types=1);

$spipRoot = dirname(__DIR__) . '/vendor/spip/spip';

if (!defined('_SPIP_TEST_INC'))   { define('_SPIP_TEST_INC',   $spipRoot); }
if (!defined('_SPIP_TEST_CHDIR')) { define('_SPIP_TEST_CHDIR', $spipRoot); }

putenv('APP_ENV=test');
chdir($spipRoot);

if (is_file($spipRoot . '/vendor/autoload.php')) {
    require_once $spipRoot . '/vendor/autoload.php';
}
require_once $spipRoot . '/ecrire/inc_version.php';

include_spip('inc/plugin');
_chemin(dirname(__DIR__));   // add plugin root to SPIP path
actualise_plugins_actifs();  // activate the plugin chain

// DO NOT require tests/bootstrap.php here: its mocks (sql_countsel, autoriser…)
// conflict with the real SPIP functions already loaded above.

<?php
declare(strict_types=1);

/**
 * Exemple unitaire inspiré de la référence: un filtre simple et une variante pilotée par configuration.
 */
function green_unit_mon_filtre(string $input): string
{
    return strtolower(trim($input));
}

function green_unit_mon_filtre_avec_config(string $input): string
{
    $mode = (string) green_unit_lire_config('monplugin/mode');

    if ($mode === 'strict') {
        return 'strict:' . green_unit_mon_filtre($input);
    }

    return green_unit_mon_filtre($input);
}
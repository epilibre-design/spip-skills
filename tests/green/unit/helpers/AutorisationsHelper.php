<?php
declare(strict_types=1);

/**
 * Exemple unitaire inspiré de la référence: déléguer à l'API d'autorisation.
 */
function green_unit_autoriser_monobjet_peut(string $faire, string $type, int $id, array $qui, array $opt = []): bool
{
    return green_unit_autoriser($faire, $type, $id, $qui, $opt);
}
<?php
declare(strict_types=1);

/**
 * Rend un HTML déjà préparé par un double de test, puis supprime les commentaires.
 */
function green_unit_render_include_context(callable $renderer, int $rubriqueId): string
{
    $raw = $renderer(['id_rubrique' => $rubriqueId]);

    return (string) preg_replace('/<!--.*?-->/s', '', (string) $raw);
}
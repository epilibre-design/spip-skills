<?php
declare(strict_types=1);

/**
 * Retourne les IDs des articles publiés de la rubrique cible,
 * triés par date décroissante et limités à $limit.
 */
function green_unit_boucle_articles_order(array $articles, int $rubriqueId, int $limit = 5): string
{
    if ($limit <= 0) {
        return '';
    }

    $filtered = array_values(array_filter(
        $articles,
        static function (array $article) use ($rubriqueId): bool {
            return (int) ($article['id_rubrique'] ?? 0) === $rubriqueId
                && (string) ($article['statut'] ?? '') === 'publie';
        }
    ));

    usort(
        $filtered,
        static function (array $left, array $right): int {
            return strcmp(
                (string) ($right['date'] ?? ''),
                (string) ($left['date'] ?? '')
            );
        }
    );

    $selected = array_slice($filtered, 0, $limit);

    if ($selected === []) {
        return '';
    }

    $ids = array_map(
        static function (array $article): string {
            return (string) ($article['id_article'] ?? 0);
        },
        $selected
    );

    return implode(',', $ids) . ',';
}
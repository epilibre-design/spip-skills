<?php
declare(strict_types=1);

/**
 * Prépare les articles publiés, les trie par date décroissante et délègue le rendu à un paginateur mocké.
 */
function green_unit_render_pagination(array $articles, callable $paginate, int $limit = 5): string
{
    $published = array_values(array_filter(
        $articles,
        static function (array $article): bool {
            return (string) ($article['statut'] ?? '') === 'publie';
        }
    ));

    usort(
        $published,
        static function (array $left, array $right): int {
            return strcmp(
                (string) ($right['date'] ?? ''),
                (string) ($left['date'] ?? '')
            );
        }
    );

    $page = array_slice($published, 0, $limit);

    return (string) $paginate($page, [
        'total' => count($published),
        'limit' => $limit,
    ]);
}
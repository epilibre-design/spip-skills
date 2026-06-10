<?php
declare(strict_types=1);

final class PaginationRendererMock
{
    /** @var array<int, array{page: array<int, array<string, mixed>>, meta: array{total: int, limit: int}}>
     */
    public array $calls = [];

    public function __invoke(array $page, array $meta): string
    {
        $this->calls[] = ['page' => $page, 'meta' => $meta];

        $items = array_map(
            static function (array $article): string {
                return '<li><a href="#">' . $article['titre'] . '</a></li>';
            },
            $page
        );

        $html = '<ul>' . implode('', $items) . '</ul>';

        if ($meta['total'] > $meta['limit']) {
            $html .= '<nav><a href="?debut_arts=' . $meta['limit'] . '">Suivant</a></nav>';
        }

        return $html;
    }
}
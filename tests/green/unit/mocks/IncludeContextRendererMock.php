<?php
declare(strict_types=1);

final class IncludeContextRendererMock
{
    /** @var array<int, array<string, mixed>> */
    public array $contexts = [];

    public function __invoke(array $context): string
    {
        $this->contexts[] = $context;

        if (($context['id_rubrique'] ?? null) !== 40) {
            return '<!-- contexte -->' .
                '<p>Hors Contexte</p>' .
                '<p>Aucun article publie dans cette rubrique.</p>';
        }

        return '<!-- contexte -->' .
            '<p>Cible A</p>' .
            '<p>Cible B</p>';
    }
}
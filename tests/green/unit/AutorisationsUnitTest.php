<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/mocks/SpipUnitStubs.php';
require_once __DIR__ . '/helpers/AutorisationsHelper.php';

final class AutorisationsUnitTest extends TestCase
{
    private static function redacteur(): array
    {
        return ['statut' => '1comite', 'id_auteur' => 5];
    }

    private static function visiteur(): array
    {
        return ['statut' => '6forum', 'id_auteur' => 0];
    }

    public function testRedacteurPeutVoir(): void
    {
        $this->assertTrue(
            green_unit_autoriser_monobjet_peut('voir', 'monobjet', 1, self::redacteur(), [])
        );
    }

    public function testVisiteurNePeutPasModifier(): void
    {
        $GLOBALS['_test_autoriser'] = false;

        $this->assertFalse(
            green_unit_autoriser_monobjet_peut('modifier', 'monobjet', 1, self::visiteur(), [])
        );
    }
}
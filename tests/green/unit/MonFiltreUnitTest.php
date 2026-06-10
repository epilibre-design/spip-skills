<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/helpers/MonFiltreHelper.php';

final class MonFiltreUnitTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['_test_config'] = [];
    }

    #[DataProvider('providerMonFiltre')]
    public function testMonFiltre(string $expected, string $input): void
    {
        $this->assertSame($expected, green_unit_mon_filtre($input));
    }

    public static function providerMonFiltre(): array
    {
        return [
            'nominal' => ['hello', 'HELLO'],
            'empty string' => ['', ''],
        ];
    }

    public function testMonFiltreAvecConfig(): void
    {
        $GLOBALS['_test_config']['monplugin/mode'] = 'strict';

        $this->assertSame('strict:hello', green_unit_mon_filtre_avec_config('HELLO'));
    }
}
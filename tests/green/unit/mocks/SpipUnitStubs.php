<?php
declare(strict_types=1);

if (!defined('_ECRIRE_INC_VERSION')) {
    define('_ECRIRE_INC_VERSION', 'test');
}

if (!function_exists('include_spip')) {
    function include_spip(string $path): void
    {
    }
}

if (!function_exists('_request')) {
    function _request(string $name): mixed
    {
        return $GLOBALS['_test_request'][$name] ?? null;
    }
}

if (!function_exists('green_unit_request')) {
    function green_unit_request(string $name): mixed
    {
        return $GLOBALS['_test_request'][$name] ?? null;
    }
}

if (!function_exists('_T')) {
    function _T(string $key): string
    {
        return $key;
    }
}

if (!function_exists('sql_quote')) {
    function sql_quote(string $value): string
    {
        return "'" . addslashes($value) . "'";
    }
}

if (!function_exists('sql_countsel')) {
    function sql_countsel(string $table, string $where = ''): int
    {
        return (int) ($GLOBALS['_test_sql_countsel'][$table] ?? 0);
    }
}

if (!function_exists('lire_config')) {
    function lire_config(string $key): mixed
    {
        return $GLOBALS['_test_config'][$key] ?? null;
    }
}

if (!function_exists('green_unit_lire_config')) {
    function green_unit_lire_config(string $key): mixed
    {
        return $GLOBALS['_test_config'][$key] ?? null;
    }
}

if (!function_exists('autoriser')) {
    function autoriser(string $faire, string $type = '', int $id = 0, array $qui = [], array $opt = []): bool
    {
        return (bool) ($GLOBALS['_test_autoriser'] ?? true);
    }
}

if (!function_exists('green_unit_autoriser')) {
    function green_unit_autoriser(string $faire, string $type = '', int $id = 0, array $qui = [], array $opt = []): bool
    {
        return (bool) ($GLOBALS['_test_autoriser'] ?? true);
    }
}
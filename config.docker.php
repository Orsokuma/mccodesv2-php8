<?php
declare(strict_types=1);

// Consider Dotenv/Dotenv
$env_file      = [];
$env_file_path = __DIR__ . '/.env';
if (file_exists($env_file_path)) {
    $env_file = parse_ini_file($env_file_path);
}

/**
 * @param string $name
 * @return string
 */
function read_secret(string $name): string
{
    if ($value = getenv($name . '_FILE')) {
        return trim(file_get_contents($value));
    }
    if ($value = getenv($name)) {
        return trim($value);
    }
    return '';
}

$_CONFIG = [
    'hostname' => read_secret('DB_HOST'),
    'username' => read_secret('DB_USER'),
    'password' => read_secret('DB_PASS'),
    'database' => read_secret('DB_NAME'),
    'code' => read_secret('APP_KEY'),
];
const FORCE_SSL = true;

<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

// loads .env/.env.local defaults; phpunit.xml <env> values are real env vars and win
(new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');

// the test database lives on the same server but needs the root user (the app MySQL user
// has grants on contact_form.* only). The root password is never committed: it comes from
// the DB_ROOT_PASSWORD env var (docker compose passes it through to the app container).
if (!getenv('DATABASE_URL')) {
    $rootPassword = getenv('DB_ROOT_PASSWORD');
    if (false === $rootPassword || '' === $rootPassword) {
        throw new RuntimeException(
            'Set DB_ROOT_PASSWORD (or a full DATABASE_URL) to run the tests — see README, section "Тесты".'
        );
    }

    $databaseUrl = sprintf(
        'mysql://root:%s@db:3306/contact_form?serverVersion=8.0&charset=utf8mb4',
        $rootPassword
    );
    putenv('DATABASE_URL=' . $databaseUrl);
    $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = $databaseUrl;
}

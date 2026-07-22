<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

// loads .env/.env.local defaults; phpunit.xml <env> values are real env vars and win
(new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');

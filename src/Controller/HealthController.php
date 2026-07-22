<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class HealthController
{
    public function __construct(private readonly Connection $connection)
    {
    }

    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    public function check(): JsonResponse
    {
        try {
            $this->connection->executeQuery('SELECT 1');

            return new JsonResponse([
                'status' => 'ok',
                'db' => 'up',
            ]);
        } catch (Throwable) {
            return new JsonResponse([
                'status' => 'error',
                'db' => 'down',
            ], JsonResponse::HTTP_SERVICE_UNAVAILABLE);
        }
    }
}

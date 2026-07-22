<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class MetricsController
{
    #[Route('/api/metrics', name: 'api_metrics', methods: ['GET'])]
    public function index(): JsonResponse
    {
        // TODO: replace with real metrics from the database
        return new JsonResponse([
            'status' => 'ok',
            'metrics' => [
                'total_contacts' => 0,
            ],
        ]);
    }
}

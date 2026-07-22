<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ContactRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class MetricsController
{
    public function __construct(
        private readonly ContactRepository $contactRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/api/metrics', name: 'api_metrics', methods: ['GET'])]
    public function index(): JsonResponse
    {
        try {
            $metrics = [
                'total' => $this->contactRepository->countAll(),
                'today' => $this->contactRepository->countToday(),
                'last_7_days' => $this->contactRepository->countByDay(7),
            ];
        } catch (Throwable $e) {
            $this->logger->error('Failed to collect metrics', ['exception' => $e]);

            throw new ServiceUnavailableHttpException(message: 'Метрики временно недоступны');
        }

        return new JsonResponse([
            'status' => 'ok',
            'metrics' => $metrics,
        ]);
    }
}

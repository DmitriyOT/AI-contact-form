<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ContactRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class MetricsController
{
    public function __construct(
        private readonly ContactRepository $contactRepository,
        private readonly RateLimiterFactory $metricsApiLimiter,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(METRICS_TOKEN)%')]
        private readonly string $metricsToken,
    ) {
    }

    #[Route('/api/metrics', name: 'api_metrics', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        // throttle Bearer-token brute force: the limit is consumed before any auth check
        $limit = $this->metricsApiLimiter->create((string) $request->getClientIp())->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException(
                max(1, $limit->getRetryAfter()->getTimestamp() - time()),
                'Слишком много запросов, попробуйте позже'
            );
        }

        // an empty token means the endpoint is disabled entirely (safe default)
        if ('' === $this->metricsToken) {
            throw new AccessDeniedHttpException('Метрики отключены: не задан METRICS_TOKEN');
        }

        $authorization = (string) $request->headers->get('Authorization', '');
        $token = str_starts_with($authorization, 'Bearer ') ? substr($authorization, 7) : '';
        if ('' === $token || !hash_equals($this->metricsToken, $token)) {
            throw new UnauthorizedHttpException('Bearer', 'Требуется авторизация: Authorization: Bearer <token>');
        }

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

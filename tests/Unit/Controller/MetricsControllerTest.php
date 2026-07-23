<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\MetricsController;
use App\Repository\ContactRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

final class MetricsControllerTest extends TestCase
{
    private const TOKEN = 'secret-token';

    public function testDisabledWhenTokenIsEmpty(): void
    {
        $controller = $this->createController('');

        $this->expectException(AccessDeniedHttpException::class);
        $controller->index($this->requestWithToken(self::TOKEN));
    }

    public function testUnauthorizedWithoutHeader(): void
    {
        $controller = $this->createController(self::TOKEN);

        $this->expectException(UnauthorizedHttpException::class);
        $controller->index(new Request());
    }

    public function testUnauthorizedWithWrongToken(): void
    {
        $controller = $this->createController(self::TOKEN);

        $this->expectException(UnauthorizedHttpException::class);
        $controller->index($this->requestWithToken('wrong'));
    }

    public function testOkWithCorrectToken(): void
    {
        $controller = $this->createController(self::TOKEN);
        $response = $controller->index($this->requestWithToken(self::TOKEN));

        self::assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertSame('ok', $data['status']);
        self::assertSame(7, $data['metrics']['total']);
        self::assertSame(3, $data['metrics']['today']);
        self::assertSame(['2026-07-22' => 7], $data['metrics']['last_7_days']);
    }

    public function testUnauthorizedChallengeHeader(): void
    {
        $controller = $this->createController(self::TOKEN);

        try {
            $controller->index(new Request());
            self::fail('Ожидался UnauthorizedHttpException');
        } catch (UnauthorizedHttpException $e) {
            self::assertSame('Bearer', $e->getHeaders()['WWW-Authenticate'] ?? null);
        }
    }

    public function testTooManyRequestsWhenLimiterIsExhausted(): void
    {
        $controller = $this->createController(self::TOKEN, limit: 1);
        $controller->index($this->requestWithToken(self::TOKEN));

        $this->expectException(TooManyRequestsHttpException::class);
        $controller->index($this->requestWithToken(self::TOKEN));
    }

    private function createController(string $token, int $limit = 100): MetricsController
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturnOnConsecutiveCalls('7', '3');
        $connection->method('fetchAllKeyValue')->willReturn(['2026-07-22' => '7']);

        $limiterFactory = new RateLimiterFactory([
            'id' => 'metrics_api',
            'policy' => 'fixed_window',
            'limit' => $limit,
            'interval' => '60 seconds',
        ], new InMemoryStorage());

        return new MetricsController(
            new ContactRepository($connection),
            $limiterFactory,
            new NullLogger(),
            $token,
        );
    }

    private function requestWithToken(string $token): Request
    {
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer ' . $token);

        return $request;
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HealthMetricsApiTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM contacts');
        // the file-backed limiter pool persists across tests — start from a clean window
        static::getContainer()->get('cache.rate_limiter')->clear();
    }

    public function testHealthReportsDatabaseUp(): void
    {
        $this->client->request('GET', '/api/health');

        self::assertResponseStatusCodeSame(200);
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('ok', $data['status']);
        self::assertSame('up', $data['db']);
    }

    public function testMetricsRequiresAuthorization(): void
    {
        $this->client->request('GET', '/api/metrics');

        self::assertResponseStatusCodeSame(401);
        self::assertResponseHasHeader('WWW-Authenticate', 'Bearer');
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('unauthorized', $data['error']['code']);
    }

    public function testMetricsRejectsWrongToken(): void
    {
        $this->client->request('GET', '/api/metrics', [], [], ['HTTP_AUTHORIZATION' => 'Bearer wrong']);

        self::assertResponseStatusCodeSame(401);
    }

    public function testMetricsWithTokenReflectsSubmittedContacts(): void
    {
        $this->client->request('POST', '/api/contact', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'name' => 'Иван Иванов',
            'phone' => '+7 900 123-45-67',
            'email' => 'ivan@example.com',
            'comment' => 'Хочу узнать подробнее о ваших услугах.',
        ], JSON_UNESCAPED_UNICODE));
        self::assertResponseStatusCodeSame(201);

        $this->client->request('GET', '/api/metrics', [], [], ['HTTP_AUTHORIZATION' => 'Bearer test-metrics-token']);

        self::assertResponseStatusCodeSame(200);
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('ok', $data['status']);
        self::assertSame(1, $data['metrics']['total']);
        self::assertSame(1, $data['metrics']['today']);
        self::assertSame(1, $data['metrics']['last_7_days'][date('Y-m-d')]);
    }
}

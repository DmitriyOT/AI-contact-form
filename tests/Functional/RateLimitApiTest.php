<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RateLimitApiTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // the file-backed limiter pool persists across test runs — start from a clean window
        static::getContainer()->get('cache.rate_limiter')->clear();
    }

    public function testContactFormRateLimitReturns429AfterLimitIsExhausted(): void
    {
        // RATE_LIMIT_MAX is 100 in the test env; the limit is consumed before validation,
        // so an empty payload burns the quota without touching the DB or the mailer
        for ($i = 0; $i < 100; ++$i) {
            $this->postEmptyPayload();
        }
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->postEmptyPayload();

        self::assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
        $data = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('too_many_requests', $data['error']['code']);
        self::assertNotNull($this->client->getResponse()->headers->get('Retry-After'));
    }

    private function postEmptyPayload(): void
    {
        $this->client->request('POST', '/api/contact', [], [], ['CONTENT_TYPE' => 'application/json'], '{}');
    }
}

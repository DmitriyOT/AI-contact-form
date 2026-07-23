<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ContactApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private Connection $connection;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->connection = static::getContainer()->get(Connection::class);
        $this->connection->executeStatement('DELETE FROM contacts');
    }

    public function testValidContactReturns201(): void
    {
        $this->postContact([
            'name' => 'Иван Иванов',
            'phone' => '+7 900 123-45-67',
            'email' => 'ivan@example.com',
            'comment' => 'Хочу узнать подробнее о ваших услугах.',
        ]);

        self::assertResponseStatusCodeSame(201);
        $data = $this->responseJson();
        self::assertSame('accepted', $data['status']);
        self::assertSame('Обращение принято', $data['message']);
        // AI_API_KEY is empty in the test env: graceful fallback
        self::assertFalse($data['ai']);

        $row = $this->connection->fetchAssociative('SELECT * FROM contacts');
        self::assertNotFalse($row);
        self::assertSame('Иван Иванов', $row['name']);
        self::assertNull($row['ai_sentiment']);
        self::assertNull($row['ai_category']);
        self::assertNull($row['ai_summary']);
        self::assertNull($row['ai_priority']);
        self::assertNull($row['ai_draft_reply']);
        self::assertSame('127.0.0.1', $row['ip_address']);
    }

    public function testUnknownFieldsAreIgnored(): void
    {
        $this->postContact([
            'name' => 'Иван Иванов',
            'phone' => '+7 900 123-45-67',
            'email' => 'ivan@example.com',
            'comment' => 'Хочу узнать подробнее о ваших услугах.',
            'is_admin' => true,
        ]);

        self::assertResponseStatusCodeSame(201);
    }

    public function testEmptyPayloadReturns422WithDetailsPerField(): void
    {
        $this->postContact([]);

        self::assertResponseStatusCodeSame(422);
        $data = $this->responseJson();
        self::assertSame('validation_failed', $data['error']['code']);
        foreach (['name', 'phone', 'email', 'comment'] as $field) {
            self::assertArrayHasKey($field, $data['error']['details']);
        }
    }

    public function testInvalidPhoneAndEmailReturn422(): void
    {
        $this->postContact([
            'name' => 'Иван',
            'phone' => '9999999999',
            'email' => 'not-an-email',
            'comment' => 'Комментарий достаточной длины.',
        ]);

        self::assertResponseStatusCodeSame(422);
        $details = $this->responseJson()['error']['details'];
        self::assertContains('Введите телефон в формате +7 900 123-45-67', $details['phone']);
        self::assertArrayHasKey('email', $details);
        self::assertArrayNotHasKey('name', $details);
        self::assertArrayNotHasKey('comment', $details);
    }

    public function testNonStringFieldsReturn422(): void
    {
        $this->postContact(['name' => 123, 'phone' => '+79001234567', 'email' => 'ivan@example.com', 'comment' => 'Комментарий достаточной длины.']);

        self::assertResponseStatusCodeSame(422);
        self::assertArrayHasKey('name', $this->responseJson()['error']['details']);
    }

    public function testBrokenJsonReturns400(): void
    {
        $this->client->request('POST', '/api/contact', [], [], ['CONTENT_TYPE' => 'application/json'], '{broken');

        self::assertResponseStatusCodeSame(400);
        self::assertSame('bad_request', $this->responseJson()['error']['code']);
    }

    public function testWrongContentTypeReturns415(): void
    {
        $this->client->request('POST', '/api/contact', [], [], ['CONTENT_TYPE' => 'text/plain'], 'name=Ivan');

        self::assertResponseStatusCodeSame(415);
        self::assertSame('unsupported_media_type', $this->responseJson()['error']['code']);
    }

    public function testGetOnContactReturns405(): void
    {
        $this->client->request('GET', '/api/contact');

        self::assertResponseStatusCodeSame(405);
    }

    public function testErrorResponsesUseUnifiedJsonFormat(): void
    {
        $this->client->request('GET', '/api/nonexistent');

        self::assertResponseStatusCodeSame(404);
        $data = $this->responseJson();
        self::assertSame('not_found', $data['error']['code']);
        self::assertArrayHasKey('message', $data['error']);
    }

    private function postContact(array $payload): void
    {
        $this->client->request(
            'POST',
            '/api/contact',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload, JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function responseJson(): array
    {
        $content = (string) $this->client->getResponse()->getContent();
        self::assertJson($content);

        return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
    }
}

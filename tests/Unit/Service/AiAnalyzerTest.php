<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\AiAnalyzer;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class AiAnalyzerTest extends TestCase
{
    private const BASE_URL = 'https://ai.example/v1';
    private const MODEL = 'test-model';

    public function testReturnsNullWithoutHttpCallWhenApiKeyIsEmpty(): void
    {
        $client = new MockHttpClient(static function (): never {
            self::fail('HTTP-запрос не должен выполняться при пустом AI_API_KEY');
        });

        $analyzer = new AiAnalyzer($client, new NullLogger(), self::BASE_URL, '', self::MODEL);

        self::assertNull($analyzer->analyze('любой комментарий'));
        self::assertNull($analyzer->analyze('повторный вызов тоже без запроса'));
    }

    public function testParsesValidResponse(): void
    {
        $analyzer = $this->analyzerWithContent('{"sentiment":"positive","category":"вопрос","summary":"Клиент спрашивает об услугах","priority":"низкий","draft_reply":"Здравствуйте! Ответим в ближайшее время."}');

        self::assertSame(
            [
                'sentiment' => 'positive',
                'category' => 'вопрос',
                'summary' => 'Клиент спрашивает об услугах',
                'priority' => 'низкий',
                'draft_reply' => 'Здравствуйте! Ответим в ближайшее время.',
            ],
            $analyzer->analyze('Расскажите о ваших услугах, пожалуйста')
        );
    }

    public function testMissingNewFieldsFallBackToDefaults(): void
    {
        // ответ старого формата (без priority/draft_reply) — обратная совместимость
        $analyzer = $this->analyzerWithContent('{"sentiment":"neutral","category":"вопрос","summary":"s"}');

        $result = $analyzer->analyze('текст');

        self::assertSame('средний', $result['priority']);
        self::assertSame('', $result['draft_reply']);
    }

    public function testUnknownPriorityFallsBackToDefault(): void
    {
        $analyzer = $this->analyzerWithContent('{"sentiment":"negative","category":"жалоба","summary":"s","priority":"космический","draft_reply":"d"}');

        self::assertSame('средний', $analyzer->analyze('текст')['priority']);
    }

    public function testDraftReplyIsTruncatedTo500Chars(): void
    {
        $analyzer = $this->analyzerWithContent(json_encode([
            'sentiment' => 'neutral',
            'category' => 'вопрос',
            'summary' => 's',
            'priority' => 'высокий',
            'draft_reply' => str_repeat('б', 600),
        ], JSON_UNESCAPED_UNICODE));

        $result = $analyzer->analyze('текст');

        self::assertSame(500, mb_strlen($result['draft_reply']));
    }

    public function testParsesJsonWrappedInMarkdownFences(): void
    {
        $analyzer = $this->analyzerWithContent("Вот результат:\n```json\n{\"sentiment\":\"negative\",\"category\":\"жалоба\",\"summary\":\"Жалоба\"}\n```");

        $result = $analyzer->analyze('Всё сломалось!');

        self::assertSame('negative', $result['sentiment']);
        self::assertSame('жалоба', $result['category']);
    }

    public function testUnknownCategoryFallsBackToOther(): void
    {
        $analyzer = $this->analyzerWithContent('{"sentiment":"neutral","category":"несуществующая","summary":"s"}');

        self::assertSame('другое', $analyzer->analyze('текст')['category']);
    }

    public function testInvalidSentimentReturnsNull(): void
    {
        $analyzer = $this->analyzerWithContent('{"sentiment":"angry","category":"вопрос","summary":"s"}');

        self::assertNull($analyzer->analyze('текст'));
    }

    public function testInvalidJsonReturnsNull(): void
    {
        $analyzer = $this->analyzerWithContent('это вообще не JSON');

        self::assertNull($analyzer->analyze('текст'));
    }

    public function testMissingContentReturnsNull(): void
    {
        $client = new MockHttpClient([new MockResponse(json_encode(['choices' => []]))]);
        $analyzer = new AiAnalyzer($client, new NullLogger(), self::BASE_URL, 'key', self::MODEL);

        self::assertNull($analyzer->analyze('текст'));
    }

    public function testHttpErrorReturnsNull(): void
    {
        $client = new MockHttpClient([new MockResponse('{"error":{"message":"rate limited"}}', ['http_code' => 500])]);
        $analyzer = new AiAnalyzer($client, new NullLogger(), self::BASE_URL, 'key', self::MODEL);

        self::assertNull($analyzer->analyze('текст'));
    }

    public function testTransportExceptionReturnsNull(): void
    {
        $client = new MockHttpClient(static function (): never {
            throw new TransportException('connection refused');
        });
        $analyzer = new AiAnalyzer($client, new NullLogger(), self::BASE_URL, 'key', self::MODEL);

        self::assertNull($analyzer->analyze('текст'));
    }

    public function testSummaryIsTruncatedTo200Chars(): void
    {
        $longSummary = str_repeat('а', 300);
        $analyzer = $this->analyzerWithContent(json_encode([
            'sentiment' => 'neutral',
            'category' => 'вопрос',
            'summary' => $longSummary,
        ], JSON_UNESCAPED_UNICODE));

        $result = $analyzer->analyze('текст');

        self::assertSame(200, mb_strlen($result['summary']));
    }

    public function testRequestPayload(): void
    {
        $client = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://ai.example/v1/chat/completions', $url);
            self::assertContains('authorization: bearer key', array_map('strtolower', $options['headers']));
            // the 'json' option is normalized into a JSON body before the request
            $payload = json_decode((string) $options['body'], true);
            self::assertSame('test-model', $payload['model']);
            self::assertSame('комментарий клиента', $payload['messages'][1]['content']);
            self::assertSame(0, $payload['temperature']);

            return new MockResponse(json_encode([
                'choices' => [['message' => ['content' => '{"sentiment":"neutral","category":"вопрос","summary":"s"}']]],
            ]));
        });

        $analyzer = new AiAnalyzer($client, new NullLogger(), self::BASE_URL . '/', 'key', self::MODEL);

        self::assertNotNull($analyzer->analyze('комментарий клиента'));
    }

    private function analyzerWithContent(string $content): AiAnalyzer
    {
        $client = new MockHttpClient([
            new MockResponse(json_encode(['choices' => [['message' => ['content' => $content]]]], JSON_UNESCAPED_UNICODE)),
        ]);

        return new AiAnalyzer($client, new NullLogger(), self::BASE_URL, 'key', self::MODEL);
    }
}

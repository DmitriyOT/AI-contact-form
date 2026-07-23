<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AiAnalyzer
{
    private const SENTIMENTS = ['positive', 'neutral', 'negative'];
    private const CATEGORIES = ['вопрос', 'заказ', 'жалоба', 'предложение', 'сотрудничество', 'другое'];
    private const PRIORITIES = ['низкий', 'средний', 'высокий', 'срочный'];
    private const DEFAULT_PRIORITY = 'средний';
    private const TIMEOUT_SECONDS = 10;
    // total wall-clock budget: a slow-trickling response must not hold the worker
    // far beyond the idle timeout above
    private const MAX_DURATION_SECONDS = 15;

    private const PROMPT = <<<'PROMPT'
        Ты — помощник службы поддержки. Проанализируй обращение клиента и ответь СТРОГО одним JSON-объектом без пояснений и markdown-обёрток:
        {"sentiment":"...","category":"...","summary":"...","priority":"...","draft_reply":"..."}

        Правила:
        - sentiment — тональность обращения, ровно одно из значений: "positive", "neutral", "negative".
        - category — тип обращения, ровно одно из значений: "вопрос", "заказ", "жалоба", "предложение", "сотрудничество", "другое".
        - summary — краткое резюме обращения на русском языке, одно предложение (до 200 символов).
        - priority — приоритет обработки, ровно одно из значений: "низкий", "средний", "высокий", "срочный". Жалобы и проблемы, блокирующие клиента, — "высокий" или "срочный"; благодарности и общие вопросы — "низкий" или "средний".
        - draft_reply — черновик ответа клиенту на русском языке (1–3 предложения, до 500 символов): вежливо, по существу обращения, без выдумывания фактов о компании и обещаний.
        PROMPT;

    private bool $disabledNoticeLogged = false;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(AI_BASE_URL)%')]
        private readonly string $baseUrl,
        #[Autowire('%env(AI_API_KEY)%')]
        private readonly string $apiKey,
        #[Autowire('%env(AI_MODEL)%')]
        private readonly string $model,
    ) {
    }

    /**
     * Analyzes a contact comment via an OpenAI-compatible Chat Completions API.
     *
     * @return array{sentiment: string, category: string, summary: string, priority: string, draft_reply: string}|null null on any failure (graceful fallback)
     */
    public function analyze(string $comment): ?array
    {
        if ('' === $this->apiKey) {
            // AI is disabled via configuration; log the notice only once per process
            if (!$this->disabledNoticeLogged) {
                $this->logger->info('AI analysis is disabled: AI_API_KEY is empty');
                $this->disabledNoticeLogged = true;
            }

            return null;
        }

        try {
            $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/') . '/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                ],
                'json' => [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => self::PROMPT],
                        ['role' => 'user', 'content' => $comment],
                    ],
                    'temperature' => 0,
                    // supported by OpenAI and most compatible providers; parsing below does not rely on it
                    'response_format' => ['type' => 'json_object'],
                ],
                'timeout' => self::TIMEOUT_SECONDS,
                'max_duration' => self::MAX_DURATION_SECONDS,
            ]);

            $statusCode = $response->getStatusCode();
            $payload = $response->toArray(false);
        } catch (TransportExceptionInterface|DecodingExceptionInterface $e) {
            // network error, timeout, unreadable response — never leak the comment itself
            $this->logger->warning('AI analysis request failed', ['exception' => $e]);

            return null;
        }

        if ($statusCode >= 400) {
            $this->logger->warning('AI analysis returned an error status', [
                'status' => $statusCode,
                'error' => $payload['error']['message'] ?? null,
            ]);

            return null;
        }

        $content = $payload['choices'][0]['message']['content'] ?? null;
        if (!is_string($content)) {
            $this->logger->warning('AI analysis returned an unexpected response shape');

            return null;
        }

        return $this->parseResult($content);
    }

    /**
     * Finds the first decodable JSON object in the model output.
     * Tries the whole content, then the widest {…} span, then flat {…} candidates:
     * the widest span alone breaks when the model appends prose with braces after the JSON.
     *
     * @return array<string, mixed>|null
     */
    private function extractJson(string $content): ?array
    {
        $candidates = [trim($content)];
        if (preg_match('/\{.*\}/s', $content, $match)) {
            $candidates[] = $match[0];
        }
        if (preg_match_all('/\{[^{}]*\}/', $content, $matches)) {
            array_push($candidates, ...$matches[0]);
        }

        foreach ($candidates as $candidate) {
            $data = json_decode($candidate, true);
            if (is_array($data)) {
                return $data;
            }
        }

        $this->logger->warning('AI analysis returned no JSON object');

        return null;
    }

    /**
     * @return array{sentiment: string, category: string, summary: string, priority: string, draft_reply: string}|null
     */
    private function parseResult(string $content): ?array
    {
        // models sometimes wrap JSON in ```json fences or add prose around it — extract the object
        $data = $this->extractJson($content);
        if (null === $data) {
            return null;
        }

        $sentiment = $data['sentiment'] ?? null;
        $category = $data['category'] ?? null;
        $summary = $data['summary'] ?? null;
        $priority = $data['priority'] ?? null;
        $draftReply = $data['draft_reply'] ?? null;

        if (!in_array($sentiment, self::SENTIMENTS, true) || !is_string($category) || '' === $category) {
            $this->logger->warning('AI analysis returned unusable fields');

            return null;
        }

        return [
            'sentiment' => $sentiment,
            'category' => in_array($category, self::CATEGORIES, true) ? $category : 'другое',
            // models sometimes exceed the 200-char prompt limit; the DB column is VARCHAR(255)
            'summary' => is_string($summary) && '' !== $summary ? mb_substr($summary, 0, 200) : '',
            // unknown priority degrades to a safe default instead of failing the whole analysis
            'priority' => is_string($priority) && in_array($priority, self::PRIORITIES, true) ? $priority : self::DEFAULT_PRIORITY,
            // optional by design: an empty draft is better than failing the request
            'draft_reply' => is_string($draftReply) && '' !== trim($draftReply) ? mb_substr(trim($draftReply), 0, 500) : '',
        ];
    }
}

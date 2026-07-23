<?php

/**
 * AI mock server — ONLY for local development and demo purposes.
 *
 * Imitates an OpenAI-compatible POST /chat/completions endpoint so the full
 * AI pipeline can be demonstrated without a real API key. The "analysis" is
 * trivial keyword matching; a real provider is configured via AI_BASE_URL.
 */

declare(strict_types=1);

$payload = json_decode((string) file_get_contents('php://input'), true) ?? [];

// analyze only the last user message, not the system prompt
$requestText = '';
foreach (array_reverse($payload['messages'] ?? []) as $message) {
    if (($message['role'] ?? null) === 'user') {
        $requestText = mb_strtolower((string) ($message['content'] ?? ''));
        break;
    }
}

$sentiment = 'neutral';
$category = 'вопрос';
$summary = 'Клиент задал вопрос.';
$priority = 'средний';
$draftReply = 'Здравствуйте! Спасибо за ваш вопрос. Мы получили обращение и ответим вам в ближайшее время.';

if (preg_match('/(плохо|ужас|жалоба|не работает|отвратительно)/u', $requestText)) {
    $sentiment = 'negative';
    $category = 'жалоба';
    $summary = 'Клиент недоволен и сообщает о проблеме.';
    $priority = 'высокий';
    $draftReply = 'Здравствуйте! Приносим извинения за возникшие неудобства. Мы уже разбираемся с проблемой и свяжемся с вами в ближайшее время.';
} elseif (preg_match('/(спасибо|отлично|супер|понравилось|благодар)/u', $requestText)) {
    $sentiment = 'positive';
    $category = 'другое';
    $summary = 'Клиент благодарит за сервис.';
    $priority = 'низкий';
    $draftReply = 'Здравствуйте! Спасибо за тёплые слова, нам очень приятно. Будем рады видеть вас снова!';
}

$content = json_encode([
    'sentiment' => $sentiment,
    'category' => $category,
    'summary' => $summary,
    'priority' => $priority,
    'draft_reply' => $draftReply,
], JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'id' => 'chatcmpl-mock-' . bin2hex(random_bytes(4)),
    'object' => 'chat.completion',
    'created' => time(),
    'model' => $payload['model'] ?? 'mock-model',
    'choices' => [
        [
            'index' => 0,
            'message' => [
                'role' => 'assistant',
                'content' => $content,
            ],
            'finish_reason' => 'stop',
        ],
    ],
    'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
], JSON_UNESCAPED_UNICODE);

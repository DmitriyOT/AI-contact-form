<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto;

use App\Dto\AiAnalysisResult;
use PHPUnit\Framework\TestCase;

final class AiAnalysisResultTest extends TestCase
{
    public function testFromArrayKeepsOnlyPublicFields(): void
    {
        $result = AiAnalysisResult::fromArray([
            'sentiment' => 'positive',
            'category' => 'вопрос',
            'summary' => 'Клиент интересуется услугами.',
            'priority' => 'средний',
            'draft_reply' => 'Спасибо за обращение!',
        ]);

        self::assertSame('positive', $result->sentiment);
        self::assertSame('вопрос', $result->category);
        self::assertSame('средний', $result->priority);
        self::assertSame('Клиент интересуется услугами.', $result->summary);
    }

    public function testToArrayHasNoDraftReply(): void
    {
        $result = new AiAnalysisResult('negative', 'жалоба', 'срочный', 'Клиент недоволен.');

        $data = $result->toArray();

        self::assertSame([
            'sentiment' => 'negative',
            'category' => 'жалоба',
            'priority' => 'срочный',
            'summary' => 'Клиент недоволен.',
        ], $data);
        self::assertArrayNotHasKey('draft_reply', $data);
    }
}

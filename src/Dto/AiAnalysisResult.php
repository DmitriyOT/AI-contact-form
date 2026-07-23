<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Публичная часть AI-анализа обращения, отдаётся в ответе POST /api/contact.
 * draft_reply сюда сознательно не входит: черновик ответа — внутренний,
 * для службы поддержки (хранится в БД и уходит в письме владельцу).
 */
final class AiAnalysisResult
{
    public function __construct(
        public readonly string $sentiment,
        public readonly string $category,
        public readonly string $priority,
        public readonly string $summary,
    ) {
    }

    /**
     * @param array{sentiment: string, category: string, summary: string, priority: string, draft_reply: string} $aiData результат AiAnalyzer::analyze()
     */
    public static function fromArray(array $aiData): self
    {
        return new self(
            $aiData['sentiment'],
            $aiData['category'],
            $aiData['priority'],
            $aiData['summary'],
        );
    }

    /**
     * @return array{sentiment: string, category: string, priority: string, summary: string}
     */
    public function toArray(): array
    {
        return [
            'sentiment' => $this->sentiment,
            'category' => $this->category,
            'priority' => $this->priority,
            'summary' => $this->summary,
        ];
    }
}

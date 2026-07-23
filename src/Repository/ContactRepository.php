<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\ContactRequest;
use Doctrine\DBAL\Connection;

final class ContactRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param array{sentiment?: string, category?: string, summary?: string, priority?: string, draft_reply?: string}|null $aiData AI analysis data
     *
     * @return int id of the inserted row
     */
    public function save(ContactRequest $contact, ?string $ip, ?array $aiData = null): int
    {
        $this->connection->executeStatement(
            'INSERT INTO contacts (name, phone, email, comment, ai_sentiment, ai_category, ai_summary, ai_priority, ai_draft_reply, ip_address)
             VALUES (:name, :phone, :email, :comment, :ai_sentiment, :ai_category, :ai_summary, :ai_priority, :ai_draft_reply, :ip_address)',
            [
                'name' => $contact->name,
                'phone' => $contact->phone,
                'email' => $contact->email,
                'comment' => $contact->comment,
                'ai_sentiment' => $aiData['sentiment'] ?? null,
                'ai_category' => $aiData['category'] ?? null,
                'ai_summary' => $aiData['summary'] ?? null,
                'ai_priority' => $aiData['priority'] ?? null,
                'ai_draft_reply' => $aiData['draft_reply'] ?? null,
                'ip_address' => $ip,
            ]
        );

        return (int) $this->connection->lastInsertId();
    }

    public function countAll(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM contacts');
    }

    public function countToday(): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM contacts WHERE created_at >= CURDATE()'
        );
    }

    /**
     * @return array<string, int> counters grouped by day ("Y-m-d" => count)
     */
    public function countByDay(int $days): array
    {
        // $days is an int by type, so inline interpolation here is safe
        $rows = $this->connection->fetchAllKeyValue(
            'SELECT DATE(created_at) AS day, COUNT(*) AS cnt
             FROM contacts
             WHERE created_at >= CURDATE() - INTERVAL ' . max(0, $days) . ' DAY
             GROUP BY DATE(created_at)
             ORDER BY day'
        );

        return array_map('intval', $rows);
    }
}

<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;
use RuntimeException;

final class ContactRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function save(array $contact): int
    {
        // TODO: implement in the persistence commit
        throw new RuntimeException('Not implemented yet');
    }

    public function countAll(): int
    {
        // TODO: implement in the persistence commit
        throw new RuntimeException('Not implemented yet');
    }
}

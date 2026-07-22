<?php

declare(strict_types=1);

namespace App\Dto;

final class ContactResult
{
    public function __construct(
        public readonly bool $accepted,
        public readonly string $message,
        public readonly bool $aiProcessed = false,
    ) {
    }
}

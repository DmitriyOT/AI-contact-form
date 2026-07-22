<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class ValidationFailedHttpException extends UnprocessableEntityHttpException
{
    /**
     * @param array<string, list<string>> $details validation messages grouped by field
     */
    public function __construct(private readonly array $details)
    {
        parent::__construct('Ошибка валидации');
    }

    /**
     * @return array<string, list<string>>
     */
    public function getDetails(): array
    {
        return $this->details;
    }
}

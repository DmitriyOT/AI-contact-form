<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

final class EmailSendingException extends HttpException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct(
            Response::HTTP_BAD_GATEWAY,
            'Не удалось отправить уведомление, попробуйте позже',
            $previous
        );
    }
}

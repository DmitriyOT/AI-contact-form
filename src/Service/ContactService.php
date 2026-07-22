<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ContactRequest;
use App\Dto\ContactResult;
use Psr\Log\LoggerInterface;

final class ContactService
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function handle(ContactRequest $request): ContactResult
    {
        // TODO (commit 5): rate limiting check before processing
        // TODO (commit 6): persist the request via ContactRepository
        // TODO (commit 4): send notification email via Symfony Mailer
        // TODO (commit 7): AI processing of the comment

        // no personal data in the log, just the fact of acceptance
        $this->logger->info('Contact request accepted');

        return new ContactResult(true, 'Обращение принято');
    }
}

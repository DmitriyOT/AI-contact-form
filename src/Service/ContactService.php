<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ContactRequest;
use App\Dto\ContactResult;
use App\Exception\EmailSendingException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

final class ContactService
{
    public function __construct(
        private readonly ContactMailer $contactMailer,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(ContactRequest $request): ContactResult
    {
        // TODO (commit 5): rate limiting check before processing
        // TODO (commit 6): persist the request via ContactRepository
        // TODO (commit 7): AI processing of the comment, pass $aiData to sendOwnerNotification()

        try {
            $this->contactMailer->sendOwnerNotification($request);
        } catch (TransportExceptionInterface $e) {
            // owner notification is critical: fail the whole request
            $this->logger->error('Failed to send owner notification', ['exception' => $e]);

            throw new EmailSendingException($e);
        }

        try {
            $this->contactMailer->sendUserCopy($request);
        } catch (TransportExceptionInterface $e) {
            // user copy is not critical: log and continue
            $this->logger->warning('Failed to send user copy', ['exception' => $e]);
        }

        // no personal data in the log, just the fact of acceptance
        $this->logger->info('Contact request accepted');

        return new ContactResult(true, 'Обращение принято', true);
    }
}

<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ContactRequest;
use App\Dto\ContactResult;
use App\Exception\EmailSendingException;
use App\Repository\ContactRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

final class ContactService
{
    public function __construct(
        private readonly ContactRepository $contactRepository,
        private readonly ContactMailer $contactMailer,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(ContactRequest $request, ?string $ip = null): ContactResult
    {
        // rate limiting is enforced in ContactController before this service runs
        // TODO (commit 7): AI processing of the comment, pass $aiData to save() and sendOwnerNotification()

        try {
            $contactId = $this->contactRepository->save($request, $ip);
            $this->logger->info('Contact request persisted', ['id' => $contactId]);
        } catch (Throwable $e) {
            // persistence failure must not break the user flow: the email still goes out
            $this->logger->error('Failed to persist contact request', ['exception' => $e]);
            $contactId = null;
        }

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
        $this->logger->info('Contact request accepted', ['id' => $contactId]);

        return new ContactResult(true, 'Обращение принято', true);
    }
}

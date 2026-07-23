<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\AiAnalysisResult;
use App\Dto\ContactRequest;
use App\Dto\ContactResult;
use App\Exception\EmailSendingException;
use App\Repository\ContactRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

final class ContactService
{
    public function __construct(
        private readonly ContactRepository $contactRepository,
        private readonly ContactMailer $contactMailer,
        private readonly AiAnalyzer $aiAnalyzer,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(ContactRequest $request, ?string $ip = null): ContactResult
    {
        // rate limiting is enforced in ContactController before this service runs

        // AI analysis is best-effort: null on any failure, the request must never fail because of AI
        $aiData = $this->aiAnalyzer->analyze($request->comment);

        try {
            $contactId = $this->contactRepository->save($request, $ip, $aiData);
            $this->logger->info('Contact request persisted', ['id' => $contactId]);
        } catch (Throwable $e) {
            // 201 must mean "the record exists": silently accepting a request that was
            // never persisted would corrupt the metrics and lose the contact without a trace.
            // Failing before any email goes out also keeps retries free of duplicates.
            $this->logger->error('Failed to persist contact request', ['exception' => $e]);

            throw new ServiceUnavailableHttpException(null, 'Сервис временно недоступен, попробуйте позже', $e);
        }

        try {
            $this->contactMailer->sendOwnerNotification($request, $aiData);
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
        $this->logger->info('Contact request accepted', ['id' => $contactId, 'ai' => null !== $aiData]);

        return new ContactResult(
            true,
            'Обращение принято',
            null !== $aiData,
            null !== $aiData ? AiAnalysisResult::fromArray($aiData) : null,
        );
    }
}

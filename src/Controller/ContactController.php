<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ContactRequest;
use App\Exception\ValidationFailedHttpException;
use App\Service\ContactService;
use JsonException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ContactController
{
    public function __construct(
        private readonly ContactService $contactService,
        private readonly RateLimiterFactory $contactFormLimiter,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/api/contact', name: 'api_contact_submit', methods: ['POST'])]
    public function submit(Request $request, ValidatorInterface $validator): JsonResponse
    {
        // rate limit is consumed before anything else (even before validation):
        // bot spam with garbage payloads must burn the limit too
        $clientIp = (string) $request->getClientIp();
        $limit = $this->contactFormLimiter->create($clientIp)->consume();
        if (!$limit->isAccepted()) {
            $this->logger->warning('Rate limit exceeded', ['ip' => $clientIp]);

            throw new TooManyRequestsHttpException(
                max(1, $limit->getRetryAfter()->getTimestamp() - time()),
                'Слишком много запросов, попробуйте позже'
            );
        }

        $contentType = (string) $request->headers->get('Content-Type', '');
        if (!str_starts_with($contentType, 'application/json')) {
            throw new UnsupportedMediaTypeHttpException('Ожидается Content-Type: application/json');
        }

        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new BadRequestHttpException('Невалидный JSON');
        }

        if (!is_array($data)) {
            throw new BadRequestHttpException('Невалидный JSON');
        }

        $contactRequest = ContactRequest::fromArray($data);

        $violations = $validator->validate($contactRequest);
        if (count($violations) > 0) {
            $details = [];
            foreach ($violations as $violation) {
                $details[$violation->getPropertyPath()][] = $violation->getMessage();
            }

            throw new ValidationFailedHttpException($details);
        }

        $result = $this->contactService->handle($contactRequest, $clientIp);

        $response = new JsonResponse([
            'status' => 'accepted',
            'message' => $result->message,
            'ai' => $result->aiProcessed,
        ], JsonResponse::HTTP_CREATED);
        $response->setEncodingOptions(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $response;
    }
}

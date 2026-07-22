<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ContactRequest;
use App\Exception\ValidationFailedHttpException;
use App\Service\ContactService;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnsupportedMediaTypeHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ContactController
{
    public function __construct(private readonly ContactService $contactService)
    {
    }

    #[Route('/api/contact', name: 'api_contact_submit', methods: ['POST'])]
    public function submit(Request $request, ValidatorInterface $validator): JsonResponse
    {
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

        $result = $this->contactService->handle($contactRequest);

        $response = new JsonResponse([
            'status' => 'accepted',
            'message' => $result->message,
        ], JsonResponse::HTTP_CREATED);
        $response->setEncodingOptions(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $response;
    }
}

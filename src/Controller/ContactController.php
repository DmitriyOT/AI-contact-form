<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController
{
    #[Route('/api/contact', name: 'api_contact_submit', methods: ['POST'])]
    public function submit(): JsonResponse
    {
        // TODO: implement validation, persistence, mail and AI logic
        return new JsonResponse([
            'status' => 'ok',
            'message' => 'stub',
        ]);
    }
}

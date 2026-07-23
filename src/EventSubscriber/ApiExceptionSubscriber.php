<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Exception\EmailSendingException;
use App\Exception\ValidationFailedHttpException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $throwable = $event->getThrowable();

        if ($throwable instanceof HttpExceptionInterface) {
            $statusCode = $throwable->getStatusCode();

            $error = [
                'code' => $this->codeFor($throwable),
                'message' => '' !== $throwable->getMessage()
                    ? $throwable->getMessage()
                    : Response::$statusTexts[$statusCode] ?? 'HTTP error',
            ];
            if ($throwable instanceof ValidationFailedHttpException) {
                $error['details'] = $throwable->getDetails();
            }

            $response = new JsonResponse(['error' => $error], $statusCode);
            $response->setEncodingOptions(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $response->headers->add($throwable->getHeaders());
        } else {
            // never leak internals to the client; full trace goes to the log
            $this->logger->error($throwable->getMessage(), ['exception' => $throwable]);

            $response = new JsonResponse([
                'error' => [
                    'code' => 'internal_error',
                    'message' => 'Internal server error',
                ],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
            $response->setEncodingOptions(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $event->setResponse($response);
    }

    private function codeFor(HttpExceptionInterface $throwable): string
    {
        // domain exceptions carry their own code, no matter which HTTP status they map to
        if ($throwable instanceof EmailSendingException) {
            return 'email_failed';
        }

        return match ($throwable->getStatusCode()) {
            Response::HTTP_BAD_REQUEST => 'bad_request',
            Response::HTTP_UNAUTHORIZED => 'unauthorized',
            Response::HTTP_FORBIDDEN => 'forbidden',
            Response::HTTP_NOT_FOUND => 'not_found',
            Response::HTTP_METHOD_NOT_ALLOWED => 'method_not_allowed',
            Response::HTTP_CONFLICT => 'conflict',
            Response::HTTP_REQUEST_ENTITY_TOO_LARGE => 'payload_too_large',
            Response::HTTP_UNSUPPORTED_MEDIA_TYPE => 'unsupported_media_type',
            Response::HTTP_UNPROCESSABLE_ENTITY => 'validation_failed',
            Response::HTTP_TOO_MANY_REQUESTS => 'too_many_requests',
            Response::HTTP_BAD_GATEWAY => 'bad_gateway',
            Response::HTTP_SERVICE_UNAVAILABLE => 'service_unavailable',
            default => 'http_error',
        };
    }
}

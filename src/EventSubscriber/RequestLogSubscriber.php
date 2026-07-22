<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class RequestLogSubscriber implements EventSubscriberInterface
{
    private const START_TIME_ATTRIBUTE = '_request_start_time';

    public function __construct(private readonly LoggerInterface $accessLogger)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 4096],
            KernelEvents::RESPONSE => ['onKernelResponse', -4096],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $event->getRequest()->attributes->set(self::START_TIME_ATTRIBUTE, hrtime(true));
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $startTime = $request->attributes->get(self::START_TIME_ATTRIBUTE);
        $durationMs = null !== $startTime
            ? round((hrtime(true) - $startTime) / 1e6, 2)
            : null;

        // request body is intentionally not logged (personal data)
        $this->accessLogger->info(sprintf('%s %s -> %d', $request->getMethod(), $request->getPathInfo(), $event->getResponse()->getStatusCode()), [
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'status' => $event->getResponse()->getStatusCode(),
            'ip' => $request->getClientIp(),
            'duration_ms' => $durationMs,
        ]);
    }
}

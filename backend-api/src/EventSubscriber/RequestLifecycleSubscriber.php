<?php

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class RequestLifecycleSubscriber implements EventSubscriberInterface
{
    public const REQUEST_ID_ATTR = '_request_id';

    private const START_HRTIME_ATTR = '_request_start_hrtime';

    public function __construct(
        #[Target('request')]
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 1024],
            KernelEvents::RESPONSE => ['onKernelResponse', -1024],
            KernelEvents::TERMINATE => ['onKernelTerminate', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $existing = $request->headers->get('X-Request-Id');
        $requestId = \is_string($existing) && $existing !== '' ? $existing : bin2hex(random_bytes(8));
        $request->attributes->set(self::REQUEST_ID_ATTR, $requestId);
        $request->attributes->set(self::START_HRTIME_ATTR, hrtime(true));

        $this->logger->info('request_started', [
            'request_id' => $requestId,
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'client_ip' => $request->getClientIp(),
        ]);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $requestId = $request->attributes->get(self::REQUEST_ID_ATTR);
        if (\is_string($requestId) && $requestId !== '') {
            $event->getResponse()->headers->set('X-Request-Id', $requestId);
        }
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $requestId = $request->attributes->get(self::REQUEST_ID_ATTR);
        if (!\is_string($requestId) || $requestId === '') {
            return;
        }

        $startNs = $request->attributes->get(self::START_HRTIME_ATTR);
        $durationMs = null;
        if (\is_int($startNs) || \is_float($startNs)) {
            $durationMs = round((hrtime(true) - $startNs) / 1_000_000, 2);
        }

        $this->logger->info('request_finished', [
            'request_id' => $requestId,
            'status' => $event->getResponse()->getStatusCode(),
            'duration_ms' => $durationMs,
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
        ]);
    }
}

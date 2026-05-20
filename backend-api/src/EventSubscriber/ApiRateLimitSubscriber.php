<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

final class ApiRateLimitSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire(service: 'limiter.api_global')]
        private readonly RateLimiterFactoryInterface $apiGlobalLimiter,
        #[Autowire(service: 'limiter.api_transaction_post')]
        private readonly RateLimiterFactoryInterface $transactionPostLimiter,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 20],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $pathInfo = $request->getPathInfo();

        // Only apply rate limiting to API routes
        if (!str_starts_with($pathInfo, '/api')) {
            return;
        }

        $routeName = (string) $request->attributes->get('_route', '');
        if ('' === $routeName) {
            return;
        }

        $method = $request->getMethod();

        $limiterFactory = $this->resolveLimiterFactory($routeName, $method);
        $key = $this->resolveKey($request->getClientIp() ?? 'unknown');

        $limiter = $limiterFactory->create($key);
        $limit = $limiter->consume(1);

        if ($limit->isAccepted()) {
            return;
        }

        $retryAfter = $limit->getRetryAfter();
        $retryAfterSeconds = max($retryAfter->getTimestamp() - time(), 0);

        $this->logger->warning('api_rate_limit_exceeded', [
            'route' => $routeName,
            'method' => $method,
            'key' => $key,
            'retry_after' => $retryAfter->format(\DateTimeInterface::ATOM),
        ]);

        $responseBody = [
            'message' => 'Too many requests, please try again later.',
        ];

        $response = new JsonResponse($responseBody, 429);
        $response->headers->set('Retry-After', (string) $retryAfterSeconds);
        $response->headers->set('X-RateLimit-Reset', (string) $retryAfter->getTimestamp());

        $event->setResponse($response);
    }

    private function resolveLimiterFactory(string $routeName, string $method): RateLimiterFactoryInterface
    {
        // Specific per-endpoint limits first
        if ('app_transaction' === $routeName && 'POST' === $method) {
            return $this->transactionPostLimiter;
        }

        // Fallback global limiter for the rest of the API
        return $this->apiGlobalLimiter;
    }

    private function resolveKey(string $ipFromRequest): string
    {
        $user = $this->security->getUser();

        if ($user instanceof User) {
            return 'user:'.$user->getId();
        }

        $ip = '' !== $ipFromRequest ? $ipFromRequest : 'unknown';

        return 'ip:'.$ip;
    }
}

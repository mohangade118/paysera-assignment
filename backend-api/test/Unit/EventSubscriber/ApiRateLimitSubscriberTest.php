<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\Entity\User;
use App\EventSubscriber\ApiRateLimitSubscriber;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class ApiRateLimitSubscriberTest extends TestCase
{
    #[Test]
    public function getSubscribedEventsRegistersRequestListenerAtPriorityTwenty(): void
    {
        self::assertSame(
            [
                KernelEvents::REQUEST => ['onKernelRequest', 20],
            ],
            ApiRateLimitSubscriber::getSubscribedEvents()
        );
    }

    #[Test]
    public function onKernelRequestDoesNothingForSubRequests(): void
    {
        $subscriber = $this->createSubscriberWithAcceptedGlobal();

        $request = $this->apiRequest('GET', '/api/health', 'app_health');
        $event = $this->createRequestEvent($request, HttpKernelInterface::SUB_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
    }

    #[Test]
    public function onKernelRequestDoesNothingForNonApiPaths(): void
    {
        $subscriber = $this->createSubscriberWithAcceptedGlobal();

        $request = Request::create('/', 'GET');
        $request->attributes->set('_route', 'app_home');
        $event = $this->createRequestEvent($request);

        $subscriber->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
    }

    #[Test]
    public function onKernelRequestDoesNothingWhenRouteNameEmpty(): void
    {
        $subscriber = $this->createSubscriberWithAcceptedGlobal();

        $request = Request::create('/api/health', 'GET');
        $event = $this->createRequestEvent($request);

        $subscriber->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
    }

    #[Test]
    public function onKernelRequestUsesGlobalLimiterWhenAccepted(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);

        $limitAccepted = new RateLimit(99, new \DateTimeImmutable(), true, 100);

        $limiter = $this->createMock(LimiterInterface::class);
        $limiter->expects(self::once())->method('consume')->with(1)->willReturn($limitAccepted);

        $apiGlobalLimiter = $this->createMock(RateLimiterFactoryInterface::class);
        $apiGlobalLimiter->expects(self::once())
            ->method('create')
            ->with('ip:10.0.0.1')
            ->willReturn($limiter);

        $transactionPostLimiter = $this->createMock(RateLimiterFactoryInterface::class);
        $transactionPostLimiter->expects(self::never())->method('create');

        $testEmailLimiter = $this->createMock(RateLimiterFactoryInterface::class);
        $testEmailLimiter->expects(self::never())->method('create');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');

        $subscriber = new ApiRateLimitSubscriber(
            $apiGlobalLimiter,
            $transactionPostLimiter,
            $testEmailLimiter,
            $security,
            $logger,
        );

        $request = $this->apiRequest('GET', '/api/anything', 'app_other_route', '10.0.0.1');
        $event = $this->createRequestEvent($request);

        $subscriber->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
    }

    #[Test]
    public function onKernelRequestUsesTransactionPostLimiterForAppTransactionPost(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);

        $limitAccepted = new RateLimit(2, new \DateTimeImmutable(), true, 3);

        $limiter = $this->createMock(LimiterInterface::class);
        $limiter->expects(self::once())->method('consume')->with(1)->willReturn($limitAccepted);

        $transactionPostLimiter = $this->createMock(RateLimiterFactoryInterface::class);
        $transactionPostLimiter->expects(self::once())
            ->method('create')
            ->with('ip:192.168.0.2')
            ->willReturn($limiter);

        $apiGlobalLimiter = $this->createMock(RateLimiterFactoryInterface::class);
        $apiGlobalLimiter->expects(self::never())->method('create');

        $testEmailLimiter = $this->createMock(RateLimiterFactoryInterface::class);
        $testEmailLimiter->expects(self::never())->method('create');

        $subscriber = new ApiRateLimitSubscriber(
            $apiGlobalLimiter,
            $transactionPostLimiter,
            $testEmailLimiter,
            $security,
            $this->createMock(LoggerInterface::class),
        );

        $request = $this->apiRequest('POST', '/api/transaction', 'app_transaction', '192.168.0.2');
        $event = $this->createRequestEvent($request);

        $subscriber->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
    }

    #[Test]
    public function onKernelRequestUsesUserKeyWhenAuthenticatedEntityUser(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(99);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $limitAccepted = new RateLimit(5, new \DateTimeImmutable(), true, 10);

        $limiter = $this->createMock(LimiterInterface::class);
        $limiter->method('consume')->willReturn($limitAccepted);

        $apiGlobalLimiter = $this->createMock(RateLimiterFactoryInterface::class);
        $apiGlobalLimiter->expects(self::once())->method('create')->with('user:99')->willReturn($limiter);

        $subscriber = new ApiRateLimitSubscriber(
            $apiGlobalLimiter,
            $this->createMock(RateLimiterFactoryInterface::class),
            $this->createMock(RateLimiterFactoryInterface::class),
            $security,
            $this->createMock(LoggerInterface::class),
        );

        $request = $this->apiRequest('GET', '/api/x', 'app_other_route', '1.2.3.4');
        $event = $this->createRequestEvent($request);
        $subscriber->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
    }

    #[Test]
    public function onKernelUsesIpKeyWhenSymfonyUserNotEntity(): void
    {
        $nonEntity = $this->createMock(UserInterface::class);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($nonEntity);

        $limitAccepted = new RateLimit(1, new \DateTimeImmutable(), true, 2);

        $limiter = $this->createMock(LimiterInterface::class);
        $limiter->method('consume')->willReturn($limitAccepted);

        $apiGlobalLimiter = $this->createMock(RateLimiterFactoryInterface::class);
        $apiGlobalLimiter->expects(self::once())->method('create')->with('ip:203.0.113.10')->willReturn($limiter);

        $subscriber = new ApiRateLimitSubscriber(
            $apiGlobalLimiter,
            $this->createMock(RateLimiterFactoryInterface::class),
            $this->createMock(RateLimiterFactoryInterface::class),
            $security,
            $this->createMock(LoggerInterface::class),
        );

        $request = $this->apiRequest('GET', '/api/ping', 'app_ping', '203.0.113.10');
        $event = $this->createRequestEvent($request);
        $subscriber->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
    }

    #[Test]
    public function onKernelRequestReturns429JsonAndHeadersWhenRejected(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);

        $retryAt = new \DateTimeImmutable('+90 seconds');

        $limitRejected = new RateLimit(0, $retryAt, false, 1);

        $limiter = $this->createMock(LimiterInterface::class);
        $limiter->expects(self::once())->method('consume')->with(1)->willReturn($limitRejected);

        $apiGlobalLimiter = $this->createMock(RateLimiterFactoryInterface::class);
        $apiGlobalLimiter->expects(self::once())
            ->method('create')
            ->with('ip:10.11.12.13')
            ->willReturn($limiter);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(
                'api_rate_limit_exceeded',
                self::callback(function (array $context): bool {
                    return isset(
                        $context['route'],
                        $context['method'],
                        $context['key'],
                        $context['retry_after']
                    )
                        && $context['route'] === 'app_blocked'
                        && $context['method'] === 'PUT'
                        && $context['key'] === 'ip:10.11.12.13'
                        && is_string($context['retry_after']);
                })
            );

        $subscriber = new ApiRateLimitSubscriber(
            $apiGlobalLimiter,
            $this->createMock(RateLimiterFactoryInterface::class),
            $this->createMock(RateLimiterFactoryInterface::class),
            $security,
            $logger,
        );

        $request = $this->apiRequest('PUT', '/api/quota-hit', 'app_blocked', '10.11.12.13');
        $event = $this->createRequestEvent($request);

        $subscriber->onKernelRequest($event);

        self::assertTrue($event->hasResponse());
        $response = $event->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(429, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Too many requests, please try again later.', $data['message'] ?? null);

        $retryAfterSeconds = (int) $response->headers->get('Retry-After');
        self::assertGreaterThanOrEqual(88, $retryAfterSeconds);
        self::assertLessThanOrEqual(92, $retryAfterSeconds);

        self::assertSame(
            (string) $retryAt->getTimestamp(),
            $response->headers->get('X-RateLimit-Reset')
        );
    }

    private function createSubscriberWithAcceptedGlobal(): ApiRateLimitSubscriber
    {
        return new ApiRateLimitSubscriber(
            $this->createMock(RateLimiterFactoryInterface::class),
            $this->createMock(RateLimiterFactoryInterface::class),
            $this->createMock(RateLimiterFactoryInterface::class),
            $this->createMock(Security::class),
            $this->createMock(LoggerInterface::class),
        );
    }

    private function createRequestEvent(Request $request, int $requestType = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);

        return new RequestEvent($kernel, $request, $requestType);
    }

    private function apiRequest(string $method, string $path, string $routeName, ?string $clientIp = null): Request
    {
        $server = [];
        if ($clientIp !== null) {
            $server['REMOTE_ADDR'] = $clientIp;
        }

        $request = Request::create($path, $method, [], [], [], $server);
        $request->attributes->set('_route', $routeName);

        return $request;
    }
}

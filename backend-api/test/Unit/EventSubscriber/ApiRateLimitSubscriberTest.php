<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\ApiRateLimitSubscriber;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\RateLimit;

final class ApiRateLimitSubscriberTest extends TestCase
{
    #[Test]
    public function returns429WhenLimitExceeded(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        $limit = $this->createStub(RateLimit::class);
        $limit->method('isAccepted')->willReturn(false);
        $limit->method('getRetryAfter')->willReturn(new \DateTimeImmutable('+60 seconds'));

        $limiter = $this->createStub(LimiterInterface::class);
        $limiter->method('consume')->willReturn($limit);

        $factory = $this->createStub(RateLimiterFactoryInterface::class);
        $factory->method('create')->willReturn($limiter);

        $transactionFactory = $this->createStub(RateLimiterFactoryInterface::class);

        $subscriber = new ApiRateLimitSubscriber($factory, $transactionFactory, $security, new NullLogger());

        $request = Request::create('/api/v1/users');
        $request->attributes->set('_route', 'app_api_v1_user');
        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, Kernel::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        self::assertNotNull($event->getResponse());
        self::assertSame(429, $event->getResponse()->getStatusCode());
    }
}

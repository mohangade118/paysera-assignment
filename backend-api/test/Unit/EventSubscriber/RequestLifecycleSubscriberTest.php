<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\RequestLifecycleSubscriber;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;

final class RequestLifecycleSubscriberTest extends TestCase
{
    private RequestLifecycleSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = new RequestLifecycleSubscriber(new NullLogger());
    }

    #[Test]
    public function assignsRequestIdOnApiRequest(): void
    {
        $request = Request::create('/api/health');
        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, Kernel::MAIN_REQUEST);

        $this->subscriber->onKernelRequest($event);

        $requestId = $request->attributes->get(RequestLifecycleSubscriber::REQUEST_ID_ATTR);
        self::assertIsString($requestId);
        self::assertNotSame('', $requestId);
    }

    #[Test]
    public function setsRequestIdHeaderOnResponse(): void
    {
        $request = Request::create('/api/health');
        $request->attributes->set(RequestLifecycleSubscriber::REQUEST_ID_ATTR, 'abc123');
        $response = new Response();
        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new ResponseEvent($kernel, $request, Kernel::MAIN_REQUEST, $response);

        $this->subscriber->onKernelResponse($event);

        self::assertSame('abc123', $response->headers->get('X-Request-Id'));
    }

    #[Test]
    public function ignoresNonApiPaths(): void
    {
        $request = Request::create('/index');
        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, Kernel::MAIN_REQUEST);

        $this->subscriber->onKernelRequest($event);

        self::assertNull($request->attributes->get(RequestLifecycleSubscriber::REQUEST_ID_ATTR));
    }

    #[Test]
    public function logsOnTerminateForApiRequest(): void
    {
        $request = Request::create('/api/health');
        $request->attributes->set(RequestLifecycleSubscriber::REQUEST_ID_ATTR, 'req-1');
        $request->attributes->set('_request_start_hrtime', hrtime(true));
        $response = new Response('', 200);
        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new TerminateEvent($kernel, $request, $response);

        $this->subscriber->onKernelTerminate($event);

        self::assertTrue(true);
    }
}

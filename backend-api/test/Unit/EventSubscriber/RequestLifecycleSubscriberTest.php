<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\RequestLifecycleSubscriber;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @phpstan-type LogEntry array{0: string, 1: array<string, mixed>}
 */
final class RequestLifecycleSubscriberTest extends TestCase
{
    /** Must stay aligned with RequestLifecycleSubscriber private START_HRTIME_ATTR */
    private const START_HRTIME_ATTR = '_request_start_hrtime';

    #[Test]
    public function getSubscribedEventsRegistersListenersWithExpectedPriorities(): void
    {
        self::assertSame(
            [
                KernelEvents::REQUEST => ['onKernelRequest', 1024],
                KernelEvents::RESPONSE => ['onKernelResponse', -1024],
                KernelEvents::TERMINATE => ['onKernelTerminate', 0],
            ],
            RequestLifecycleSubscriber::getSubscribedEvents()
        );
    }

    #[Test]
    public function onKernelRequestIgnoresSubRequests(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $request = self::httpRequest('/api/ping');
        $event = $this->makeRequestEvent($request, HttpKernelInterface::SUB_REQUEST);

        (new RequestLifecycleSubscriber($logger))->onKernelRequest($event);

        self::assertFalse($request->attributes->has(RequestLifecycleSubscriber::REQUEST_ID_ATTR));
        self::assertFalse($request->attributes->has(self::START_HRTIME_ATTR));
    }

    #[Test]
    public function onKernelRequestIgnoresNonApiPaths(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $request = self::httpRequest('/');
        $this->expectNoRequestInstrumentation($logger, $request);
    }

    #[Test]
    public function onKernelRequestCreatesRequestIdLogsAndStoresStartTime(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with(
                'request_started',
                self::callback(function (array $context): bool {
                    return preg_match('/^[a-f0-9]{16}$/', $context['request_id'] ?? '') === 1
                        && ($context['method'] ?? '') === 'GET'
                        && ($context['path'] ?? '') === '/api/health'
                        && ($context['client_ip'] ?? null) === '10.0.0.9';
                })
            );

        $request = self::httpRequest('/api/health', server: ['REMOTE_ADDR' => '10.0.0.9']);
        $subscriber = new RequestLifecycleSubscriber($logger);
        $subscriber->onKernelRequest($this->makeRequestEvent($request));

        $id = $request->attributes->get(RequestLifecycleSubscriber::REQUEST_ID_ATTR);
        self::assertIsString($id);
        self::assertSame(1, preg_match('/^[a-f0-9]{16}$/', $id));

        self::assertTrue($request->attributes->has(self::START_HRTIME_ATTR));
        $start = $request->attributes->get(self::START_HRTIME_ATTR);
        self::assertTrue(\is_int($start) || \is_float($start));
    }

    #[Test]
    public function onKernelRequestReusesInboundXRequestIdHeader(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with(
                'request_started',
                [
                    'request_id' => 'upstream-id-xyz',
                    'method' => 'POST',
                    'path' => '/api/v1/action',
                    'client_ip' => '10.0.0.9',
                ]
            );

        $request = self::httpRequest('/api/v1/action', 'POST');
        $request->headers->set('X-Request-Id', 'upstream-id-xyz');

        (new RequestLifecycleSubscriber($logger))->onKernelRequest($this->makeRequestEvent($request));

        self::assertSame(
            'upstream-id-xyz',
            $request->attributes->get(RequestLifecycleSubscriber::REQUEST_ID_ATTR)
        );
    }

    #[Test]
    public function onKernelResponseIgnoresSubRequests(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $subscriber = new RequestLifecycleSubscriber($logger);

        $request = self::httpRequest('/api/r');
        $request->attributes->set(RequestLifecycleSubscriber::REQUEST_ID_ATTR, 'req-keep');

        $response = new Response('');
        $event = new ResponseEvent(
            $this->kernelMock(),
            $request,
            HttpKernelInterface::SUB_REQUEST,
            $response
        );

        $subscriber->onKernelResponse($event);

        self::assertFalse($response->headers->has('X-Request-Id'));
    }

    #[Test]
    public function onKernelResponseSetsResponseHeaderWhenRequestIdPresent(): void
    {
        $subscriber = new RequestLifecycleSubscriber($this->createMock(LoggerInterface::class));

        $request = self::httpRequest('/api/x');
        $request->attributes->set(RequestLifecycleSubscriber::REQUEST_ID_ATTR, 'id-for-client');

        $response = new Response('');
        $subscriber->onKernelResponse(
            new ResponseEvent(
                $this->kernelMock(),
                $request,
                HttpKernelInterface::MAIN_REQUEST,
                $response
            )
        );

        self::assertTrue($response->headers->has('X-Request-Id'));
        self::assertSame('id-for-client', $response->headers->get('X-Request-Id'));
    }

    #[Test]
    public function onKernelResponseDoesNotSetHeaderWhenRequestIdAbsent(): void
    {
        $subscriber = new RequestLifecycleSubscriber($this->createMock(LoggerInterface::class));

        $request = self::httpRequest('/api/x');
        $response = new Response('');

        $subscriber->onKernelResponse(
            new ResponseEvent(
                $this->kernelMock(),
                $request,
                HttpKernelInterface::MAIN_REQUEST,
                $response
            )
        );

        self::assertFalse($response->headers->has('X-Request-Id'));
    }

    #[Test]
    public function onKernelTerminateIgnoresNonApiPaths(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $request = self::httpRequest('/');
        $request->attributes->set(RequestLifecycleSubscriber::REQUEST_ID_ATTR, 'x');

        (new RequestLifecycleSubscriber($logger))->onKernelTerminate(
            new TerminateEvent($this->kernelMock(), $request, new Response())
        );
    }

    #[Test]
    public function onKernelTerminateIgnoresEmptyRequestId(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('info');

        $request = self::httpRequest('/api/no-id');

        (new RequestLifecycleSubscriber($logger))->onKernelTerminate(
            new TerminateEvent($this->kernelMock(), $request, new Response())
        );
    }

    #[Test]
    public function onKernelTerminateLogsFinishedWithDurationWhenStartTimeCaptured(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $subscriber = new RequestLifecycleSubscriber($logger);

        /** @var list<LogEntry> $logLines */
        $logLines = [];
        $logger->expects(self::exactly(2))
            ->method('info')
            ->willReturnCallback(function (string $message, array $context) use (&$logLines): void {
                $logLines[] = [$message, $context];
            });

        $request = self::httpRequest('/api/flow', server: ['REMOTE_ADDR' => '10.0.0.1']);
        $subscriber->onKernelRequest($this->makeRequestEvent($request));

        $rid = $request->attributes->get(RequestLifecycleSubscriber::REQUEST_ID_ATTR);
        self::assertIsString($rid);

        $response = new Response('', Response::HTTP_NO_CONTENT);
        $subscriber->onKernelResponse(
            new ResponseEvent(
                $this->kernelMock(),
                $request,
                HttpKernelInterface::MAIN_REQUEST,
                $response
            )
        );

        $subscriber->onKernelTerminate(
            new TerminateEvent($this->kernelMock(), $request, $response)
        );

        self::assertCount(2, $logLines);
        self::assertSame('request_started', $logLines[0][0]);
        self::assertSame('request_finished', $logLines[1][0]);

        self::assertSame($rid, $logLines[0][1]['request_id']);
        self::assertSame('/api/flow', $logLines[0][1]['path']);

        self::assertSame($rid, $logLines[1][1]['request_id']);
        self::assertSame(Response::HTTP_NO_CONTENT, $logLines[1][1]['status']);
        self::assertSame('/api/flow', $logLines[1][1]['path']);
        self::assertSame('GET', $logLines[1][1]['method']);

        self::assertArrayHasKey('duration_ms', $logLines[1][1]);
        self::assertIsFloat($logLines[1][1]['duration_ms']);
        self::assertGreaterThanOrEqual(0.0, $logLines[1][1]['duration_ms']);
        self::assertNotNull($logLines[1][1]['duration_ms']);
        self::assertTrue($response->headers->has('X-Request-Id'));
        self::assertSame($rid, $response->headers->get('X-Request-Id'));
    }

    #[Test]
    public function onKernelTerminateLogsNullDurationWhenStartTimeMissing(): void
    {
        $logger = $this->createMock(LoggerInterface::class);

        $logger->expects(self::once())
            ->method('info')
            ->with(
                'request_finished',
                [
                    'request_id' => 'orphan-id',
                    'status' => 200,
                    'duration_ms' => null,
                    'method' => 'DELETE',
                    'path' => '/api/orphan',
                ]
            );

        $request = self::httpRequest('/api/orphan', 'DELETE');
        $request->attributes->set(RequestLifecycleSubscriber::REQUEST_ID_ATTR, 'orphan-id');

        (new RequestLifecycleSubscriber($logger))->onKernelTerminate(
            new TerminateEvent($this->kernelMock(), $request, new Response())
        );
    }

    private function kernelMock(): HttpKernelInterface
    {
        return $this->createMock(HttpKernelInterface::class);
    }

    /**
     * @param array<string, string> $server
     */
    private static function httpRequest(string $path, string $method = Request::METHOD_GET, array $server = []): Request
    {
        $merged = ['REMOTE_ADDR' => '10.0.0.9'] + $server;

        return Request::create($path, $method, [], [], [], $merged);
    }

    private function makeRequestEvent(Request $request, int $requestType = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        return new RequestEvent($this->kernelMock(), $request, $requestType);
    }

    private function expectNoRequestInstrumentation(
        LoggerInterface $logger,
        Request $request,
    ): void {
        (new RequestLifecycleSubscriber($logger))->onKernelRequest($this->makeRequestEvent($request));
        self::assertFalse($request->attributes->has(RequestLifecycleSubscriber::REQUEST_ID_ATTR));
        self::assertFalse($request->attributes->has(self::START_HRTIME_ATTR));
    }
}

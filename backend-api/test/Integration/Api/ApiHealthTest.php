<?php

declare(strict_types=1);

namespace App\Tests\Integration\Api;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ApiHealthTest extends WebTestCase
{
    #[Test]
    public function testHealthEndpointIsPublic(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health');

        self::assertResponseIsSuccessful();
        $payload = json_decode($client->getResponse()->getContent() ?: '', true);
        self::assertSame('ok', $payload['status']);
    }
}

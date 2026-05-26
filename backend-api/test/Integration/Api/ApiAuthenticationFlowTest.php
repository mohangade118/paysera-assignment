<?php

declare(strict_types=1);

namespace App\Tests\Integration\Api;

use App\DataFixtures\AccountFixtures;
use App\DataFixtures\UserFixtures;
use App\Tests\Support\AuthenticatesWithJwt;
use App\Tests\Support\ReloadsDoctrineFixtures;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ApiAuthenticationFlowTest extends WebTestCase
{
    use AuthenticatesWithJwt;
    use ReloadsDoctrineFixtures;

    #[Test]
    public function testLoginReturnsTokenAndTokenAccessesProtectedRoute(): void
    {
        $client = static::createClient();
        $this->purgeAndLoadFixtures([UserFixtures::class, AccountFixtures::class]);

        $token = $this->loginAndGetToken($client, 'mohangade118@gmail.com');

        $this->requestAuthenticated($client, 'GET', '/api/v1/users/1', $token);

        self::assertResponseIsSuccessful();
        $payload = json_decode($client->getResponse()->getContent() ?: '', true);
        self::assertSame('user details', $payload['message']);
        self::assertSame(1, $payload['data']['id']);
        self::assertSame('mohangade118@gmail.com', $payload['data']['email']);
    }

    #[Test]
    public function testLoginFailsWithInvalidPassword(): void
    {
        $client = static::createClient();
        $this->purgeAndLoadFixtures([UserFixtures::class, AccountFixtures::class]);

        $client->request(
            'POST',
            '/api/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'email' => 'mohangade118@gmail.com',
                'password' => 'wrong-password',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function testProtectedRouteRequiresAuthentication(): void
    {
        $client = static::createClient();
        $this->purgeAndLoadFixtures([UserFixtures::class, AccountFixtures::class]);

        $client->request('GET', '/api/v1/users/1');

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function testProtectedRouteRejectsInvalidToken(): void
    {
        $client = static::createClient();
        $this->purgeAndLoadFixtures([UserFixtures::class, AccountFixtures::class]);

        $client->request(
            'GET',
            '/api/v1/users/1',
            server: ['HTTP_AUTHORIZATION' => 'Bearer invalid-token'],
        );

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function testProtectedRouteAcceptsValidToken(): void
    {
        $client = static::createClient();
        $this->purgeAndLoadFixtures([UserFixtures::class, AccountFixtures::class]);

        $token = $this->loginAndGetToken($client, 'mohangade118@gmail.com');
        $this->requestAuthenticated($client, 'GET', '/api/v1/users/1', $token);

        self::assertResponseIsSuccessful();
    }
}

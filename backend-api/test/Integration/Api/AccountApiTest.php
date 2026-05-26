<?php

declare(strict_types=1);

namespace App\Tests\Integration\Api;

use App\DataFixtures\AccountFixtures;
use App\DataFixtures\UserFixtures;
use App\Tests\Support\AuthenticatesWithJwt;
use App\Tests\Support\ReloadsDoctrineFixtures;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AccountApiTest extends WebTestCase
{
    use AuthenticatesWithJwt;
    use ReloadsDoctrineFixtures;

    #[Test]
    public function testOwnerCanListOwnAccounts(): void
    {
        $client = static::createClient();
        $this->purgeAndLoadFixtures([UserFixtures::class, AccountFixtures::class]);

        $token = $this->loginAndGetToken($client, 'mohangade118@gmail.com');

        $this->requestAuthenticated($client, 'GET', '/api/v1/users/1/accounts', $token);

        self::assertResponseIsSuccessful();
        $payload = json_decode($client->getResponse()->getContent() ?: '', true);
        self::assertSame('account details', $payload['message']);
        self::assertCount(2, $payload['data']);
        self::assertSame(100.0, $payload['data'][0]['balance']);
        self::assertSame('INR', $payload['data'][0]['currencyType']);
        self::assertSame(1, $payload['data'][0]['status']);
    }

    #[Test]
    public function testCannotListAnotherUsersAccounts(): void
    {
        $client = static::createClient();
        $this->purgeAndLoadFixtures([UserFixtures::class, AccountFixtures::class]);

        $token = $this->loginAndGetToken($client, 'mohangade118@gmail.com');

        $this->requestAuthenticated($client, 'GET', '/api/v1/users/2/accounts', $token);

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function testUnknownUserReturnsNotFound(): void
    {
        $client = static::createClient();
        $this->purgeAndLoadFixtures([UserFixtures::class, AccountFixtures::class]);

        $token = $this->loginAndGetToken($client, 'mohangade118@gmail.com');

        $this->requestAuthenticated($client, 'GET', '/api/v1/users/999/accounts', $token);

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function testListAccountsRequiresAuthentication(): void
    {
        $client = static::createClient();
        $this->purgeAndLoadFixtures([UserFixtures::class, AccountFixtures::class]);

        $client->request('GET', '/api/v1/users/1/accounts');

        self::assertResponseStatusCodeSame(403);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Integration\Api;

use App\DataFixtures\UserFixtures;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Requires: migrations on test DB, MySQL from .env.test. JWT uses keys in test/fixtures/jwt/ (test env).
 */
final class ApiAuthenticationFlowTest extends WebTestCase
{
    public function testLoginReturnsTokenAndTokenAccessesProtectedRoute(): void
    {
        $client = static::createClient();
        $this->reloadFixtures();

        $client->request(
            'POST',
            '/api/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'mohangade118@gmail.com',
                'password' => 'password',
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseIsSuccessful();
        $loginPayload = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($loginPayload);
        self::assertArrayHasKey('token', $loginPayload);
        $token = $loginPayload['token'];
        self::assertIsString($token);
        self::assertNotSame('', $token);

        $client->request(
            'GET',
            '/api/v1/users',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer '.$token]
        );

        self::assertResponseIsSuccessful();
        $body = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('data', $body);
    }

    private function reloadFixtures(): void
    {
        $container = static::getContainer();
        $em = $container->get('doctrine')->getManager();
        $purger = new ORMPurger($em);
        $purger->purge();

        $loader = new Loader();
        $loader->addFixture(new UserFixtures($container->get(UserPasswordHasherInterface::class)));

        $executor = new ORMExecutor($em);
        $executor->execute($loader->getFixtures(), true);
    }
}

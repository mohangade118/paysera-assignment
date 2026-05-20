<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

trait AuthenticatesWithJwt
{
    protected function loginAndGetToken(KernelBrowser $client, string $email, string $password = 'password'): string
    {
        $client->request(
            'POST',
            '/api/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'email' => $email,
                'password' => $password,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent() ?: '', true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('token', $data);
        self::assertIsString($data['token']);
        self::assertNotSame('', $data['token']);

        return $data['token'];
    }

    /**
     * @param array<string, mixed>|null $body
     */
    protected function requestAuthenticated(
        KernelBrowser $client,
        string $method,
        string $uri,
        string $token,
        ?array $body = null,
    ): void {
        $server = [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/json',
        ];

        $content = null;
        if (null !== $body) {
            $content = json_encode($body, JSON_THROW_ON_ERROR);
        }

        $client->request($method, $uri, server: $server, content: $content);
    }
}

<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

final class LoginController extends AbstractController
{
    /**
     * Route exists so the URL resolves; authentication is handled by the `json_login` firewall.
     */
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(): never
    {
        throw new \LogicException('This should never be reached: handled by json_login.');
    }
}

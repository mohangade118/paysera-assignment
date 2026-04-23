<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ApiController extends AbstractController
{
    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    public function index(): Response
    {

        return $this->json([
            'status' => 'ok'
        ]);
    }
}

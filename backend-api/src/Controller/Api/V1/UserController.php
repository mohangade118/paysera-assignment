<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Repository\UserRepository;
use App\Transformer\UserTransformer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class UserController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserTransformer $userTransformer,
    ) {
    }

    #[Route('/api/v1/users/{id}', name: 'app_api_v1_user_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $user = $this->userRepository->findActiveUserById($id);
        if (null === $user) {
            throw new NotFoundHttpException('User not found');
        }

        return $this->json([
            'data' => $this->userTransformer->transform($user),
            'message' => 'user details',
        ]);
    }
}

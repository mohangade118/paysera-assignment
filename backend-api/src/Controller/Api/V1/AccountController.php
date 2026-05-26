<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Entity\User;
use App\Repository\AccountRepository;
use App\Repository\UserRepository;
use App\Transformer\AccountTransformer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class AccountController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly AccountRepository $accountRepository,
        private readonly AccountTransformer $accountTransformer,
    ) {
    }

    #[Route('/api/v1/users/{id}/accounts', name: 'app_api_v1_user_accounts', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function listByUser(int $id): Response
    {
        $authenticatedUser = $this->getUser();
        if (!$authenticatedUser instanceof User) {
            throw new AccessDeniedHttpException('Authenticated user is required');
        }

        if (null === $this->userRepository->findActiveUserById($id)) {
            throw new NotFoundHttpException('User not found');
        }

        if ($id !== $authenticatedUser->getId()) {
            throw new AccessDeniedHttpException('You can only view your own accounts');
        }

        return $this->json([
            'data' => $this->accountTransformer->transformCollection(
                $this->accountRepository->findByUserId($id),
            ),
            'message' => 'account details',
        ]);
    }
}

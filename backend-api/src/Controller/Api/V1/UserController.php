<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Entity\User;
use App\Repository\UserRepository as UserRepositoryAlias;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class UserController extends AbstractController
{


    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepositoryAlias $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('/api/v1/users', name: 'app_api_v1_user')]
    public function index(): Response
    {

        $email = 'mohangade111@gmail.com';
        if (null === $this->userRepository->findOneBy(['email' => $email])) {
            $user = new User();
            $user->setFirstName('Mohan')
                ->setLastName('Gade')
                ->setEmail($email)
                ->setContactNo('9001112233')
                ->setPassword($this->passwordHasher->hashPassword($user, 'password'))
                ->setCreatedAt(new \DateTimeImmutable())
                ->setUpdatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }
        return $this->json([
           'data' => $this->userRepository->findAll(),
           'message' => 'users listing'
        ]);
    }
}

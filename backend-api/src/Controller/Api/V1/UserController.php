<?php

namespace App\Controller\Api\V1;

use App\Entity\User;
use App\Repository\UserRepository as UserRepositoryAlias;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class UserController extends AbstractController
{


    public function __construct(private  readonly EntityManagerInterface $entityManager,
                                private  readonly UserRepositoryAlias    $userRepository)
    {
    }

    #[Route('/api/v1/users', name: 'app_api_v1_user')]
    public function index(): Response
    {

        $user = new User();
        $user->setFirstName("Mohan")
        ->setLastName("Gade")
        ->setEmail("mohangade111@gmail.com")
        ->setContactNo("8793281988")
        ->setPassword("password")
        ->setCreatedAt(new \DateTimeImmutable())
        ->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($user);
        $this->entityManager->flush();
        return $this->json([
           'data' => $this->userRepository->findAll(),
           'message' => 'users listing'
        ]);
    }
}

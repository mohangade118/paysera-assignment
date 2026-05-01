<?php

namespace App\DataFixtures;

use App\Entity\User;
use DateTimeImmutable as DateTimeImmutableAlias;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class UserFixtures extends Fixture
{
    public  const user1 = 'user1';
    public  const user2 = 'user2';

    public static function getGroups(): array
    {
        return ['user_group'];
    }

    public function load(ObjectManager $manager): void
    {
        $user1 = new User();
        $user1->setFirstName("Amit")
            ->setLastName("Sharma")
            ->setEmail("amit.sharma92@gmail.com")
            ->setContactNo("9876543210")
            ->setPassword(md5("password"))
            ->setCreatedAt(new DateTimeImmutableAlias())
            ->setUpdatedAt(new DateTimeImmutableAlias());

        $manager->persist($user1);

        $user2 = new User();
        $user2->setFirstName("Priya")
            ->setLastName("Kulkarni")
            ->setEmail("priya.kulkarni88@gmail.com")
            ->setContactNo("9123456780")
            ->setPassword(md5("password"))
            ->setCreatedAt(new DateTimeImmutableAlias())
            ->setUpdatedAt(new DateTimeImmutableAlias());

        $manager->persist($user2);
        $manager->flush();
        $this->addReference(self::user1, $user1);
        $this->addReference(self::user2, $user2);



    }
}

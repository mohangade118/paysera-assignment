<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;

trait ReloadsDoctrineFixtures
{
    protected function purgeAndLoadFixtures(FixtureInterface ...$fixtures): void
    {
        $container = static::getContainer();
        $em = $container->get('doctrine')->getManager();

        (new ORMPurger($em))->purge();

        $loader = new ContainerAwareFixtureLoader($container);
        foreach ($fixtures as $fixture) {
            $loader->addFixture($fixture);
        }

        (new ORMExecutor($em))->execute($loader->getFixtures(), true);
    }
}

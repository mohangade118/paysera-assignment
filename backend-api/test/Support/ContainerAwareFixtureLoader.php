<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Common\DataFixtures\Loader;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class ContainerAwareFixtureLoader extends Loader
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {
    }

    protected function createFixture(string $class): FixtureInterface
    {
        if ($this->container->has($class)) {
            $fixture = $this->container->get($class);
            assert($fixture instanceof FixtureInterface);

            return $fixture;
        }

        return parent::createFixture($class);
    }
}

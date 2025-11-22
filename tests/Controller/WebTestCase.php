<?php

namespace App\Tests\Controller;

use Zenstruck\Foundry\Test\Factories;

class WebTestCase extends \Symfony\Bundle\FrameworkBundle\Test\WebTestCase
{
    use Factories;

    /**
     * @template T
     * @param class-string<T> $classServiceName
     * @return T
     */
    public function getService(string $classServiceName): object
    {
        return static::getContainer()->get($classServiceName);
    }
}

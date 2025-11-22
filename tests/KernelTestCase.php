<?php

namespace App\Tests;

use Zenstruck\Foundry\Test\Factories;

class KernelTestCase extends \Symfony\Bundle\FrameworkBundle\Test\KernelTestCase
{
    use Factories;


    /**
     * @template T
     * @param class-string<T> $className
     * @return T
     */
    public function getService(string $className): mixed
    {
        return static::getContainer()->get($className);
    }
}

<?php
declare(strict_types=1);

namespace WilyCode\Router\Support;

use Psr\Container\ContainerInterface;

final class Instantiator
{
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    /** @param class-string|non-empty-string $idOrClass */
    public function make(string $idOrClass): object
    {
        if ($this->container->has($idOrClass)) {
            $obj = $this->container->get($idOrClass);
            if (is_object($obj)) {
                return $obj;
            }
        }

        if (class_exists($idOrClass)) {
            /** @var class-string $idOrClass */
            return new $idOrClass();
        }

        throw new \InvalidArgumentException('Cannot instantiate: ' . $idOrClass);
    }
}

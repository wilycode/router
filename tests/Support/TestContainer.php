<?php
declare(strict_types=1);

namespace WilyCode\Router\Tests\Support;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

final class TestContainer implements ContainerInterface
{
    /** @var array<string, mixed> */
    private array $items = [];

    public function set(string $id, mixed $value): void
    {
        $this->items[$id] = $value;
    }

    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new class('Not found: ' . $id) extends \RuntimeException implements NotFoundExceptionInterface {};
        }

        $v = $this->items[$id];
        return is_callable($v) ? $v() : $v;
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->items);
    }
}

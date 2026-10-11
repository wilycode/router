<?php

declare(strict_types=1);

namespace WilyCode\Router\Index;

use WilyCode\Router\Route;

final class RouteIndexNode
{
    /** @var array<string, self> */
    private array $staticChildren = [];

    private ?self $dynamicChild = null;

    /** @var list<array{route:Route,order:int}> */
    private array $routes = [];

    /** @param list<string> $segments */
    public function add(array $segments, int $offset, Route $route, int $order): void
    {
        if ($offset === count($segments)) {
            $this->routes[] = ['route' => $route, 'order' => $order];
            return;
        }

        $segment = $segments[$offset];
        if (str_contains($segment, '{')) {
            $this->dynamicChild ??= new self();
            $this->dynamicChild->add($segments, $offset + 1, $route, $order);
            return;
        }

        $this->staticChildren[$segment] ??= new self();
        $this->staticChildren[$segment]->add($segments, $offset + 1, $route, $order);
    }

    public function staticChild(string $segment): ?self
    {
        return $this->staticChildren[$segment] ?? null;
    }

    public function dynamicChild(): ?self
    {
        return $this->dynamicChild;
    }

    /** @return list<array{route:Route,order:int}> */
    public function routes(): array
    {
        return $this->routes;
    }
}

<?php

declare(strict_types=1);

namespace WilyCode\Router;

interface RouteCollectionInterface
{
    public function getPrefix(): string;

    /** @return list<Route> */
    public function getRoutes(): array;

    /** Idempotent lazy-loading hook. */
    public function load(): void;
}

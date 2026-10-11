<?php
declare(strict_types=1);

namespace WilyCode\Router\Tests;

use PHPUnit\Framework\TestCase;
use WilyCode\Router\HttpMethod;
use WilyCode\Router\RouteCollection;

final class RouteCollectionTest extends TestCase
{
    public function testAnyRegistersAllMethods(): void
    {
        $c = new RouteCollection()->prefix('/api');
        $c->any('ping', '/ping', static fn () => null);

        $routes = $c->getRoutes();
        self::assertCount(count(HttpMethod::cases()), $routes);

        $methods = array_map(static fn ($r) => $r->getMethod()->value, $routes);
        sort($methods);

        $expected = array_map(static fn ($m) => $m->value, HttpMethod::cases());
        sort($expected);

        self::assertSame($expected, $methods);
    }
}

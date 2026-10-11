<?php
declare(strict_types=1);

namespace WilyCode\Router\Tests;

use PHPUnit\Framework\TestCase;
use WilyCode\Router\RouteCollection;
use WilyCode\Router\Rule\IntRule;
use WilyCode\Router\Router;
use WilyCode\Router\Tests\Support\TestContainer;
use WilyCode\Router\UrlGenerator;

final class UrlGeneratorTest extends TestCase
{
    public function testGenerateUrlBuildsPathQueryAndFragment(): void
    {
        $routes = new RouteCollection();
        $routes->get(
            name: 'articles.edit',
            path: '/articles/{id}/edit',
            handler: static fn () => null,
            rules: ['id' => IntRule::class]
        );

        $router = new Router(new TestContainer());
        $router->addCollection($routes);

        $urlGenerator = new UrlGenerator($router);

        self::assertSame(
            '/articles/15/edit?tab=seo&lang=en#meta',
            $urlGenerator->generate('articles.edit', ['id' => 15], ['tab' => 'seo', 'lang' => 'en'], 'meta')
        );
    }

    public function testGenerateUrlOmitsEmptyQueryAndFragment(): void
    {
        $routes = new RouteCollection();
        $routes->get(
            name: 'articles.index',
            path: '/articles',
            handler: static fn () => null
        );

        $router = new Router(new TestContainer());
        $router->addCollection($routes);

        $urlGenerator = new UrlGenerator($router);

        self::assertSame('/articles', $urlGenerator->generate('articles.index'));
    }

    public function testGenerateUrlEncodesFragment(): void
    {
        $routes = new RouteCollection();
        $routes->get(
            name: 'articles.index',
            path: '/articles',
            handler: static fn () => null
        );

        $router = new Router(new TestContainer());
        $router->addCollection($routes);

        $urlGenerator = new UrlGenerator($router);

        self::assertSame('/articles#seo%20section', $urlGenerator->generate('articles.index', [], [], 'seo section'));
    }
}

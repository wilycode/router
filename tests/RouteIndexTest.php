<?php

declare(strict_types=1);

namespace WilyCode\Router\Tests;

use PHPUnit\Framework\TestCase;
use WilyCode\Router\HttpMethod;
use WilyCode\Router\Index\RouteIndex;
use WilyCode\Router\Route;
use WilyCode\Router\Rule\IntRule;
use WilyCode\Router\Rule\RuleInterface;

final class RouteIndexTest extends TestCase
{
    public function testMatchesOptionalRouteVariants(): void
    {
        $route = new Route(HttpMethod::Get, '/article/list[/{page}]', 'article.list');
        $index = new RouteIndex([$route]);

        self::assertSame($route, $index->match('GET', '/article/list'));
        self::assertSame($route, $index->match('GET', '/article/list/2'));
    }

    public function testPreservesTypedRuleValidationAndCasting(): void
    {
        $route = new Route(
            HttpMethod::Get,
            '/article/{id}',
            'article.show',
            rules: ['id' => IntRule::class],
        );
        $index = new RouteIndex([$route]);

        self::assertSame($route, $index->match('GET', '/article/42'));
        self::assertSame(42, $route->metadata()['id']);
        self::assertNull($index->match('GET', '/article/not-an-int'));
    }

    public function testMultiSegmentRuleUsesCorrectFallback(): void
    {
        $pathRule = new class implements RuleInterface {
            public function getPattern(): string
            {
                return '[a-z]+(?:/[a-z]+)*';
            }

            public function allowsPathSeparator(): bool
            {
                return true;
            }

            public function validate(mixed $value): bool
            {
                return is_string($value) && preg_match('~^[a-z]+(?:/[a-z]+)*$~', $value) === 1;
            }

            public function cast(string $value): string
            {
                return $value;
            }
        };

        $route = new Route(
            HttpMethod::Get,
            '/{path}',
            'page.show',
            rules: ['path' => $pathRule],
        );
        $index = new RouteIndex([$route]);

        self::assertSame($route, $index->match('GET', '/offer/websites/store'));
        self::assertSame('offer/websites/store', $route->metadata()['path']);
    }

    public function testPriorityWinsAcrossCandidateBranches(): void
    {
        $first = new Route(HttpMethod::Get, '/section/{value}', 'first', priority: 1);
        $second = new Route(HttpMethod::Get, '/{area}/{value}', 'second', priority: 10);
        $index = new RouteIndex([$first, $second]);

        self::assertSame($second, $index->match('GET', '/section/item'));
    }

    public function testMissingRouteReturnsNull(): void
    {
        $route = new Route(HttpMethod::Get, '/article/{id}', 'article.show', rules: ['id' => IntRule::class]);
        $index = new RouteIndex([$route]);

        self::assertNull($index->match('POST', '/article/1'));
        self::assertNull($index->match('GET', '/category/1'));
    }
}

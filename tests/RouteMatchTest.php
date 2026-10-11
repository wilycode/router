<?php
declare(strict_types=1);

namespace WilyCode\Router\Tests;

use PHPUnit\Framework\TestCase;
use WilyCode\Router\HttpMethod;
use WilyCode\Router\Route;
use WilyCode\Router\Rule\BoolRule;
use WilyCode\Router\Rule\IntRule;

final class RouteMatchTest extends TestCase
{
    public function testIntParamMatchAndCast(): void
    {
        $r = new Route(HttpMethod::Get, '/users/{id}', 'user.show', null, ['id' => IntRule::class]);

        self::assertTrue($r->match('/users/12'));
        self::assertSame(12, $r->metadata()['id']);

        self::assertFalse($r->match('/users/abc'));
    }

    public function testOptionalParamCanBeMissing(): void
    {
        $r = new Route(HttpMethod::Get, '/a[/{b}]', 'a.opt');

        self::assertTrue($r->match('/a'));
        self::assertArrayNotHasKey('b', $r->metadata());

        self::assertTrue($r->match('/a/xyz'));
        self::assertSame('xyz', $r->metadata()['b']);
    }

    public function testBoolRuleCastsCorrectly(): void
    {
        $r = new Route(HttpMethod::Get, '/flag/{on}', 'flag', null, ['on' => BoolRule::class]);

        self::assertTrue($r->match('/flag/1'));
        self::assertTrue($r->metadata()['on']);

        self::assertTrue($r->match('/flag/false'));
        self::assertFalse($r->metadata()['on']);
    }

    public function testGeneratePathKeepsTailAfterOptionalBlock(): void
    {
        $r = new Route(HttpMethod::Get, '/a[/{b}]/c', 'gen');

        self::assertSame('/a/c', $r->generatePath([]));
        self::assertSame('/a/xxx/c', $r->generatePath(['b' => 'xxx']));
    }

    public function testGeneratePathFailsWhenRequiredMissing(): void
    {
        $r = new Route(HttpMethod::Get, '/u/{id}', 'req');

        self::assertNull($r->generatePath([]));
        self::assertSame('/u/5', $r->generatePath(['id' => 5]));
    }
}

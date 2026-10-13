<?php
declare(strict_types=1);

namespace WilyCode\Router\Tests;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WilyCode\Router\CompiledRoutes;
use WilyCode\Router\CompiledRoutesBuilder;
use WilyCode\Router\HttpMethod;
use WilyCode\Router\Index\RouteIndex;
use WilyCode\Router\Route;
use WilyCode\Router\RouteCollection;
use WilyCode\Router\Rule\BoolRule;
use WilyCode\Router\Rule\DateRule;
use WilyCode\Router\Rule\EnumRule;
use WilyCode\Router\Rule\FloatRule;
use WilyCode\Router\Rule\IntRule;
use WilyCode\Router\Rule\RegexRule;
use WilyCode\Router\Rule\SlugRule;
use WilyCode\Router\Router;
use WilyCode\Router\Tests\Support\TestContainer;
use WilyCode\Router\Rule\UuidRule;

final class NewRouteRulesTest extends TestCase
{
    public function testAllFiveRulesMatchThroughPreparedIndex(): void
    {
        $routes = [
            new Route(HttpMethod::Get, '/token/{token}', 'token', rules: ['token' => new RegexRule('[A-Z]{2}[0-9]+')]),
            new Route(HttpMethod::Get, '/uuid/{id}', 'uuid', rules: ['id' => UuidRule::class]),
            new Route(HttpMethod::Get, '/slug/{slug}', 'slug', rules: ['slug' => SlugRule::class]),
            new Route(HttpMethod::Get, '/state/{state}', 'state', rules: ['state' => new EnumRule(['draft', 'published'])]),
            new Route(HttpMethod::Get, '/date/{date}', 'date', rules: ['date' => DateRule::class]),
        ];
        $index = new RouteIndex($routes);
        self::assertSame($routes[0], $index->match('GET', '/token/PL123'));
        self::assertSame($routes[1], $index->match('GET', '/uuid/550E8400-E29B-41D4-A716-446655440000'));
        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $routes[1]->metadata()['id']);
        self::assertSame($routes[2], $index->match('GET', '/slug/my-post'));
        self::assertSame($routes[3], $index->match('GET', '/state/published'));
        self::assertSame($routes[4], $index->match('GET', '/date/2024-02-29'));
        self::assertInstanceOf(DateTimeImmutable::class, $routes[4]->metadata()['date']);
        self::assertNull($index->match('GET', '/date/2025-02-29'));
        self::assertNull($index->match('GET', '/state/unknown'));
        self::assertNull($index->match('GET', '/slug/not--valid'));
    }

    public function testURLGenerationValidatesRules(): void
    {
        $route = new Route(HttpMethod::Get, '/articles/{slug}', 'articles.show', rules: ['slug' => SlugRule::class]);
        self::assertSame('/articles/my-article', $route->generatePath(['slug' => 'my-article']));
        self::assertNull($route->generatePath());
        $this->expectException(InvalidArgumentException::class);
        $route->generatePath(['slug' => 'Bad Slug']);
    }

    public function testEnumRejectsInvalidGeneratedUrl(): void
    {
        $route = new Route(HttpMethod::Get, '/posts/{state}', 'posts.state', rules: ['state' => new EnumRule(['draft', 'published'])]);
        self::assertSame('/posts/draft', $route->generatePath(['state' => 'draft']));
        $this->expectException(InvalidArgumentException::class);
        $route->generatePath(['state' => 'deleted']);
    }

    public function testOptionalInvalidParameterCannotBeSilentlyDropped(): void
    {
        $route = new Route(HttpMethod::Get, '/articles[/{page}]', 'articles.index', rules: ['page' => IntRule::class]);
        self::assertSame('/articles', $route->generatePath());
        self::assertSame('/articles/3', $route->generatePath(['page' => 3]));
        $this->expectException(InvalidArgumentException::class);
        $route->generatePath(['page' => 'abc']);
    }

    public function testDatesCanBeGeneratedFromStringsOrDateObjects(): void
    {
        $route = new Route(HttpMethod::Get, '/archive/{date}', 'archive', rules: ['date' => DateRule::class]);
        self::assertSame('/archive/2024-02-29', $route->generatePath(['date' => new DateTimeImmutable('2024-02-29')]));
        self::assertSame('/archive/2024-02-29', $route->generatePath(['date' => '2024-02-29']));
        $this->expectException(InvalidArgumentException::class);
        $route->generatePath(['date' => '2025-02-29']);
    }

    public function testBoolCanGenerateTrueAndFalseFromPhpBooleans(): void
    {
        $route = new Route(HttpMethod::Get, '/feature/{enabled}', 'feature', rules: ['enabled' => BoolRule::class]);
        self::assertSame('/feature/true', $route->generatePath(['enabled' => true]));
        self::assertSame('/feature/false', $route->generatePath(['enabled' => false]));
        self::assertTrue($route->match('/feature/TRUE'));
        self::assertTrue($route->metadata()['enabled']);
    }

    public function testFloatAndIntRejectOutOfRangeGeneratedValues(): void
    {
        $float = new Route(HttpMethod::Get, '/price/{price}', 'price', rules: ['price' => FloatRule::class]);
        self::assertSame('/price/-1.25', $float->generatePath(['price' => '-1.25']));
        $int = new Route(HttpMethod::Get, '/ids/{id}', 'ids', rules: ['id' => IntRule::class]);
        self::assertSame('/ids/42', $int->generatePath(['id' => 42]));
        $this->expectException(InvalidArgumentException::class);
        $int->generatePath(['id' => (string) PHP_INT_MAX . '0']);
    }

    public function testCompiledRoutesMatchConfiguredObjects(): void
    {
        $collection = new RouteCollection();
        $collection->get('code', '/code/{id}', null, ['id' => new RegexRule('[A-Z]{2}[0-9]+')]);
        $collection->get('state', '/state/{status}', null, ['status' => new EnumRule(['draft', 'published'])]);
        $manifest = (new CompiledRoutesBuilder())->build([$collection]);
        $compiled = new CompiledRoutes($manifest);
        self::assertSame('code', $compiled->match('GET', '/code/PL2026')?->getName());
        self::assertSame('state', $compiled->match('GET', '/state/draft')?->getName());
        self::assertNull($compiled->match('GET', '/state/unknown'));
    }

    public function testRouterAndCompiledRouteGenerationRejectInvalidValues(): void
    {
        $collection = new RouteCollection();
        $collection->get('article', '/article/{slug}', null, ['slug' => SlugRule::class]);

        $router = new Router(new TestContainer());
        $router->addCollection($collection);
        self::assertSame('/article/my-post', $router->generatePath('article', ['slug' => 'my-post']));

        $compiledRouter = new Router(new TestContainer());
        $compiledRouter->setCompiledRoutes(new CompiledRoutes((new CompiledRoutesBuilder())->build([$collection])));
        self::assertSame('/article/my-post', $compiledRouter->generatePath('article', ['slug' => 'my-post']));

        $this->expectException(InvalidArgumentException::class);
        $compiledRouter->generatePath('article', ['slug' => 'Invalid slug']);
    }

    public function testCompiledRulesSurviveVarExportAndReimport(): void
    {
        $collection = new RouteCollection();
        $collection->get('status', '/status/{value}', null, ['value' => new EnumRule(['draft', 'published'])]);
        $collection->get('token', '/token/{code}', null, ['code' => new RegexRule('[A-Z]{2}[0-9]{3}')]);
        $manifest = (new CompiledRoutesBuilder())->build([$collection]);
        $reloaded = eval('return ' . var_export($manifest, true) . ';');
        $compiled = new CompiledRoutes($reloaded);
        self::assertSame('status', $compiled->match('GET', '/status/draft')?->getName());
        self::assertSame('token', $compiled->match('GET', '/token/PL123')?->getName());
    }

    public function testDefaultStringRuleCannotGeneratePathSeparator(): void
    {
        $route = new Route(HttpMethod::Get, '/user/{name}', 'user');
        $this->expectException(InvalidArgumentException::class);
        $route->generatePath(['name' => 'foo/bar']);
    }

    public function testRegexRuleCannotGeneratePathSeparator(): void
    {
        $route = new Route(HttpMethod::Get, '/file/{name}', 'file', rules: ['name' => new RegexRule('.+')]);
        $this->expectException(InvalidArgumentException::class);
        $route->generatePath(['name' => 'a/b']);
    }
}

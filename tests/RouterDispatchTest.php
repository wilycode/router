<?php
declare(strict_types=1);

namespace WilyCode\Router\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use WilyCode\Router\CompiledRoutes;
use WilyCode\Router\CompiledRoutesBuilder;
use WilyCode\Router\RouteCollection;
use WilyCode\Router\Router;
use WilyCode\Router\Rule\IntRule;
use WilyCode\Router\Tests\Support\TestContainer;
use WilyCode\Router\Tests\Support\TestServerRequest;
use WilyCode\Router\Tests\Support\TestUri;

final class RouterDispatchTest extends TestCase
{
    public function testDispatchStaticRoute(): void
    {
        $container = new TestContainer();
        $resp = $this->createMock(ResponseInterface::class);

        $c = new RouteCollection();
        $c->get('hello', '/hello', static fn () => $resp);

        $router = new Router($container);
        $router->addCollection($c);

        $req = new TestServerRequest('GET', new TestUri('/hello'));
        $out = $router->dispatch($req);

        self::assertSame($resp, $out);
    }

    public function testDispatchWithCompiledRoutes(): void
    {
        $container = new TestContainer();
        $response = $this->createMock(ResponseInterface::class);
        $collection = new RouteCollection();
        $collection->get('compiled', '/compiled/{id}', static fn (): ResponseInterface => $response, ['id' => IntRule::class]);
        $router = new Router($container);
        $router->setCompiledRoutes(new CompiledRoutes(new CompiledRoutesBuilder()->build([$collection])));

        self::assertSame($response, $router->dispatch(new TestServerRequest('GET', new TestUri('/compiled/7'))));
    }

    public function testOptionalObserverExecutesControllerOnce(): void
    {
        $container = new TestContainer();
        $calls = 0;
        $observer = new class implements \WilyCode\Router\Observability\RoutingObserverInterface {
            public function around(string $phase, callable $callback): mixed
            {
                return $callback();
            }
        };
        $response = $this->createMock(ResponseInterface::class);
        $collection = new RouteCollection();
        $collection->get('hello', '/hello', static function () use (&$calls, $response): ResponseInterface {
            ++$calls;
            return $response;
        });
        $router = new Router($container, $observer);
        $router->addCollection($collection);
        $router->dispatch(new TestServerRequest('GET', new TestUri('/hello')));

        self::assertSame(1, $calls);
    }

    public function testMiddlewareIsExecutedBeforeHandler(): void
    {
        $container = new TestContainer();
        $resp = $this->createMock(ResponseInterface::class);

        $mw = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $request = $request->withAttribute('mw', 'ok');
                return $handler->handle($request);
            }
        };

        $container->set(get_class($mw), $mw);

        $c = new RouteCollection()->middleware(get_class($mw));
        $c->get(
            'x',
            '/x/{id}',
            static function (ServerRequestInterface $req, $id) use ($resp) {
                TestCase::assertSame('ok', $req->getAttribute('mw'));
                TestCase::assertSame(7, $id);
                return $resp;
            },
            ['id' => IntRule::class]
        );

        $router = new Router($container);
        $router->addCollection($c);

        $req = new TestServerRequest('GET', new TestUri('/x/7'));
        $out = $router->dispatch($req);

        self::assertSame($resp, $out);
    }
}

<?php
declare(strict_types=1);

namespace WilyCode\Router;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use WilyCode\Router\Index\RouteIndex;
use WilyCode\Router\Middleware\MiddlewareManager;
use WilyCode\Router\Middleware\MiddlewareRoute;
use WilyCode\Router\Support\Instantiator;
use WilyCode\Router\Observability\RoutingObserverInterface;

final class Router
{
    /** @var list<RouteCollectionInterface> */
    private array $collections = [];

    /** @var array<string, RouteIndex> prefix => prepared route index */
    private array $routeIndexes = [];

    /** @var array<string, array<string, Route>> method => path => route */
    private array $staticRoutes = [];

    /** @var array<string, Route> */
    private array $routeByName = [];

    /** @var list<string> */
    private array $prefixes = [];

    private bool $prepared = false;

    private ?CompiledRoutes $compiledRoutes = null;

    private ?Route $currentRoute = null;

    private readonly Instantiator $instantiator;

    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ?RoutingObserverInterface $observer = null,
    ) {
        $this->instantiator = new Instantiator($this->container);
    }

    public function addCollection(RouteCollectionInterface $collection): self
    {
        if ($this->compiledRoutes !== null) {
            throw new InvalidArgumentException('Cannot add route collections after compiled routes have been loaded.');
        }

        $this->collections[] = $collection;
        $this->prepared = false;
        return $this;
    }

    public function setCompiledRoutes(CompiledRoutes $compiledRoutes): self
    {
        if ($this->collections !== []) {
            throw new InvalidArgumentException('Cannot load compiled routes after route collections have been added.');
        }

        $this->compiledRoutes = $compiledRoutes;
        $this->prepared = true;

        return $this;
    }

    public function getCurrentRoute(): ?Route
    {
        return $this->currentRoute;
    }

    public function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        $this->observe('router.prepare', function (): void {
            $this->prepare();
        });
        $route = $this->observe('router.match', fn (): Route => $this->matchRoute($request));

        return $this->observe('router.middleware', fn (): ResponseInterface => $this->runRoute($route, $request));
    }

    /**
     * @template T
     * @param callable():T $operation
     * @return T
     */
    private function observe(string $phase, callable $operation): mixed
    {
        if ($this->observer === null) {
            return $operation();
        }

        return $this->observer->around($phase, $operation);
    }

    private function matchRoute(ServerRequestInterface $request): Route
    {
        $method = strtoupper($request->getMethod());
        $path = $request->getUri()->getPath() ?: '/';

        if ($this->compiledRoutes !== null) {
            $route = $this->compiledRoutes->match($method, $path);
            if ($route !== null) {
                return $route;
            }

            throw new NotFoundException('Route not found.');
        }

        if (isset($this->staticRoutes[$method][$path])) {
            $route = $this->staticRoutes[$method][$path];
            $route->match($path);

            return $route;
        }

        $prefix = '';
        foreach ($this->prefixes as $candidatePrefix) {
            if ($candidatePrefix === '') {
                continue;
            }
            if ($path === $candidatePrefix || str_starts_with($path, $candidatePrefix . '/')) {
                $prefix = $candidatePrefix;
                break;
            }
        }

        $index = $this->routeIndexes[$prefix] ?? null;
        $route = $index?->match($method, $path);
        if ($route !== null) {
            return $route;
        }

        if ($prefix !== '') {
            $rootIndex = $this->routeIndexes[''] ?? null;
            $route = $rootIndex?->match($method, $path);
            if ($route !== null) {
                return $route;
            }
        }

        throw new NotFoundException('Route not found.');
    }

    /** @param array<string, mixed> $params */
    public function generatePath(string $name, array $params = []): string
    {
        $this->prepare();

        $route = $this->compiledRoutes?->named($name) ?? $this->routeByName[$name] ?? null;
        if ($route === null) {
            throw new InvalidArgumentException('Unknown route name: ' . $name);
        }

        $path = $route->generatePath($params);
        if ($path === null) {
            throw new InvalidArgumentException('Missing parameters for route: ' . $name);
        }

        return $path;
    }

    private function runRoute(Route $route, ServerRequestInterface $request): ResponseInterface
    {
        $this->currentRoute = $route;

        if ($route->getHandler() === null) {
            throw new InvalidArgumentException('Handler not found for route: ' . $route->getName());
        }

        $middlewares = $this->observe('router.middleware.resolve', function () use ($route): array {
            $resolved = [];
            foreach ($route->getMiddlewares() as $middleware) {
                $resolved[] = is_object($middleware)
                    ? $middleware
                    : $this->instantiator->make($middleware);
            }

            return $resolved;
        });

        $manager = new MiddlewareManager($middlewares);
        $manager->add(new MiddlewareRoute($route, $this->container, $this->observer));

        return $manager->handle($request);
    }

    private function prepare(): void
    {
        if ($this->prepared) {
            return;
        }

        $routesByPrefix = [];
        $this->routeIndexes = [];
        $this->staticRoutes = [];
        $this->routeByName = [];

        foreach ($this->collections as $collection) {
            $this->observe('router.collection.load', static function () use ($collection): void {
                $collection->load();
            });

            foreach ($collection->getRoutes() as $route) {
                $prefix = $collection->getPrefix();
                $method = $route->getMethod()->value;

                $routesByPrefix[$prefix][] = $route;

                $named = $this->routeByName[$route->getName()] ?? null;
                if ($named !== null && $named->getPath() !== $route->getPath()) {
                    throw new InvalidArgumentException('Duplicate route name with different paths: ' . $route->getName());
                }
                $this->routeByName[$route->getName()] = $route;

                if ($route->isStatic()) {
                    $existing = $this->staticRoutes[$method][$route->getPath()] ?? null;
                    if ($existing !== null && $route->getPriority() === $existing->getPriority()) {
                        throw new InvalidArgumentException(sprintf(
                            'Duplicate static route for %s %s.',
                            $method,
                            $route->getPath(),
                        ));
                    }
                    if ($existing === null || $route->getPriority() > $existing->getPriority()) {
                        $this->staticRoutes[$method][$route->getPath()] = $route;
                    }
                }
            }
        }

        foreach ($routesByPrefix as $prefix => $routes) {
            $this->routeIndexes[$prefix] = new RouteIndex($routes);
        }

        $this->prefixes = array_keys($this->routeIndexes);
        usort($this->prefixes, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));

        $this->prepared = true;
    }

}

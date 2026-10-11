<?php
declare(strict_types=1);

namespace WilyCode\Router;

use WilyCode\Router\Rule\RuleInterface;

/**
 * @phpstan-type RuleMap array<string, RuleInterface|class-string<RuleInterface>>
 * @phpstan-type MiddlewareList list<class-string|object>
 */
final class RouteCollection implements RouteCollectionInterface
{
    private string $prefix = '';

    /** @var list<string> */
    private array $middlewares = [];

    /** @var list<Route> */
    private array $routes = [];

    private bool $loaded = false;

    public function prefix(string $prefix): self
    {
        $this->prefix = rtrim($prefix, '/');
        return $this;
    }

    public function middleware(string ...$middlewares): self
    {
        foreach ($middlewares as $m) {
            $this->middlewares[] = $m;
        }
        return $this;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * @return list<Route>
     */
    public function getRoutes(): array
    {
        $this->load();
        return $this->routes;
    }

    public function load(): void
    {
        if ($this->loaded) {
            return;
        }
        $this->loaded = true;
    }

    /**
     * @param RuleMap $rules
     * @param array<string, mixed> $defaults
     * @param MiddlewareList $middlewares
     */
    public function add(
        HttpMethod $method,
        string $name,
        string $path,
        mixed $handler,
        array $rules = [],
        array $defaults = [],
        array $middlewares = [],
        int $priority = 0
    ): self {
        $this->load();

        $fullPath = $this->prefix . $path;
        if ($fullPath === '') {
            $fullPath = '/';
        }

        $route = new Route(
            method: $method,
            path: $fullPath,
            name: $name,
            handler: $handler,
            rules: $rules,
            defaults: $defaults,
            middlewares: [...$this->middlewares, ...$middlewares],
            priority: $priority
        );

        $this->routes[] = $route;

        return $this;
    }

    /**
     * @param RuleMap $rules
     * @param array<string, mixed> $defaults
     * @param MiddlewareList $middlewares
     */
    public function get(string $name, string $path, mixed $handler, array $rules = [], array $defaults = [], array $middlewares = [], int $priority = 0): self
    {
        return $this->add(HttpMethod::Get, $name, $path, $handler, $rules, $defaults, $middlewares, $priority);
    }

    /**
     * @param RuleMap $rules
     * @param array<string, mixed> $defaults
     * @param MiddlewareList $middlewares
     */
    public function post(string $name, string $path, mixed $handler, array $rules = [], array $defaults = [], array $middlewares = [], int $priority = 0): self
    {
        return $this->add(HttpMethod::Post, $name, $path, $handler, $rules, $defaults, $middlewares, $priority);
    }

    /**
     * @param RuleMap $rules
     * @param array<string, mixed> $defaults
     * @param MiddlewareList $middlewares
     */
    public function put(string $name, string $path, mixed $handler, array $rules = [], array $defaults = [], array $middlewares = [], int $priority = 0): self
    {
        return $this->add(HttpMethod::Put, $name, $path, $handler, $rules, $defaults, $middlewares, $priority);
    }

    /**
     * @param RuleMap $rules
     * @param array<string, mixed> $defaults
     * @param MiddlewareList $middlewares
     */
    public function delete(string $name, string $path, mixed $handler, array $rules = [], array $defaults = [], array $middlewares = [], int $priority = 0): self
    {
        return $this->add(HttpMethod::Delete, $name, $path, $handler, $rules, $defaults, $middlewares, $priority);
    }

    /**
     * @param RuleMap $rules
     * @param array<string, mixed> $defaults
     * @param MiddlewareList $middlewares
     */
    public function patch(string $name, string $path, mixed $handler, array $rules = [], array $defaults = [], array $middlewares = [], int $priority = 0): self
    {
        return $this->add(HttpMethod::Patch, $name, $path, $handler, $rules, $defaults, $middlewares, $priority);
    }

    /**
     * @param RuleMap $rules
     * @param array<string, mixed> $defaults
     * @param MiddlewareList $middlewares
     */
    public function options(string $name, string $path, mixed $handler, array $rules = [], array $defaults = [], array $middlewares = [], int $priority = 0): self
    {
        return $this->add(HttpMethod::Options, $name, $path, $handler, $rules, $defaults, $middlewares, $priority);
    }

    /**
     * @param RuleMap $rules
     * @param array<string, mixed> $defaults
     * @param MiddlewareList $middlewares
     */
    public function head(string $name, string $path, mixed $handler, array $rules = [], array $defaults = [], array $middlewares = [], int $priority = 0): self
    {
        return $this->add(HttpMethod::Head, $name, $path, $handler, $rules, $defaults, $middlewares, $priority);
    }

    /**
     * @param RuleMap $rules
     * @param array<string, mixed> $defaults
     * @param MiddlewareList $middlewares
     */
    public function any(string $name, string $path, mixed $handler, array $rules = [], array $defaults = [], array $middlewares = [], int $priority = 0): self
    {
        foreach (HttpMethod::cases() as $method) {
            $this->add($method, $name, $path, $handler, $rules, $defaults, $middlewares, $priority);
        }
        return $this;
    }
}

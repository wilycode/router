<?php

declare(strict_types=1);

namespace WilyCode\Router;

use LogicException;

/**
 * Runtime view of a build-time compiled route manifest.
 *
 * The manifest contains only scalar/array routing metadata so immutable
 * production runtimes can preload it through OPcache. Route objects remain
 * request-local and are instantiated lazily only for actual candidates.
 *
 * @phpstan-type CompiledRoute array{method:string,path:string,name:string,handler:mixed,rules:array<string,mixed>,defaults:array<string,mixed>,middlewares:list<mixed>,priority:int}
 * @phpstan-type RouteIndexNode array{s:array<string,int>,d:int|null,r:list<array{0:int,1:int}>}
 * @phpstan-type RouteIndex array{roots:array<string,int>,nodes:list<RouteIndexNode>,fallback:array<string,list<array{0:int,1:int}>>}
 * @phpstan-type RouteManifest array{routes:list<CompiledRoute>,'static':array<string,array<string,int>>,indexes:array<string,RouteIndex>,routeByName:array<string,int>,prefixes:list<string>}
 */
final class CompiledRoutes
{
    /** @var array<int, Route> */
    private array $routeCache = [];

    /** @param RouteManifest $manifest */
    public function __construct(private readonly array $manifest)
    {
    }

    public function match(string $method, string $path): ?Route
    {
        $routeId = $this->manifest['static'][$method][$path] ?? null;
        if (is_int($routeId)) {
            $route = $this->route($routeId);
            return $route->match($path) ? $route : null;
        }

        $prefix = '';
        foreach ($this->manifest['prefixes'] as $candidatePrefix) {
            if ($candidatePrefix === '') {
                continue;
            }
            if ($path === $candidatePrefix || str_starts_with($path, $candidatePrefix . '/')) {
                $prefix = $candidatePrefix;
                break;
            }
        }

        $route = $this->matchIndex($prefix, $method, $path);
        if ($route !== null) {
            return $route;
        }

        if ($prefix !== '') {
            return $this->matchIndex('', $method, $path);
        }

        return null;
    }

    public function named(string $name): ?Route
    {
        $routeId = $this->manifest['routeByName'][$name] ?? null;
        return is_int($routeId) ? $this->route($routeId) : null;
    }

    /** @param RouteIndex $index */
    private function matchIndexData(array $index, string $method, string $path): ?Route
    {
        /** @var array<int, array{0:int,1:int}> $candidates */
        $candidates = [];
        $rootId = $index['roots'][$method] ?? null;

        if (is_int($rootId)) {
            $nodeIds = [$rootId];
            foreach ($this->segments($path) as $segment) {
                $next = [];
                foreach ($nodeIds as $nodeId) {
                    $node = $index['nodes'][$nodeId];
                    $staticId = $node['s'][$segment] ?? null;
                    if (is_int($staticId)) {
                        $next[$staticId] = $staticId;
                    }

                    $dynamicId = $node['d'];
                    if (is_int($dynamicId)) {
                        $next[$dynamicId] = $dynamicId;
                    }
                }

                if ($next === []) {
                    $nodeIds = [];
                    break;
                }

                $nodeIds = array_values($next);
            }

            foreach ($nodeIds as $nodeId) {
                foreach ($index['nodes'][$nodeId]['r'] as $entry) {
                    $this->addCandidate($candidates, $entry);
                }
            }
        }

        foreach ($index['fallback'][$method] ?? [] as $entry) {
            $this->addCandidate($candidates, $entry);
        }

        if ($candidates === []) {
            return null;
        }

        $ordered = array_values($candidates);
        if (count($ordered) > 1) {
            usort(
                $ordered,
                fn (array $a, array $b): int => ($this->priority($b[0]) <=> $this->priority($a[0]))
                    ?: ($a[1] <=> $b[1]),
            );
        }

        foreach ($ordered as [$routeId]) {
            $route = $this->route($routeId);
            if ($route->match($path)) {
                return $route;
            }
        }

        return null;
    }

    private function matchIndex(string $prefix, string $method, string $path): ?Route
    {
        $index = $this->manifest['indexes'][$prefix] ?? null;
        return is_array($index) ? $this->matchIndexData($index, $method, $path) : null;
    }

    /**
     * @param array<int, array{0:int,1:int}> $candidates
     * @param array{0:int,1:int} $entry
     */
    private function addCandidate(array &$candidates, array $entry): void
    {
        [$routeId, $order] = $entry;
        $known = $candidates[$routeId] ?? null;
        if ($known === null || $order < $known[1]) {
            $candidates[$routeId] = [$routeId, $order];
        }
    }

    private function priority(int $routeId): int
    {
        return $this->manifest['routes'][$routeId]['priority'];
    }

    private function route(int $routeId): Route
    {
        if (isset($this->routeCache[$routeId])) {
            return $this->routeCache[$routeId];
        }

        $definition = $this->manifest['routes'][$routeId] ?? null;
        if (!is_array($definition)) {
            throw new LogicException('Compiled route definition not found for id ' . $routeId . '.');
        }

        return $this->routeCache[$routeId] = new Route(
            method: HttpMethod::from($definition['method']),
            path: $definition['path'],
            name: $definition['name'],
            handler: $definition['handler'],
            rules: $definition['rules'],
            defaults: $definition['defaults'],
            middlewares: $definition['middlewares'],
            priority: $definition['priority'],
        );
    }

    /** @return list<string> */
    private function segments(string $path): array
    {
        $trimmed = trim($path, '/');
        return $trimmed === '' ? [] : explode('/', $trimmed);
    }
}

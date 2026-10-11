<?php

declare(strict_types=1);

namespace WilyCode\Router;

use InvalidArgumentException;

/**
 * Builds a scalar route manifest suitable for immutable production preload.
 *
 * @phpstan-type CompiledRoute array{method:string,path:string,name:string,handler:mixed,rules:array<string,mixed>,defaults:array<string,mixed>,middlewares:list<mixed>,priority:int}
 * @phpstan-type RouteIndexNode array{s:array<string,int>,d:int|null,r:list<array{0:int,1:int}>}
 * @phpstan-type RouteIndex array{roots:array<string,int>,nodes:list<RouteIndexNode>,fallback:array<string,list<array{0:int,1:int}>>}
 * @phpstan-type RouteManifest array{routes:list<CompiledRoute>,'static':array<string,array<string,int>>,indexes:array<string,RouteIndex>,routeByName:array<string,int>,prefixes:list<string>}
 */
final class CompiledRoutesBuilder
{
    /**
     * @param list<RouteCollectionInterface> $collections
     * @return RouteManifest
     */
    public function build(array $collections): array
    {
        $routes = [];
        $routeObjects = [];
        $routesByPrefix = [];
        $staticRoutes = [];
        $routeByName = [];

        foreach ($collections as $collection) {
            $prefix = $collection->getPrefix();
            foreach ($collection->getRoutes() as $route) {
                $routeId = count($routes);
                $routes[] = [
                    'method' => $route->getMethod()->value,
                    'path' => $route->getPath(),
                    'name' => $route->getName(),
                    'handler' => $route->getHandler(),
                    'rules' => $route->getRuleSpecifications(),
                    'defaults' => $route->getDefaults(),
                    'middlewares' => $route->getMiddlewares(),
                    'priority' => $route->getPriority(),
                ];
                $routeObjects[$routeId] = $route;
                $routesByPrefix[$prefix][] = $routeId;

                $namedId = $routeByName[$route->getName()] ?? null;
                if (is_int($namedId) && $routes[$namedId]['path'] !== $route->getPath()) {
                    throw new InvalidArgumentException('Duplicate route name with different paths: ' . $route->getName());
                }
                $routeByName[$route->getName()] = $routeId;

                if (!$route->isStatic()) {
                    continue;
                }

                $method = $route->getMethod()->value;
                $path = $route->getPath();
                $existingId = $staticRoutes[$method][$path] ?? null;
                if (is_int($existingId) && $route->getPriority() === $routes[$existingId]['priority']) {
                    throw new InvalidArgumentException(sprintf(
                        'Duplicate static route for %s %s.',
                        $method,
                        $path,
                    ));
                }
                if (!is_int($existingId) || $route->getPriority() > $routes[$existingId]['priority']) {
                    $staticRoutes[$method][$path] = $routeId;
                }
            }
        }

        $indexes = [];
        foreach ($routesByPrefix as $prefix => $routeIds) {
            $indexes[$prefix] = $this->buildIndex($routeIds, $routeObjects);
        }

        $prefixes = array_keys($indexes);
        usort($prefixes, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return [
            'routes' => $routes,
            'static' => $staticRoutes,
            'indexes' => $indexes,
            'routeByName' => $routeByName,
            'prefixes' => $prefixes,
        ];
    }

    /**
     * @param list<int> $routeIds
     * @param array<int, Route> $routeObjects
     * @return RouteIndex
     */
    private function buildIndex(array $routeIds, array $routeObjects): array
    {
        $roots = [];
        /** @var list<RouteIndexNode> $nodes */
        $nodes = [];
        $fallback = [];

        foreach ($routeIds as $order => $routeId) {
            $route = $routeObjects[$routeId];
            $method = $route->getMethod()->value;
            $path = $route->getPath();

            if ($this->isFixedPath($path)) {
                continue;
            }

            if ($route->hasPathSeparatorParameter()) {
                $fallback[$method][] = [$routeId, $order];
                continue;
            }

            $rootId = $roots[$method] ?? null;
            if (!is_int($rootId)) {
                $rootId = $this->newNode($nodes);
                $roots[$method] = $rootId;
            }

            foreach ($this->expandOptionalTemplates($path) as $template) {
                $this->addTemplate($nodes, $rootId, $this->segments($template), $routeId, $order);
            }
        }

        return [
            'roots' => $roots,
            'nodes' => $nodes,
            'fallback' => $fallback,
        ];
    }

    /**
     * @param list<RouteIndexNode> $nodes
     * @param-out list<RouteIndexNode> $nodes
     */
    private function newNode(array &$nodes): int
    {
        $nodes[] = ['s' => [], 'd' => null, 'r' => []];
        return count($nodes) - 1;
    }

    /**
     * @param list<RouteIndexNode> $nodes
     * @param-out list<RouteIndexNode> $nodes
     * @param list<string> $segments
     */
    private function addTemplate(array &$nodes, int $rootId, array $segments, int $routeId, int $order): void
    {
        $nodeId = $rootId;
        foreach ($segments as $segment) {
            if (str_contains($segment, '{')) {
                $childId = $nodes[$nodeId]['d'];
                if (!is_int($childId)) {
                    $childId = $this->newNode($nodes);
                    $node = $nodes[$nodeId];
                    $node['d'] = $childId;
                    $nodes[$nodeId] = $node;
                }
                $nodeId = $childId;
                continue;
            }

            $childId = $nodes[$nodeId]['s'][$segment] ?? null;
            if (!is_int($childId)) {
                $childId = $this->newNode($nodes);
                $node = $nodes[$nodeId];
                $node['s'][$segment] = $childId;
                $nodes[$nodeId] = $node;
            }
            $nodeId = $childId;
        }

        $node = $nodes[$nodeId];
        $node['r'][] = [$routeId, $order];
        $nodes[$nodeId] = $node;
    }

    private function isFixedPath(string $path): bool
    {
        return !str_contains($path, '{') && !str_contains($path, '[');
    }

    /** @return list<string> */
    private function segments(string $path): array
    {
        $trimmed = trim($path, '/');
        return $trimmed === '' ? [] : explode('/', $trimmed);
    }

    /** @return list<string> */
    private function expandOptionalTemplates(string $template): array
    {
        $start = strpos($template, '[');
        if ($start === false) {
            return [$template];
        }

        $depth = 0;
        $end = null;
        $length = strlen($template);
        for ($offset = $start; $offset < $length; $offset++) {
            if ($template[$offset] === '[') {
                $depth++;
                continue;
            }

            if ($template[$offset] === ']') {
                $depth--;
                if ($depth === 0) {
                    $end = $offset;
                    break;
                }
            }
        }

        if ($end === null) {
            throw new InvalidArgumentException('Unclosed optional route block: ' . $template);
        }

        $before = substr($template, 0, $start);
        $inside = substr($template, $start + 1, $end - $start - 1);
        $after = substr($template, $end + 1);

        return array_values(array_unique([
            ...$this->expandOptionalTemplates($before . $after),
            ...$this->expandOptionalTemplates($before . $inside . $after),
        ]));
    }
}

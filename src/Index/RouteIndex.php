<?php

declare(strict_types=1);

namespace WilyCode\Router\Index;

use InvalidArgumentException;
use WilyCode\Router\Route;

/**
 * Prepared route-candidate index.
 *
 * Fixed paths use direct lookup. Segment-safe dynamic routes use a trie and
 * routes with rules that may consume '/' stay in a small ordered fallback.
 * Route::match() remains the final authority for regex validation, defaults,
 * casts and matched attributes.
 */
final class RouteIndex
{
    /** @var array<string, array<string, Route>> */
    private array $staticRoutes = [];

    /** @var array<string, RouteIndexNode> */
    private array $dynamicRoots = [];

    /** @var array<string, list<array{route:Route,order:int}>> */
    private array $fallbackRoutes = [];

    /** @param list<Route> $routes */
    public function __construct(array $routes)
    {
        foreach ($routes as $order => $route) {
            $method = $route->getMethod()->value;
            $path = $route->getPath();

            if ($this->isFixedPath($path)) {
                $existing = $this->staticRoutes[$method][$path] ?? null;
                if ($existing === null || $route->getPriority() > $existing->getPriority()) {
                    $this->staticRoutes[$method][$path] = $route;
                }
                continue;
            }

            if ($route->hasPathSeparatorParameter()) {
                $this->fallbackRoutes[$method][] = ['route' => $route, 'order' => $order];
                continue;
            }

            $root = $this->dynamicRoots[$method] ??= new RouteIndexNode();
            foreach ($this->expandOptionalTemplates($path) as $template) {
                $root->add($this->segments($template), 0, $route, $order);
            }
        }
    }

    public function match(string $method, string $path): ?Route
    {
        if (isset($this->staticRoutes[$method][$path])) {
            $route = $this->staticRoutes[$method][$path];
            return $route->match($path) ? $route : null;
        }

        $candidates = [];
        $root = $this->dynamicRoots[$method] ?? null;
        if ($root !== null) {
            $nodes = [$root];
            foreach ($this->segments($path) as $segment) {
                $next = [];
                foreach ($nodes as $node) {
                    $static = $node->staticChild($segment);
                    if ($static !== null) {
                        $next[spl_object_id($static)] = $static;
                    }

                    $dynamic = $node->dynamicChild();
                    if ($dynamic !== null) {
                        $next[spl_object_id($dynamic)] = $dynamic;
                    }
                }

                if ($next === []) {
                    $nodes = [];
                    break;
                }

                $nodes = array_values($next);
            }

            foreach ($nodes as $node) {
                foreach ($node->routes() as $entry) {
                    $this->addCandidate($candidates, $entry);
                }
            }
        }

        foreach ($this->fallbackRoutes[$method] ?? [] as $entry) {
            $this->addCandidate($candidates, $entry);
        }

        if ($candidates === []) {
            return null;
        }

        $candidates = array_values($candidates);
        if (count($candidates) > 1) {
            usort(
                $candidates,
                static fn(array $a, array $b): int => ($b['route']->getPriority() <=> $a['route']->getPriority())
                    ?: ($a['order'] <=> $b['order']),
            );
        }

        foreach ($candidates as $candidate) {
            if ($candidate['route']->match($path)) {
                return $candidate['route'];
            }
        }

        return null;
    }

    /**
     * @param array<int, array{route:Route,order:int}> $candidates
     * @param array{route:Route,order:int} $entry
     */
    private function addCandidate(array &$candidates, array $entry): void
    {
        $id = spl_object_id($entry['route']);
        $known = $candidates[$id] ?? null;
        if ($known === null || $entry['order'] < $known['order']) {
            $candidates[$id] = $entry;
        }
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

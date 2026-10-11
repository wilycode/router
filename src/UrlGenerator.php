<?php

declare(strict_types=1);

namespace WilyCode\Router;

final class UrlGenerator
{
    public function __construct(
        private readonly Router $router
    ) {
    }

    /**
     * @param array<string, mixed> $params
     * @param array<int|string, mixed> $query
     */
    public function generate(
        string $routeName,
        array $params = [],
        array $query = [],
        ?string $fragment = null
    ): string {
        $path = $this->router->generatePath($routeName, $params);

        if ($query !== []) {
            $path .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        if ($fragment !== null && $fragment !== '') {
            $path .= '#' . rawurlencode($fragment);
        }

        return $path;
    }
}
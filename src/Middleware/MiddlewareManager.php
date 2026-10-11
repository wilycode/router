<?php

declare(strict_types=1);

namespace WilyCode\Router\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

final class MiddlewareManager implements RequestHandlerInterface
{
    private int $index = 0;

    /** @param list<MiddlewareInterface|callable> $middlewares */
    public function __construct(private array $middlewares = [])
    {
    }

    public function add(MiddlewareInterface|callable $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $middleware = $this->middlewares[$this->index] ?? null;
        $this->index++;

        if ($middleware === null) {
            throw new RuntimeException('Middleware pipeline has no terminal handler.');
        }

        if ($middleware instanceof MiddlewareInterface) {
            return $middleware->process($request, $this);
        }

        $response = $middleware($request, $this);
        if (!$response instanceof ResponseInterface) {
            throw new RuntimeException('Callable middleware must return ResponseInterface.');
        }

        return $response;
    }
}

<?php

declare(strict_types=1);

namespace WilyCode\Router\Middleware;

use Closure;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use WilyCode\Router\Route;
use WilyCode\Router\Support\Instantiator;
use WilyCode\Router\Observability\RoutingObserverInterface;

final class MiddlewareRoute implements MiddlewareInterface
{
    /** @var array<string, list<array{type:?string,name:string,default:bool,value:mixed,nullable:bool}>> */
    private static array $signatureCache = [];

    private readonly Instantiator $instantiator;

    public function __construct(
        private readonly Route $route,
        private readonly ContainerInterface $container,
        private readonly ?RoutingObserverInterface $observer = null,
    ) {
        $this->instantiator = new Instantiator($this->container);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        foreach ($this->route->metadata() as $name => $value) {
            $request = $request->withAttribute($name, $value);
        }

        $callable = $this->observe(
            'controller.resolve',
            fn (): callable => $this->resolveCallable($this->route->getHandler()),
        );

        return $this->observe('controller', function () use ($callable, $request, $handler): ResponseInterface {
            $arguments = $this->observe(
                'controller.arguments',
                fn (): array => $this->resolveArguments($callable, $request, $handler),
            );
            $response = $this->observe(
                'controller.action',
                fn (): mixed => $callable(...$arguments),
            );

            if (!$response instanceof ResponseInterface) {
                throw new \RuntimeException('Route handler must return ResponseInterface.');
            }

            return $response;
        });
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

    private function resolveCallable(mixed $handler): callable
    {
        if (is_callable($handler)) {
            return $handler;
        }

        if (is_array($handler) && count($handler) === 2 && is_string($handler[0])) {
            return [$this->instantiator->make($handler[0]), (string) $handler[1]];
        }

        if (is_string($handler)) {
            if (function_exists($handler)) {
                return $handler;
            }

            $object = $this->instantiator->make($handler);
            if (is_callable($object)) {
                return $object;
            }
        }

        throw new \InvalidArgumentException('Invalid route handler definition.');
    }

    /** @return list<mixed> */
    private function resolveArguments(
        callable $callable,
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): array {
        $signature = self::$signatureCache[$this->callableKey($callable)]
            ??= $this->reflectParameters($callable);

        $arguments = [];
        foreach ($signature as $parameter) {
            $type = $parameter['type'];

            if ($type === ServerRequestInterface::class) {
                $arguments[] = $request;
                continue;
            }
            if ($type === RequestHandlerInterface::class) {
                $arguments[] = $handler;
                continue;
            }
            if ($type === ContainerInterface::class) {
                $arguments[] = $this->container;
                continue;
            }
            if ($type !== null && $this->container->has($type)) {
                $arguments[] = $this->container->get($type);
                continue;
            }

            $attributes = $request->getAttributes();
            if (array_key_exists($parameter['name'], $attributes)) {
                $arguments[] = $attributes[$parameter['name']];
                continue;
            }
            if ($parameter['default']) {
                $arguments[] = $parameter['value'];
                continue;
            }
            if ($parameter['nullable']) {
                $arguments[] = null;
                continue;
            }

            throw new \RuntimeException(sprintf(
                'Cannot resolve route handler parameter $%s.',
                $parameter['name'],
            ));
        }

        return $arguments;
    }

    private function callableKey(callable $callable): string
    {
        if ($callable instanceof Closure) {
            return 'closure:' . spl_object_id($callable);
        }

        if (is_string($callable)) {
            return 'function:' . $callable;
        }

        if (is_array($callable)) {
            $class = is_object($callable[0]) ? $callable[0]::class : (string) $callable[0];
            return $class . '::' . $callable[1];
        }

        if (is_object($callable)) {
            return $callable::class . '::__invoke';
        }

        throw new \InvalidArgumentException('Unsupported callable type.');
    }

    /** @return list<array{type:?string,name:string,default:bool,value:mixed,nullable:bool}> */
    private function reflectParameters(callable $callable): array
    {
        $reflection = match (true) {
            $callable instanceof Closure => new ReflectionFunction($callable),
            is_string($callable) => new ReflectionFunction($callable),
            is_array($callable) => new ReflectionMethod($callable[0], (string) $callable[1]),
            default => new ReflectionMethod($callable, '__invoke'),
        };

        $parameters = [];
        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();
            $parameters[] = [
                'type' => $type instanceof ReflectionNamedType && !$type->isBuiltin()
                    ? $type->getName()
                    : null,
                'name' => $parameter->getName(),
                'default' => $parameter->isDefaultValueAvailable(),
                'value' => $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null,
                'nullable' => $parameter->allowsNull(),
            ];
        }

        return $parameters;
    }
}

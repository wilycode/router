<?php

declare(strict_types=1);

namespace WilyCode\Router\Observability;

/**
 * Optional instrumentation hook. The router does not know about profiling,
 * metrics storage, or any concrete observability implementation.
 */
interface RoutingObserverInterface
{
    /**
     * @template T
     * @param callable():T $callback
     * @return T
     */
    public function around(string $phase, callable $callback): mixed;
}

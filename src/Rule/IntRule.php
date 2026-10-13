<?php
declare(strict_types=1);

namespace WilyCode\Router\Rule;

/** Nonnegative decimal integers that fit into PHP's native integer range. */
final class IntRule implements RuleInterface
{
    public function getPattern(): string
    {
        return '[0-9]+';
    }

    public function allowsPathSeparator(): bool
    {
        return false;
    }

    public function validate(mixed $value): bool
    {
        if (!is_string($value) || preg_match('#\A[0-9]+\z#D', $value) !== 1) {
            return false;
        }

        $digits = ltrim($value, '0');
        $maximum = (string) PHP_INT_MAX;
        return strlen($digits) < strlen($maximum)
            || (strlen($digits) === strlen($maximum) && strcmp($digits, $maximum) <= 0);
    }

    public function cast(string $value): int
    {
        return (int) $value;
    }
}

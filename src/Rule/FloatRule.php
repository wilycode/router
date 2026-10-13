<?php
declare(strict_types=1);

namespace WilyCode\Router\Rule;

/** Finite decimal numbers, optionally negative; scientific notation is not accepted. */
final class FloatRule implements RuleInterface
{
    public function getPattern(): string
    {
        return '-?[0-9]+(?:\.[0-9]+)?';
    }

    public function allowsPathSeparator(): bool
    {
        return false;
    }

    public function validate(mixed $value): bool
    {
        return is_string($value)
            && preg_match('#\A(?:' . $this->getPattern() . ')\z#D', $value) === 1
            && is_finite((float) $value);
    }

    public function cast(string $value): float
    {
        return (float) $value;
    }
}

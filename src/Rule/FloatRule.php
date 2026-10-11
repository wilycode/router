<?php
declare(strict_types=1);

namespace WilyCode\Router\Rule;

final class FloatRule implements RuleInterface
{
    public function getPattern(): string
    {
        return '\d+(?:\.\d+)?';
    }

    public function allowsPathSeparator(): bool
    {
        return false;
    }

    public function validate(mixed $value): bool
    {
        return is_string($value) && preg_match('/^' . $this->getPattern() . '$/', $value) === 1;
    }

    public function cast(string $value): float
    {
        return (float) $value;
    }
}

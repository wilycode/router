<?php
declare(strict_types=1);

namespace WilyCode\Router\Rule;

final class StringRule implements RuleInterface
{
    public function getPattern(): string
    {
        return '[^/]+';
    }

    public function allowsPathSeparator(): bool
    {
        return false;
    }

    public function validate(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }

    public function cast(string $value): string
    {
        return $value;
    }
}

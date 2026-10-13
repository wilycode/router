<?php
declare(strict_types=1);

namespace WilyCode\Router\Rule;

final class BoolRule implements RuleInterface
{
    public function getPattern(): string
    {
        return '(?i:true|false)|1|0';
    }

    public function allowsPathSeparator(): bool
    {
        return false;
    }

    public function validate(mixed $value): bool
    {
        return is_string($value)
            && in_array(strtolower($value), ['true', 'false', '1', '0'], true);
    }

    public function cast(string $value): bool
    {
        return in_array(strtolower($value), ['true', '1'], true);
    }
}

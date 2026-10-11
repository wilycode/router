<?php
declare(strict_types=1);

namespace WilyCode\Router\Rule;

final class BoolRule implements RuleInterface
{
    public function getPattern(): string
    {
        return '(?:true|false|1|0)';
    }

    public function allowsPathSeparator(): bool
    {
        return false;
    }

    public function validate(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $v = strtolower($value);
        return $v === 'true' || $v === 'false' || $v === '1' || $v === '0';
    }

    public function cast(string $value): bool
    {
        $v = strtolower($value);
        return $v === 'true' || $v === '1';
    }
}

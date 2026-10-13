<?php
declare(strict_types=1);

namespace WilyCode\Router\Rule;

/** Canonical 8-4-4-4-12 hexadecimal UUID syntax; any version is accepted. */
final class UuidRule implements RuleInterface
{
    public function getPattern(): string
    {
        return '(?i:[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})';
    }

    public function allowsPathSeparator(): bool
    {
        return false;
    }

    public function validate(mixed $value): bool
    {
        return is_string($value)
            && preg_match('#\A' . $this->getPattern() . '\z#D', $value) === 1;
    }

    public function cast(string $value): string
    {
        return strtolower($value);
    }
}

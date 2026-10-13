<?php
declare(strict_types=1);

namespace WilyCode\Router\Rule;

/** Lowercase ASCII letters, digits, and single hyphen separators. */
final class SlugRule implements RuleInterface
{
    public function getPattern(): string
    {
        return '[a-z0-9]+(?:-[a-z0-9]+)*';
    }

    public function allowsPathSeparator(): bool
    {
        return false;
    }

    public function validate(mixed $value): bool
    {
        return is_string($value)
            && preg_match('#\A(?:' . $this->getPattern() . ')\z#D', $value) === 1;
    }

    public function cast(string $value): string
    {
        return $value;
    }
}

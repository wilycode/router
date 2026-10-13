<?php
declare(strict_types=1);

namespace WilyCode\Router\Rule;

use InvalidArgumentException;

/**
 * A developer-defined regular expression for a single URL path segment.
 * Patterns have no delimiters and must use non-capturing groups when possible.
 */
final readonly class RegexRule implements RuleInterface
{
    public function __construct(private string $pattern)
    {
        if ($pattern === '' || @preg_match('#\A(?:' . $pattern . ')\z#D', '') === false) {
            throw new InvalidArgumentException('Invalid route parameter regular expression.');
        }

        // Router::nodeRegex() already creates a named capture for this parameter.
        // Additional named captures can collide with other route parameters.
        if (preg_match('/\(\?(?:P<|<[a-zA-Z_]|\x27[a-zA-Z_])/', $pattern) === 1) {
            throw new InvalidArgumentException('Named capture groups are not supported in RegexRule.');
        }
    }

    public function getPattern(): string
    {
        return $this->pattern;
    }

    public function allowsPathSeparator(): bool
    {
        return false;
    }

    public function validate(mixed $value): bool
    {
        return is_string($value)
            && $value !== ''
            && !str_contains($value, '/')
            && preg_match('#\A(?:' . $this->pattern . ')\z#D', $value) === 1;
    }

    public function cast(string $value): string
    {
        return $value;
    }

    /** @param array{pattern:string} $properties */
    public static function __set_state(array $properties): self
    {
        return new self($properties['pattern']);
    }
}

<?php
declare(strict_types=1);

namespace WilyCode\Router\Rule;

use InvalidArgumentException;

/** Restrict a path segment to an explicit list of URL-safe string values. */
final readonly class EnumRule implements RuleInterface
{
    /** @var non-empty-list<string> */
    private array $values;

    /** @param list<string> $values */
    public function __construct(array $values)
    {
        if ($values === []) {
            throw new InvalidArgumentException('EnumRule requires at least one value.');
        }

        foreach ($values as $value) {
            if (!is_string($value) || preg_match('#\A[A-Za-z0-9._~-]+\z#D', $value) !== 1) {
                throw new InvalidArgumentException('EnumRule values must be nonempty URL-safe path segments.');
            }
        }

        $this->values = array_values(array_unique($values));
    }

    public function getPattern(): string
    {
        return '(?:' . implode('|', array_map(static fn (string $value): string => preg_quote($value, '#'), $this->values)) . ')';
    }

    public function allowsPathSeparator(): bool
    {
        return false;
    }

    public function validate(mixed $value): bool
    {
        return is_string($value) && in_array($value, $this->values, true);
    }

    public function cast(string $value): string
    {
        return $value;
    }

    /** @param array{values:non-empty-list<string>} $properties */
    public static function __set_state(array $properties): self
    {
        return new self($properties['values']);
    }
}

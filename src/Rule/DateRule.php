<?php
declare(strict_types=1);

namespace WilyCode\Router\Rule;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** A real Gregorian YYYY-MM-DD date, cast to midnight UTC. */
final class DateRule implements RuleInterface
{
    public function getPattern(): string
    {
        return '[0-9]{4}-[0-9]{2}-[0-9]{2}';
    }

    public function allowsPathSeparator(): bool
    {
        return false;
    }

    public function validate(mixed $value): bool
    {
        if (!is_string($value)
            || preg_match('#\A([0-9]{4})-([0-9]{2})-([0-9]{2})\z#D', $value, $parts) !== 1) {
            return false;
        }

        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }

    public function cast(string $value): DateTimeImmutable
    {
        if (!$this->validate($value)) {
            throw new InvalidArgumentException('Invalid route date: ' . $value);
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));
        if ($date === false) {
            throw new InvalidArgumentException('Invalid route date: ' . $value);
        }

        return $date;
    }
}

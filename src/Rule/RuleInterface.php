<?php
declare(strict_types=1);

namespace WilyCode\Router\Rule;

interface RuleInterface
{
    /**
     * Regex (WITHOUT delimiters) used in a named capture group.
     * Example: '\d+' or '[^/]+'.
     */
    public function getPattern(): string;

    /** Whether the rule may consume one or more path separators. */
    public function allowsPathSeparator(): bool;

    public function validate(mixed $value): bool;

    public function cast(string $value): mixed;
}

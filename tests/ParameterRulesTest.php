<?php
declare(strict_types=1);

namespace WilyCode\Router\Tests;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WilyCode\Router\Rule\BoolRule;
use WilyCode\Router\Rule\DateRule;
use WilyCode\Router\Rule\EnumRule;
use WilyCode\Router\Rule\FloatRule;
use WilyCode\Router\Rule\IntRule;
use WilyCode\Router\Rule\RegexRule;
use WilyCode\Router\Rule\SlugRule;
use WilyCode\Router\Rule\UuidRule;

final class ParameterRulesTest extends TestCase
{
    public function testRegexRuleAcceptsOnlyAFullSingleSegment(): void
    {
        $rule = new RegexRule('[A-Z]{2}[0-9]{3}');
        self::assertSame('[A-Z]{2}[0-9]{3}', $rule->getPattern());
        self::assertTrue($rule->validate('PL123'));
        self::assertSame('PL123', $rule->cast('PL123'));
        self::assertFalse($rule->validate('XXPL123'));
        self::assertFalse($rule->validate('PL123/next'));
        self::assertFalse($rule->validate('pl123'));
        self::assertFalse($rule->allowsPathSeparator());
    }

    public function testRegexRuleRejectsInvalidPatterns(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RegexRule('[abc');
    }

    public function testRegexRuleRejectsNamedCaptures(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RegexRule('(?<id>[0-9]+)');
    }

    public function testUuidAcceptsMixedCaseAndNormalizesTheResult(): void
    {
        $rule = new UuidRule();
        $value = '550E8400-E29B-41D4-A716-446655440000';
        self::assertTrue($rule->validate($value));
        self::assertSame(strtolower($value), $rule->cast($value));
        self::assertFalse($rule->validate('550e8400-e29b-41d4-a716-44665544'));
        self::assertFalse($rule->validate('invalid'));
    }

    public function testSlugRejectsUppercaseAndRepeatedOrOuterHyphens(): void
    {
        $rule = new SlugRule();
        self::assertTrue($rule->validate('my-article-2026'));
        self::assertFalse($rule->validate('My-article'));
        self::assertFalse($rule->validate('-my-article'));
        self::assertFalse($rule->validate('my--article'));
        self::assertFalse($rule->validate('my-article-'));
        self::assertFalse($rule->validate('my_article'));
    }

    public function testEnumRulesAcceptOnlyConfiguredValues(): void
    {
        $rule = new EnumRule(['draft', 'published', 'archived']);
        self::assertTrue($rule->validate('draft'));
        self::assertTrue($rule->validate('published'));
        self::assertFalse($rule->validate('Draft'));
        self::assertFalse($rule->validate('deleted'));
        self::assertFalse($rule->validate(10));
        self::assertSame('archived', $rule->cast('archived'));
    }

    public function testEnumRejectsInvalidDefinitions(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new EnumRule(['draft', 'has/slash']);
    }

    public function testEnumRejectsEmptyDefinitions(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new EnumRule([]);
    }

    public function testDateHandlesLeapYearsAndReturnsImmutableUtc(): void
    {
        $rule = new DateRule();
        self::assertTrue($rule->validate('2024-02-29'));
        self::assertFalse($rule->validate('2025-02-29'));
        self::assertFalse($rule->validate('2026-13-01'));
        self::assertFalse($rule->validate('2026-1-01'));
        $date = $rule->cast('2024-02-29');
        self::assertInstanceOf(DateTimeImmutable::class, $date);
        self::assertSame('2024-02-29 00:00:00 UTC', $date->format('Y-m-d H:i:s T'));
    }

    public function testIntRejectsOverflowAndKeepsOriginalNonnegativeSyntax(): void
    {
        $rule = new IntRule();
        self::assertTrue($rule->validate((string) PHP_INT_MAX));
        self::assertTrue($rule->validate('00017'));
        self::assertFalse($rule->validate((string) PHP_INT_MAX . '0'));
        self::assertFalse($rule->validate('-3'));
        self::assertSame(17, $rule->cast('00017'));
    }

    public function testFloatHandlesNegativeAndRejectsInfinity(): void
    {
        $rule = new FloatRule();
        self::assertTrue($rule->validate('-12.75'));
        self::assertSame(-12.75, $rule->cast('-12.75'));
        self::assertFalse($rule->validate(str_repeat('9', 400)));
        self::assertFalse($rule->validate('1e3'));
        self::assertFalse($rule->validate('NaN'));
    }

    public function testBoolIsCaseInsensitiveInValidationAndRegex(): void
    {
        $rule = new BoolRule();
        self::assertTrue($rule->validate('TRUE'));
        self::assertTrue($rule->validate('False'));
        self::assertTrue($rule->cast('TRUE'));
        self::assertFalse($rule->cast('FALSE'));
        self::assertFalse($rule->validate('yes'));
    }

    public function testConfiguredRulesCanBeExportedInCompiledManifests(): void
    {
        $regex = new RegexRule('[A-Z]{2}[0-9]+');
        $enum = new EnumRule(['draft', 'published']);
        $restored = eval('return ' . var_export([$regex, $enum], true) . ';');
        self::assertInstanceOf(RegexRule::class, $restored[0]);
        self::assertInstanceOf(EnumRule::class, $restored[1]);
        self::assertTrue($restored[0]->validate('PL123'));
        self::assertTrue($restored[1]->validate('draft'));
    }
}

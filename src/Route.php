<?php

declare(strict_types=1);

namespace WilyCode\Router;

use InvalidArgumentException;
use WilyCode\Router\Rule\RuleInterface;
use WilyCode\Router\Rule\StringRule;

final class Route
{
    /** @var array<string, mixed> */
    private array $attributes = [];

    /** @var array<string, RuleInterface>|null */
    private ?array $compiledRules = null;

    /** @var array<string, RuleInterface|class-string<RuleInterface>> */
    private readonly array $ruleSpecifications;

    private ?string $regex = null;

    /** @var list<array{0:int,1:mixed}>|null */
    private ?array $nodes = null;

    /**
     * @param array<string, RuleInterface|class-string<RuleInterface>> $rules
     * @param array<string, mixed> $defaults
     * @param list<class-string|object> $middlewares
     */
    public function __construct(
        private readonly HttpMethod $method,
        private readonly string $path,
        private readonly string $name,
        private readonly mixed $handler = null,
        array $rules = [],
        private readonly array $defaults = [],
        private readonly array $middlewares = [],
        private readonly int $priority = 0,
    ) {
        $this->ruleSpecifications = $rules;

        if ($this->name === '') {
            throw new InvalidArgumentException('Route name cannot be empty.');
        }

        if ($this->path === '' || $this->path[0] !== '/') {
            throw new InvalidArgumentException('Route path must start with "/": ' . $this->path);
        }
    }

    public function getName(): string { return $this->name; }
    public function getMethod(): HttpMethod { return $this->method; }
    public function getPath(): string { return $this->path; }
    public function getHandler(): mixed { return $this->handler; }
    /** @return array<string, mixed> */
    public function getDefaults(): array { return $this->defaults; }
    /** @return list<class-string|object> */
    public function getMiddlewares(): array { return $this->middlewares; }
    public function getPriority(): int { return $this->priority; }
    /** @return array<string, RuleInterface|class-string<RuleInterface>> */
    public function getRuleSpecifications(): array { return $this->ruleSpecifications; }
    /** @return array<string, mixed> */
    public function metadata(): array { return $this->attributes; }

    public function isStatic(): bool
    {
        return !str_contains($this->path, '{');
    }

    public function hasPathSeparatorParameter(): bool
    {
        if ($this->ruleSpecifications === []) {
            return false;
        }

        foreach ($this->rules() as $rule) {
            if ($rule->allowsPathSeparator()) {
                return true;
            }
        }

        return false;
    }

    public function match(string $path): bool
    {
        if (preg_match($this->compiledRegex(), $path, $matches) !== 1) {
            return false;
        }

        $attributes = $this->defaults;
        foreach ($this->rules() as $name => $rule) {
            $raw = $matches[$name] ?? null;
            if ($raw === null || $raw === '') {
                continue;
            }

            if (!$rule->validate($raw)) {
                return false;
            }

            $attributes[$name] = $rule->cast($raw);
        }

        $this->attributes = $attributes;
        return true;
    }

    /** @param array<string, mixed> $parameters */
    public function generatePath(array $parameters = []): ?string
    {
        $missing = false;
        $path = $this->renderNodes(
            $this->nodes(),
            array_replace($this->defaults, $parameters),
            $missing,
        );

        if ($missing) {
            return null;
        }

        $path = preg_replace('#/+#', '/', $path) ?? $path;

        return $path === '' ? '/' : $path;
    }

    private function compiledRegex(): string
    {
        if ($this->regex !== null) {
            return $this->regex;
        }

        $pattern = '';
        foreach ($this->nodes() as $node) {
            $pattern .= $this->nodeRegex($node);
        }

        return $this->regex = '#^' . $pattern . '$#D';
    }

    /** @param array{0:int,1:mixed} $node */
    private function nodeRegex(array $node): string
    {
        return match ($node[0]) {
            1 => preg_quote((string) $node[1], '#'),
            2 => '(?<' . $node[1] . '>' . ($this->rules()[$node[1]] ?? new StringRule())->getPattern() . ')',
            3 => '(?:' . implode('', array_map($this->nodeRegex(...), $node[1])) . ')?',
            default => throw new \LogicException('Unknown route node.'),
        };
    }

    /** @return array<string, RuleInterface> */
    private function rules(): array
    {
        if ($this->compiledRules !== null) {
            return $this->compiledRules;
        }

        $rules = [];
        foreach ($this->parameterNames() as $name) {
            $specification = $this->ruleSpecifications[$name] ?? StringRule::class;

            if ($specification instanceof RuleInterface) {
                $rules[$name] = $specification;
                continue;
            }

            $rules[$name] = new $specification();
        }

        return $this->compiledRules = $rules;
    }

    /** @return list<string> */
    private function parameterNames(): array
    {
        $names = [];
        $walk = static function (array $nodes) use (&$walk, &$names): void {
            foreach ($nodes as $node) {
                if ($node[0] === 2) {
                    $names[] = $node[1];
                } elseif ($node[0] === 3) {
                    $walk($node[1]);
                }
            }
        };
        $walk($this->nodes());

        return array_values(array_unique($names));
    }

    /** @return list<array{0:int,1:mixed}> */
    private function nodes(): array
    {
        if ($this->nodes !== null) {
            return $this->nodes;
        }

        $offset = 0;
        $this->nodes = $this->parseNodes($this->path, $offset, null);

        if ($offset !== strlen($this->path)) {
            throw new InvalidArgumentException('Unexpected route template terminator: ' . $this->path);
        }

        return $this->nodes;
    }

    /** @return list<array{0:int,1:mixed}> */
    private function parseNodes(string $template, int &$offset, ?string $terminator): array
    {
        $nodes = [];
        $text = '';
        $length = strlen($template);

        while ($offset < $length) {
            $character = $template[$offset];

            if ($terminator !== null && $character === $terminator) {
                if ($text !== '') {
                    $nodes[] = [1, $text];
                    $text = '';
                }
                $offset++;
                return $nodes;
            }

            if ($character === '[') {
                if ($text !== '') {
                    $nodes[] = [1, $text];
                    $text = '';
                }
                $offset++;
                $nodes[] = [3, $this->parseNodes($template, $offset, ']')];
                continue;
            }

            if ($character === '{') {
                if ($text !== '') {
                    $nodes[] = [1, $text];
                    $text = '';
                }
                $end = strpos($template, '}', $offset + 1);
                if ($end === false) {
                    throw new InvalidArgumentException('Unclosed route parameter: ' . $template);
                }

                $name = trim(substr($template, $offset + 1, $end - $offset - 1));
                if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $name) !== 1) {
                    throw new InvalidArgumentException('Invalid route parameter name: ' . $name);
                }

                $nodes[] = [2, $name];
                $offset = $end + 1;
                continue;
            }

            if ($character === ']') {
                throw new InvalidArgumentException('Unexpected "]" in route template: ' . $template);
            }

            $text .= $character;
            $offset++;
        }

        if ($text !== '') {
            $nodes[] = [1, $text];
        }

        if ($terminator !== null) {
            throw new InvalidArgumentException('Unclosed optional block in route: ' . $template);
        }

        return $nodes;
    }

    /**
     * @param list<array{0:int,1:mixed}> $nodes
     * @param array<string, mixed> $parameters
     */
    private function renderNodes(array $nodes, array $parameters, bool &$missing): string
    {
        $output = '';

        foreach ($nodes as $node) {
            if ($node[0] === 1) {
                $output .= $node[1];
                continue;
            }

            if ($node[0] === 2) {
                $value = $parameters[$node[1]] ?? null;
                if ($value === null || $value === '') {
                    $missing = true;
                    return '';
                }

                $output .= rawurlencode((string) $value);
                continue;
            }

            $optionalMissing = false;
            $optional = $this->renderNodes($node[1], $parameters, $optionalMissing);
            if (!$optionalMissing) {
                $output .= $optional;
            }
        }

        return $output;
    }
}

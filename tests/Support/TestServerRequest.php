<?php
declare(strict_types=1);

namespace WilyCode\Router\Tests\Support;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

final class TestServerRequest implements ServerRequestInterface
{
    /** @var array<string, mixed> */
    private array $attributes = [];

    /** @var array<string, string[]> */
    private array $headers = [];

    private StreamInterface $body;

    /** @param array<string, mixed> $attributes */
    public function __construct(
        private string $method = 'GET',
        private UriInterface $uri = new TestUri('/'),
        array $attributes = [],
    ) {
        $this->attributes = $attributes;
        $this->body = new TestStream();
    }

    public function getRequestTarget(): string { return $this->uri->getPath(); }
    public function withRequestTarget(string $requestTarget): RequestInterface { return $this; }

    public function getMethod(): string { return $this->method; }
    public function withMethod(string $method): RequestInterface { $c = clone $this; $c->method = $method; return $c; }

    public function getUri(): UriInterface { return $this->uri; }
    public function withUri(UriInterface $uri, bool $preserveHost = false): RequestInterface { $c = clone $this; $c->uri = $uri; return $c; }

    public function getProtocolVersion(): string { return '1.1'; }
    public function withProtocolVersion(string $version): RequestInterface { return $this; }

    public function getHeaders(): array { return $this->headers; }
    public function hasHeader(string $name): bool { return isset($this->headers[strtolower($name)]); }
    public function getHeader(string $name): array { return $this->headers[strtolower($name)] ?? []; }
    public function getHeaderLine(string $name): string { return implode(', ', $this->getHeader($name)); }

    public function withHeader(string $name, $value): RequestInterface
    {
        $c = clone $this;
        $c->headers[strtolower($name)] = is_array($value) ? array_values($value) : [$value];
        return $c;
    }

    public function withAddedHeader(string $name, $value): RequestInterface
    {
        $c = clone $this;
        $k = strtolower($name);
        $add = is_array($value) ? array_values($value) : [$value];
        $c->headers[$k] = array_merge($c->headers[$k] ?? [], $add);
        return $c;
    }

    public function withoutHeader(string $name): RequestInterface
    {
        $c = clone $this;
        unset($c->headers[strtolower($name)]);
        return $c;
    }

    public function getBody(): StreamInterface { return $this->body; }
    public function withBody(StreamInterface $body): RequestInterface { $c = clone $this; $c->body = $body; return $c; }

    public function getServerParams(): array { return []; }
    public function getCookieParams(): array { return []; }
    public function withCookieParams(array $cookies): ServerRequestInterface { return $this; }

    public function getQueryParams(): array { return []; }
    public function withQueryParams(array $query): ServerRequestInterface { return $this; }

    public function getUploadedFiles(): array { return []; }
    public function withUploadedFiles(array $uploadedFiles): ServerRequestInterface { return $this; }

    public function getParsedBody(): mixed { return null; }
    public function withParsedBody($data): ServerRequestInterface { return $this; }

    public function getAttributes(): array { return $this->attributes; }
    public function getAttribute(string $name, mixed $default = null): mixed { return $this->attributes[$name] ?? $default; }

    public function withAttribute(string $name, mixed $value): ServerRequestInterface
    {
        $c = clone $this;
        $c->attributes[$name] = $value;
        return $c;
    }

    public function withoutAttribute(string $name): ServerRequestInterface
    {
        $c = clone $this;
        unset($c->attributes[$name]);
        return $c;
    }
}

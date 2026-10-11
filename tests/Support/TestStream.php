<?php
declare(strict_types=1);

namespace WilyCode\Router\Tests\Support;

use Psr\Http\Message\StreamInterface;

final class TestStream implements StreamInterface
{
    private string $data = '';

    public function __toString(): string { return $this->data; }
    public function close(): void {}
    public function detach() { return null; }
    public function getSize(): ?int { return strlen($this->data); }
    public function tell(): int { return 0; }
    public function eof(): bool { return true; }
    public function isSeekable(): bool { return false; }
    public function seek(int $offset, int $whence = SEEK_SET): void { throw new \RuntimeException('Not seekable'); }
    public function rewind(): void {}
    public function isWritable(): bool { return false; }
    public function write(string $string): int { throw new \RuntimeException('Not writable'); }
    public function isReadable(): bool { return true; }
    public function read(int $length): string { return ''; }
    public function getContents(): string { return $this->data; }
    public function getMetadata(?string $key = null): mixed { return $key === null ? [] : null; }
}

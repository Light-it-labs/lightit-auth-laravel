<?php

declare(strict_types=1);

namespace Lightitlabs\Tests\Support;

/**
 * A `stream_wrapper_register`-able wrapper that lies about how much of a write actually reached
 * disk: it reports success for the full payload while only persisting a prefix of it. This
 * reproduces a short/partial write that `file_put_contents()` itself cannot detect, so tests can
 * assert that a caller verifies the full written content rather than trusting the return value.
 */
final class TruncatingStreamWrapper
{
    /** @var resource|null */
    public $context;

    private mixed $handle = null;

    public static function register(string $protocol): void
    {
        if (in_array($protocol, stream_get_wrappers(), true)) {
            stream_wrapper_unregister($protocol);
        }

        stream_wrapper_register($protocol, self::class);
    }

    public static function unregister(string $protocol): void
    {
        if (in_array($protocol, stream_get_wrappers(), true)) {
            stream_wrapper_unregister($protocol);
        }
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        $realPath = $this->realPath($path);
        $this->handle = fopen($realPath, $mode);

        return $this->handle !== false;
    }

    /**
     * Drops the final line of the payload but reports the full length as written, simulating a
     * short write that a caller's return-value check cannot catch.
     */
    public function stream_write(string $data): int
    {
        $lastNewline = strrpos(rtrim($data, "\n"), "\n");
        $truncated = $lastNewline === false ? '' : substr($data, 0, $lastNewline + 1);

        fwrite($this->handle, $truncated);

        return strlen($data);
    }

    public function stream_read(int $count): string|false
    {
        return fread($this->handle, $count);
    }

    public function stream_eof(): bool
    {
        return feof($this->handle);
    }

    public function stream_close(): void
    {
        fclose($this->handle);
    }

    public function stream_flush(): bool
    {
        return fflush($this->handle);
    }

    /**
     * @return array<int|string, int>|false
     */
    public function stream_stat(): array|false
    {
        return fstat($this->handle);
    }

    /**
     * @return array<int|string, int>|false
     */
    public function url_stat(string $path, int $flags): array|false
    {
        return @stat($this->realPath($path));
    }

    public function unlink(string $path): bool
    {
        return unlink($this->realPath($path));
    }

    private function realPath(string $path): string
    {
        $position = strpos($path, '://');

        return $position === false ? $path : substr($path, $position + 3);
    }
}

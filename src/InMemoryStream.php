<?php

/**
 * TOBENTO
 *
 * @copyright   Tobias Strub, TOBENTO
 * @license     MIT License, see LICENSE file distributed with this source code.
 * @author      Tobias Strub
 * @link        https://www.tobento.ch
 */

declare(strict_types=1);

namespace Tobento\Service\Upload;

use Psr\Http\Message\StreamInterface;

/**
 * InMemoryStream
 *
 * A lightweight PSR-7 StreamInterface implementation backed by an in-memory
 * temporary stream. This class is used internally by InMemoryUploadedFile
 * to provide a PSR-7 compatible stream without touching the filesystem.
 *
 * It is not intended for general-purpose streaming, but specifically for
 * handling nested ZIP contents during recursive validation.
 */
class InMemoryStream implements StreamInterface
{
    /**
     * @var resource|null The underlying PHP stream resource.
     */
    protected $resource;

    /**
     * Create a new in-memory stream from the given content.
     *
     * @param string $content The raw content to store in the stream.
     */
    public function __construct(string $content)
    {
        $this->resource = fopen('php://temp', 'r+');
        fwrite($this->resource, $content);
        rewind($this->resource);
    }

    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        if (!is_resource($this->resource)) {
            return '';
        }

        $contents = stream_get_contents($this->resource, -1, 0);
        return $contents === false ? '' : $contents;
    }

    /**
     * {@inheritdoc}
     */
    public function close(): void
    {
        $res = $this->resource;

        if (is_resource($res)) {
            fclose($res);
        }

        $this->resource = null;
    }

    /**
     * {@inheritdoc}
     */
    public function detach()
    {
        $res = $this->resource;
        $this->resource = null;
        return $res;
    }

    /**
     * {@inheritdoc}
     */
    public function getSize(): ?int
    {
        $this->ensureResource();

        $stats = fstat($this->resource);
        return $stats['size'] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function tell(): int
    {
        $this->ensureResource();

        $pos = ftell($this->resource);
        return $pos === false ? 0 : $pos;
    }

    /**
     * {@inheritdoc}
     */
    public function eof(): bool
    {
        $this->ensureResource();
        return feof($this->resource);
    }

    /**
     * {@inheritdoc}
     */
    public function isSeekable(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function seek($offset, $whence = SEEK_SET): void
    {
        $this->ensureResource();
        fseek($this->resource, $offset, $whence);
    }

    /**
     * {@inheritdoc}
     */
    public function rewind(): void
    {
        $this->ensureResource();
        rewind($this->resource);
    }

    /**
     * {@inheritdoc}
     */
    public function isWritable(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function write($string): int
    {
        $this->ensureResource();

        $written = fwrite($this->resource, $string);
        return $written === false ? 0 : $written;
    }

    /**
     * {@inheritdoc}
     */
    public function isReadable(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function read($length): string
    {
        $this->ensureResource();

        $data = fread($this->resource, $length);
        return $data === false ? '' : $data;
    }

    /**
     * {@inheritdoc}
     */
    public function getContents(): string
    {
        $this->ensureResource();

        $data = stream_get_contents($this->resource);
        return $data === false ? '' : $data;
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata($key = null)
    {
        $this->ensureResource();

        $meta = stream_get_meta_data($this->resource);
        return $key ? ($meta[$key] ?? null) : $meta;
    }
    
    private function ensureResource(): void
    {
        if (!is_resource($this->resource)) {
            throw new \RuntimeException('Stream is detached');
        }
    }
}
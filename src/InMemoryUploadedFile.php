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

use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\StreamInterface;

/**
 * InMemoryUploadedFile
 *
 * A lightweight PSR-7 UploadedFileInterface implementation used internally
 * by the ZipValidator when validating nested ZIP archives.
 *
 * This class stores file contents entirely in memory and is not intended
 * for handling real uploaded files. It exists solely to allow recursive
 * validation of ZIP files without requiring PSR-17 factories or touching
 * the filesystem.
 */
class InMemoryUploadedFile implements UploadedFileInterface
{
    /**
     * @var StreamInterface The in-memory stream containing the file content.
     */
    protected StreamInterface $stream;
    
    protected null|int $size = null;

    /**
     * Create a new in-memory uploaded file.
     *
     * @param string $filename The client-provided filename.
     * @param string $content The raw file content.
     * @param string $mediaType The client-provided media type.
     * @param int $error
     */
    public function __construct(
        protected string $filename,
        protected string $content,
        protected string $mediaType = 'application/octet-stream',
        protected int $error = \UPLOAD_ERR_OK,
    ) {
        $this->stream = new InMemoryStream($content);
    }

    /**
     * {@inheritdoc}
     */
    public function getStream(): StreamInterface
    {
        return $this->stream;
    }

    /**
     * {@inheritdoc}
     */
    public function moveTo($targetPath): void
    {
        file_put_contents($targetPath, $this->content);
    }
    
    /**
     * Set the file size in KB.
     *
     * @param int $kilobytes
     * @return static
     */
    public function setSize(int $kilobytes): static
    {
        $this->size = $kilobytes * 1024; // convert to bytes
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getSize(): ?int
    {
        return $this->size ?? strlen($this->content);
    }
    
    /**
     * Set the upload error code.
     *
     * @param int $error
     * @return static
     */
    public function setError(int $error): static
    {
        $this->error = $error;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getError(): int
    {
        return $this->error;
    }

    /**
     * {@inheritdoc}
     */
    public function getClientFilename(): ?string
    {
        return $this->filename;
    }

    /**
     * {@inheritdoc}
     */
    public function getClientMediaType(): ?string
    {
        return $this->mediaType;
    }
}
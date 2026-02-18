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

/**
 * CopyFileWrapper
 *
 * Wraps an UploadedFileInterface so validation can still run normally,
 * while allowing higher-level logic (CRUD, Blocks, API, etc.) to detect
 * that the file should be copied instead of processed (e.g. ImageProcessor).
 */
class CopyFileWrapper
{
    /**
     * Create a new instance.
     *
     * @param UploadedFileInterface $uploadedFile The wrapped uploaded file used for validation.
     * @param string $storage The source storage name where the file currently resides.
     * @param string $path The source file path inside the storage.
     */
    public function __construct(
        private UploadedFileInterface $uploadedFile,
        private string $storage,
        private string $path,
    ) {}

    /**
     * Get the wrapped UploadedFileInterface instance.
     *
     * @return UploadedFileInterface The uploaded file used for validation.
     */
    public function uploadedFile(): UploadedFileInterface
    {
        return $this->uploadedFile;
    }

    /**
     * Get the source storage name.
     *
     * @return string The storage name where the file currently resides.
     */
    public function storage(): string
    {
        return $this->storage;
    }

    /**
     * Get the source file path inside the storage.
     *
     * @return string The file path.
     */
    public function path(): string
    {
        return $this->path;
    }
}
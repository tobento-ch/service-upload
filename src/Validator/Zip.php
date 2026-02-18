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

namespace Tobento\Service\Upload\Validator;

use Psr\Http\Message\UploadedFileInterface;
use Tobento\Service\Upload\Exception\UploadedFileException;
use Tobento\Service\Upload\InMemoryUploadedFile;
use ZipArchive;

/**
 * Validates ZIP files and protects against ZIP bombs.
 */
class Zip extends General
{
    /**
     * @var int Maximum allowed total uncompressed size in bytes.
     */
    protected int $maxTotalUncompressedBytes = 50_000_000; // 50 MB default

    /**
     * @var int Maximum allowed number of entries inside the ZIP.
     */
    protected int $maxEntries = 2000;

    /**
     * @var int Maximum allowed compression ratio (uncompressed/compressed).
     */
    protected int $maxCompressionRatio = 200;

    /**
     * @var int Maximum allowed nested ZIP depth.
     */
    protected int $maxDepth = 3;

    /**
     * Returns the file extensions supported by this ZIP validator
     * (lowercase, without dot).
     *
     * @return array<int, string>
     */
    public function supportsExtensions(): array
    {
        return ['zip'];
    }

    /**
     * Validates the uploaded ZIP file.
     *
     * @param UploadedFileInterface $file
     * @return void
     * @throws UploadedFileException
     */
    public function validateUploadedFile(UploadedFileInterface $file): void
    {
        // First run the base validator (extension, mime, size, etc.)
        parent::validateUploadedFile($file);

        $this->validateZipContent($file);
    }

    /**
     * Validates ZIP content without extracting files.
     *
     * @param UploadedFileInterface $file
     * @param int $depth
     * @return void
     * @throws UploadedFileException
     */
    protected function validateZipContent(UploadedFileInterface $file, int $depth = 0): void
    {
        if ($depth > $this->maxDepth) {
            throw new UploadedFileException(
                uploadedFile: $file,
                message: 'ZIP nesting depth exceeds the allowed limit of :limit.',
                parameters: [':limit' => $this->maxDepth]
            );
        }

        $zip = new ZipArchive();

        // Create a real temporary file
        $tmp = tempnam(sys_get_temp_dir(), 'zip_');

        // Write the uploaded file content into it
        file_put_contents($tmp, (string)$file->getStream());

        // Now open the real file
        if ($zip->open($tmp) !== true) {
            unlink($tmp);
            throw new UploadedFileException(
                uploadedFile: $file,
                message: 'Unable to open ZIP file.'
            );
        }

        // Remember to delete the temp file later
        $tempZipPath = $tmp;

        $numFiles = $zip->numFiles;

        if ($numFiles > $this->maxEntries) {
            throw new UploadedFileException(
                uploadedFile: $file,
                message: 'The ZIP archive contains more than :max files.',
                parameters: [':max' => $this->maxEntries]
            );
        }

        $totalUncompressed = 0;

        for ($i = 0; $i < $numFiles; $i++) {
            $stat = $zip->statIndex($i);

            if (!is_array($stat)) {
                continue;
            }

            $name = $stat['name'];
            $size = $stat['size'];
            $compressed = $stat['comp_size'];

            // Directory traversal protection
            if (str_contains($name, '../')) {
                throw new UploadedFileException(
                    uploadedFile: $file,
                    message: 'ZIP contains invalid or unsafe paths.'
                );
            }

            // Accumulate uncompressed size
            $totalUncompressed += $size;

            // Compression ratio check
            if ($compressed > 0) {
                $ratio = $size / $compressed;
                if ($ratio > $this->maxCompressionRatio) {
                    throw new UploadedFileException(
                        uploadedFile: $file,
                        message: 'ZIP compression ratio too high (:ratio > :max).',
                        parameters: [
                            ':ratio' => round($ratio, 2),
                            ':max'   => $this->maxCompressionRatio,
                        ]
                    );
                }
            }

            // Nested ZIP detection
            if (str_ends_with(strtolower($name), '.zip')) {
                $stream = $zip->getStream($name);
                if ($stream !== false) {
                    $nestedContent = stream_get_contents($stream);
                    fclose($stream);

                    $nestedFile = new InMemoryUploadedFile(
                        filename: $name,
                        content: $nestedContent,
                        mediaType: 'application/zip'
                    );

                    $this->validateZipContent($nestedFile, $depth + 1);
                }
            }
        }

        if ($totalUncompressed > $this->maxTotalUncompressedBytes) {
            throw new UploadedFileException(
                uploadedFile: $file,
                message: 'ZIP uncompressed size :size exceeds the allowed maximum of :max.',
                parameters: [
                    ':size' => $totalUncompressed,
                    ':max'  => $this->maxTotalUncompressedBytes,
                ]
            );
        }
        
        $zip->close();
        unlink($tempZipPath);
    }

    /**
     * Returns a new instance with the maximum allowed total
     * uncompressed ZIP size in bytes.
     *
     * @param int $bytes
     * @return static
     */
    public function withMaxTotalUncompressedBytes(int $bytes): static
    {
        $new = clone $this;
        $new->maxTotalUncompressedBytes = $bytes;
        return $new;
    }

    /**
     * Returns a new instance with the maximum allowed number
     * of entries inside the ZIP archive.
     *
     * @param int $entries
     * @return static
     */
    public function withMaxEntries(int $entries): static
    {
        $new = clone $this;
        $new->maxEntries = $entries;
        return $new;
    }

    /**
     * Returns a new instance with the maximum allowed compression
     * ratio (uncompressed size divided by compressed size).
     *
     * @param int $ratio
     * @return static
     */
    public function withMaxCompressionRatio(int $ratio): static
    {
        $new = clone $this;
        $new->maxCompressionRatio = $ratio;
        return $new;
    }

    /**
     * Returns a new instance with the maximum allowed nested ZIP
     * depth to inspect recursively.
     *
     * @param int $depth
     * @return static
     */
    public function withMaxDepth(int $depth): static
    {
        $new = clone $this;
        $new->maxDepth = $depth;
        return $new;
    }
}
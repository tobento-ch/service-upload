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

/**
 * NDJSON validator: ensures each non‑empty line contains valid JSON.
 */
class Ndjson extends General
{
    /**
     * Returns the file extensions handled by this specialized Ndjson validator (lowercase, without dot).
     *
     * @return array<int, string>
     */
    public function supportsExtensions(): array
    {
        return ['ndjson'];
    }
    
    /**
     * Runs base validation and NDJSON content checks.
     *
     * @param UploadedFileInterface $file
     * @return void
     */
    public function validateUploadedFile(UploadedFileInterface $file): void
    {
        // Run base validation first (extension, mime, size, etc.)
        parent::validateUploadedFile($file);
        
        // Run NDJSON-specific validation
        $this->validateNdjsonContent($file);
    }

    /**
     * Validates NDJSON line-by-line.
     *
     * @param UploadedFileInterface $file
     * @return void
     */
    protected function validateNdjsonContent(UploadedFileInterface $file): void
    {
        $stream = $file->getStream();
        $stream->rewind();

        $buffer = '';

        while (!$stream->eof()) {
            $chunk = $stream->read(8192);

            // If read() returns empty string but not EOF, break to avoid infinite loop
            if ($chunk === '') {
                break;
            }

            $buffer .= $chunk;

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);

                if ($line === '') {
                    continue;
                }

                try {
                    $_ = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    throw new UploadedFileException(
                        uploadedFile: $file,
                        message: 'Invalid NDJSON: malformed JSON line.',
                        previous: $e
                    );
                }
            }
        }

        // Validate last line (no trailing newline)
        $line = trim($buffer);
        if ($line !== '') {
            try {
                $_ = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                throw new UploadedFileException(
                    uploadedFile: $file,
                    message: 'Invalid NDJSON: malformed JSON line.',
                    previous: $e
                );
            }
        }
    }

    /**
     * Returns the mime types for the given extension.
     *
     * @param string $extension
     * @param UploadedFileInterface $file
     * @return array<array-key, string>
     * @throws UploadedFileException
     */
    protected function lookupMimeTypes(string $extension, UploadedFileInterface $file): array
    {
        if ($extension === 'ndjson') {
            return [
                'application/x-ndjson',
                'application/ndjson',
                'application/json',
                'text/plain', // optional, some systems send NDJSON as plain text
            ];
        }

        return parent::lookupMimeTypes($extension, $file);
    }
}
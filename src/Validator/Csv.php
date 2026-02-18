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

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Tobento\Service\Upload\Exception\UploadedFileException;

class Csv extends General
{
    protected bool $validateCsvContent = true;
    
    protected string $csvBuffer = '';
    
    /**
     * Returns the file extensions handled by this specialized CSV validator (lowercase, without dot).
     *
     * @return array<int, string>
     */
    public function supportsExtensions(): array
    {
        return ['csv'];
    }
    
    /**
     * Returns a new instance with CSV content validation enabled or disabled.
     *
     * @param bool $validate
     * @return static
     */
    public function withValidateCsvContent(bool $validate): static
    {
        $new = clone $this;
        $new->validateCsvContent = $validate;
        return $new;
    }

    /**
     * Validates the uploaded CSV file.
     *
     * @param UploadedFileInterface $file
     * @return void
     * @throws UploadedFileException
     */
    public function validateUploadedFile(UploadedFileInterface $file): void
    {
        // Run base validation first (extension, mime, size, etc.)
        parent::validateUploadedFile($file);
        
        // Run CSV-specific validation
        if ($this->validateCsvContent) {
            $this->validateCsvContent($file);
        }
    }
    
    /**
     * Performs CSV-specific validation on the uploaded file.
     *
     * @param UploadedFileInterface $file
     * @return void
     * @throws UploadedFileException
     */
    protected function validateCsvContent(UploadedFileInterface $file): void
    {
        if (!$this->validateCsvContent) {
            return;
        }

        // Extract filename + extension
        [$filename, $extension] = $this->extractFilenameAndExtension($file);

        if ($extension !== 'csv') {
            return;
        }

        $stream = $file->getStream();
        $stream->rewind();

        $rowIndex = 0;
        $expectedColumns = null;

        while (($line = $this->readCsvLine($stream)) !== null) {

            if (trim($line) === '') {
                continue;
            }

            // remove BOM on first line
            if ($rowIndex === 0) {
                $line = preg_replace('/^\xEF\xBB\xBF/', '', $line);
            }

            $row = str_getcsv($line, ',', '"', '\\');

            if ($row === false) {
                throw new UploadedFileException(
                    uploadedFile: $file,
                    message: 'The file :name is not a valid CSV file.',
                    parameters: [':name' => $filename]
                );
            }

            // formula injection
            foreach ($row as $cell) {
                $trimmed = ltrim((string)$cell);
                if ($trimmed !== '' && (
                    str_starts_with($trimmed, '=') ||
                    str_starts_with($trimmed, '+') ||
                    str_starts_with($trimmed, '-') ||
                    str_starts_with($trimmed, '@')
                )) {
                    throw new UploadedFileException(
                        uploadedFile: $file,
                        message: 'The CSV file :name contains potentially dangerous spreadsheet formulas.',
                        parameters: [':name' => $filename]
                    );
                }
            }

            // column count consistency
            $colCount = count($row);

            if ($expectedColumns === null) {
                $expectedColumns = $colCount;
            } elseif ($colCount !== $expectedColumns) {
                throw new UploadedFileException(
                    uploadedFile: $file,
                    message: 'The CSV file :name has inconsistent column counts.',
                    parameters: [':name' => $filename]
                );
            }

            $rowIndex++;
        }

        if ($rowIndex === 0) {
            throw new UploadedFileException(
                uploadedFile: $file,
                message: 'The file :name is not a valid CSV file.',
                parameters: [':name' => $filename]
            );
        }
    }

    /**
     * Reads the next CSV line from the stream without loading the full file into memory.
     *
     * Data is read in small chunks and buffered until a newline is found. The line is
     * returned without its line‑ending characters. If the stream ends and buffered data
     * remains, that data is returned as the final line.
     *
     * @param StreamInterface $stream The stream to read from.
     * @return null|string The next CSV line, or null when no more data is available.
     */
    protected function readCsvLine(StreamInterface $stream): null|string
    {
        while (!$stream->eof()) {
            $chunk = $stream->read(1024);

            if ($chunk === '') {
                break;
            }

            $this->csvBuffer .= $chunk;

            if (($pos = strpos($this->csvBuffer, "\n")) !== false) {
                $line = substr($this->csvBuffer, 0, $pos + 1);
                $this->csvBuffer = substr($this->csvBuffer, $pos + 1);
                return rtrim($line, "\r\n");
            }
        }

        if ($this->csvBuffer !== '') {
            $line = $this->csvBuffer;
            $this->csvBuffer = '';
            return rtrim($line, "\r\n");
        }

        return null;
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
        if ($extension === 'csv') {
            return [
                'text/csv',
                'text/plain',
                'application/csv',
                'application/vnd.ms-excel',
            ];
        }

        return parent::lookupMimeTypes($extension, $file);
    }
}
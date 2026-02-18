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

use League\MimeTypeDetection\FinfoMimeTypeDetector;
use League\MimeTypeDetection\GeneratedExtensionToMimeTypeMap;
use Psr\Http\Message\UploadedFileInterface;
use Tobento\Service\Upload\Exception\UploadedFileErrorException;
use Tobento\Service\Upload\Exception\UploadedFileException;
use Tobento\Service\Upload\ValidatorInterface;

/**
 * General validator for uploaded files.
 *
 * This validator performs broad, non‑specialized checks on an UploadedFile
 * instance. It verifies basic structural properties such as non-emptiness and
 * the absence of upload-related errors reported by the file object. The General
 * validator serves as a safe baseline for common upload scenarios where no
 * format-specific validation is required.
 *
 * For more advanced or file-type-specific validation, use one of the
 * specialized validators (e.g. Csv, Pdf, Zip) or combine multiple validators
 * using the Combine validator.
 */
class General implements ValidatorInterface
{
    /**
     * Create a new instance.
     *
     * @param array<array-key, string> $allowedExtensions
     * @param bool $strictFilenameCharacters
     * @param int $maxFilenameLength
     * @param null|int $maxFileSizeInKb Null unlimited
     * @param bool $validateClientMediaType
     * @param bool $validateNotEmpty
     */
    public function __construct(
        protected array $allowedExtensions = ['jpg', 'png', 'gif', 'webp'],
        protected bool $strictFilenameCharacters = true,
        protected int $maxFilenameLength = 255,
        protected null|int $maxFileSizeInKb = null,
        protected bool $validateClientMediaType = false,
        protected bool $validateNotEmpty = false,
    ) {}
    
    /**
     * Returns the file extensions handled by this specialized validator (lowercase, without dot).
     *
     * @return array<int, string>
     */
    public function supportsExtensions(): array
    {
        return [];
    }
    
    /**
     * Validates the uploaded file.
     *
     * @param UploadedFileInterface $file
     * @return void
     * @throws UploadedFileException
     */
    public function validateUploadedFile(UploadedFileInterface $file): void
    {
        // Check for PHP upload errors (UPLOAD_ERR_*)
        $this->validateFileError($file);
        
        // Validate not empty
        if ($this->validateNotEmpty) {
            $this->validateNotEmpty($file);
        }
        
        // Validate filename characters if enabled
        if ($this->strictFilenameCharacters) {
            $this->validateFilenameCharacters($file);
        }
        
        // Validate filename length
        $this->validateFilenameLength($file, $this->maxFilenameLength);
        
        // Extract filename + extension
        [, $extension] = $this->extractFilenameAndExtension($file);
        
        // Validate extension allowed
        $this->validateExtensionAllowed($file, $extension, $this->allowedExtensions);
        
        // Validate mime type
        $detectedMime = $this->detectMimeType($file);
        $allowedMimes = $this->lookupMimeTypes($extension, $file);

        $this->validateMimeTypeConsistency($file, $detectedMime, $allowedMimes);
        
        if ($this->validateClientMediaType) {
            $this->validateClientMediaTypeConsistency($file, $detectedMime, $allowedMimes);
        }

        // Validate file size
        $this->validateFileSize($file, $this->maxFileSizeInKb);
    }
    
    /**
     * Validates the file error.
     *
     * @param UploadedFileInterface $file
     * @return void
     * @throws UploadedFileException
     */
    protected function validateFileError(UploadedFileInterface $file): void
    {
        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new UploadedFileErrorException($file);
        }
    }
    
    /**
     * Validates the filename characters.
     *
     * @param UploadedFileInterface $file
     * @return void
     * @throws UploadedFileException
     */
    protected function validateFilenameCharacters(UploadedFileInterface $file): void
    {
        if (empty($file->getClientFilename())) {
            return;
        }
        
        if ((bool)preg_match('/^[a-zA-Z0-9_\-\. ]+$/u', (string)$file->getClientFilename()) === false) {
            throw new UploadedFileException(
                uploadedFile: $file,
                message: 'The filename :name has invalid characters. Only alphanumeric characters, hyphen, spaces, and periods are allowed.',
                parameters: [':name' => (string)$file->getClientFilename()],
            );
        }
    }
    
    /**
     * Validates the filename length.
     *
     * @param UploadedFileInterface $file
     * @param int $maxFilenameLength
     * @return void
     * @throws UploadedFileException
     */
    protected function validateFilenameLength(UploadedFileInterface $file, int $maxFilenameLength): void
    {
        if (mb_strlen((string)$file->getClientFilename()) > $maxFilenameLength) {
            throw new UploadedFileException(
                uploadedFile: $file,
                message: 'The filename :name must have at most :num characters.',
                parameters: [
                    ':name' => (string)$file->getClientFilename(),
                    ':num' => $maxFilenameLength,
                ],
            );
        }
    }
    
    /**
     * Validates that the file extension is allowed.
     *
     * @param UploadedFileInterface $file
     * @param string $extension
     * @param array<array-key, string> $allowedExtensions
     * @return void
     * @throws UploadedFileException
     */
    protected function validateExtensionAllowed(
        UploadedFileInterface $file,
        string $extension,
        array $allowedExtensions
    ): void {
        if (!in_array($extension, $allowedExtensions, true)) {
            throw new UploadedFileException(
                uploadedFile: $file,
                message: 'The extension :extension of the file :name is disallowed. Allowed extensions are :extensions.',
                parameters: [
                    ':extension' => $extension,
                    ':name' => (string)$file->getClientFilename(),
                    ':extensions' => implode(',', $allowedExtensions),
                ],
            );
        }
    }
    
    /**
     * Validates that the detected mime type matches the allowed mime types.
     *
     * @param UploadedFileInterface $file
     * @param string $detectedMimeType
     * @param array<array-key, string> $allowedMimeTypes
     * @return void
     * @throws UploadedFileException
     */
    protected function validateMimeTypeConsistency(
        UploadedFileInterface $file,
        string $detectedMimeType,
        array $allowedMimeTypes
    ): void {
        if (!in_array($detectedMimeType, $allowedMimeTypes, true)) {
            throw new UploadedFileException(
                uploadedFile: $file,
                message: 'The mime type :type of the file :name is invalid. Allowed mime types are :types.',
                parameters: [
                    ':type' => $detectedMimeType,
                    ':name' => (string)$file->getClientFilename(),
                    ':types' => implode(',', $allowedMimeTypes),
                ],
            );
        }
    }
    
    /**
     * Validates that the client media type is consistent with the detected mime type.
     *
     * @param UploadedFileInterface $file
     * @param string $detectedMimeType
     * @param array<array-key, string> $allowedMimeTypes
     * @return void
     * @throws UploadedFileException
     */
    protected function validateClientMediaTypeConsistency(
        UploadedFileInterface $file,
        string $detectedMimeType,
        array $allowedMimeTypes
    ): void {
        $clientMediaType = $file->getClientMediaType();

        if (!is_string($clientMediaType)) {
            return;
        }

        if ($detectedMimeType !== $clientMediaType) {
            throw new UploadedFileException(
                uploadedFile: $file,
                message: 'The mime type :type of the file :name is invalid. Allowed mime types are :types.',
                parameters: [
                    ':type' => $clientMediaType,
                    ':name' => (string)$file->getClientFilename(),
                    ':types' => implode(',', $allowedMimeTypes),
                ],
            );
        }
    }
    
    /**
     * Validates the file size.
     *
     * @param UploadedFileInterface $file
     * @param null|int $maxFileSizeInKb Null unlimited
     * @return void
     * @throws UploadedFileException
     */
    protected function validateFileSize(UploadedFileInterface $file, null|int $maxFileSizeInKb): void
    {
        if (is_null($maxFileSizeInKb) || is_null($file->getSize())) {
            return;
        }
        
        $fileSizeInKb = $file->getSize() / 1024;
        
        if ($fileSizeInKb > $maxFileSizeInKb) {
            throw new UploadedFileException(
                uploadedFile: $file,
                message: 'The file :name exceeded the max upload size of :num KB.',
                parameters: [':name' => (string)$file->getClientFilename(), ':num' => $maxFileSizeInKb],
            );
        }
    }
    
    /**
     * Validates that the uploaded file is not empty.
     *
     * @param UploadedFileInterface $file
     * @return void
     * @throws UploadedFileException
     */
    protected function validateNotEmpty(UploadedFileInterface $file): void
    {
        $size = $file->getSize();

        // getSize() may return null for some streams, so treat null as empty
        if ($size === null || $size === 0) {
            throw new UploadedFileException(
                uploadedFile: $file,
                message: 'The file :name is empty.',
                parameters: [
                    ':name' => (string)$file->getClientFilename(),
                ],
            );
        }
    }

    /**
     * Returns the detected mime type for the given file.
     *
     * @param UploadedFileInterface $file
     * @return string
     * @throws UploadedFileException
     */
    protected function detectMimeType(UploadedFileInterface $file): string
    {
        $detector = new FinfoMimeTypeDetector();
        $mimeType = $detector->detectMimeTypeFromBuffer((string)$file->getStream());

        if (is_null($mimeType)) {
            throw new UploadedFileException(
                uploadedFile: $file,
                message: 'Unable to detect the mime type for the file :name.',
                parameters: [':name' => (string)$file->getClientFilename()],
            );
        }
        
        return $mimeType;
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
        $map = new GeneratedExtensionToMimeTypeMap();
        $mimeType = $map->lookupMimeType($extension);

        if (is_null($mimeType)) {
            throw new UploadedFileException(
                uploadedFile: $file,
                message: 'Unable to determine the mime types for the extension :extension.',
                parameters: [':extension' => $extension],
            );
        }
        
        return [$mimeType];
    }
    
    /**
     * Extracts the filename and extension from the uploaded file.
     *
     * @param UploadedFileInterface $file
     * @return array{string, string} [filename, extension]
     */
    protected function extractFilenameAndExtension(UploadedFileInterface $file): array
    {
        $filename = (string)$file->getClientFilename();
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return [$filename, $extension];
    }
}
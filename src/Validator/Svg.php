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

use enshrined\svgSanitize\Sanitizer;
use Psr\Http\Message\UploadedFileInterface;
use Tobento\Service\Upload\Exception\UploadedFileException;

/**
 * Validates uploaded SVG files by extending the base upload validator
 * with SVG-specific security checks using the enshrined/svg-sanitize library.
 */
class Svg extends General
{
    /**
     * Create a new instance.
     *
     * @param Sanitizer $sanitizer Optional custom sanitizer instance.
     * @param array<array-key, string> $allowedExtensions
     * @param bool $strictFilenameCharacters
     * @param int $maxFilenameLength
     * @param null|int $maxFileSizeInKb Null unlimited
     * @param bool $validateClientMediaType
     * @param bool $validateNotEmpty
     */
    public function __construct(
        protected Sanitizer $sanitizer = new Sanitizer(),
        protected array $allowedExtensions = ['jpg', 'png', 'gif', 'webp'],
        protected bool $strictFilenameCharacters = true,
        protected int $maxFilenameLength = 255,
        protected null|int $maxFileSizeInKb = null,
        protected bool $validateClientMediaType = false,
        protected bool $validateNotEmpty = false,
    ) {}

    /**
     * Returns the file extensions handled by this specialized SVG validator.
     *
     * @return array<int, string>
     */
    public function supportsExtensions(): array
    {
        return ['svg'];
    }

    /**
     * Validate the uploaded SVG file.
     *
     * @param UploadedFileInterface $file
     * @return void
     * @throws UploadedFileException If the SVG is invalid or unsafe.
     */
    public function validateUploadedFile(UploadedFileInterface $file): void
    {
        // Run base validation first (extension, mime, size, etc.)
        parent::validateUploadedFile($file);

        // Run SVG-specific validation
        $this->validateSvgContent($file);
    }

    /**
     * Perform structural and security checks on the SVG content.
     *
     * @param UploadedFileInterface $file
     * @return void
     * @throws UploadedFileException
     */
    protected function validateSvgContent(UploadedFileInterface $file): void
    {
        $svg = (string)$file->getStream();

        // Attempt sanitization
        $sanitized = $this->sanitizer->sanitize($svg);

        // If sanitize() returns false, invalid XML or fatal issue
        if (!is_string($sanitized)) {
            throw new UploadedFileException(
                uploadedFile: $file,
                message: 'SVG file is invalid or contains unsafe XML.',
            );
        }

        // Check XML issues
        $issues = $this->sanitizer->getXmlIssues();

        if (!empty($issues)) {
            $messages = array_map(function ($issue) {
                if (is_array($issue) && isset($issue['message'])) {
                    return $issue['message'];
                }
                return (string)$issue;
            }, $issues);

            throw new UploadedFileException(
                uploadedFile: $file,
                message: 'SVG contains XML issues: :issues.',
                parameters: [':issues' => implode(', ', $messages)],
            );
        }

        // If we reach here, SVG is safe (sanitized or not)
    }
}
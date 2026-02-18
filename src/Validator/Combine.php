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
use Tobento\Service\Upload\ValidatorInterface;

/**
 * A validator that delegates file validation to the first validator
 * that supports the file's extension.
 */
class Combine implements ValidatorInterface
{
    /**
     * @var array<int, ValidatorInterface>
     */
    protected array $validators = [];
    
    /**
     * @param ValidatorInterface ...$validators The validators to dispatch to, in order of priority.
     */
    public function __construct(
        ValidatorInterface ...$validators
    ) {
        $this->validators = $validators;
    }

    /**
     * This validator does not handle extensions directly.
     * It delegates to its inner validators.
     *
     * @return array<int, string>
     */
    public function supportsExtensions(): array
    {
        return [];
    }

    /**
     * Validates the uploaded file by selecting the first validator
     * that supports the file's extension. If none match, an exception
     * is thrown.
     *
     * @param UploadedFileInterface $file
     * @return void
     * @throws UploadedFileException
     */
    public function validateUploadedFile(UploadedFileInterface $file): void
    {
        $filename = (string)$file->getClientFilename();
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        foreach ($this->validators as $validator) {
            $supported = $validator->supportsExtensions();

            // Empty list means: validator accepts all extensions
            if (empty($supported) || in_array($extension, $supported, true)) {
                $validator->validateUploadedFile($file);
                return;
            }
        }

        throw new UploadedFileException(
            uploadedFile: $file,
            message: 'No validator available for file :name.',
            parameters: [':name' => $filename]
        );
    }
}
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
use Tobento\Service\Upload\Exception\UploadedFileException;

/**
 * ValidatorInterface
 */
interface ValidatorInterface
{
    /**
     * Returns the file extensions handled by the specialized validator (lowercase, without dot).
     *
     * @return array<int, string>
     */
    public function supportsExtensions(): array;
    
    /**
     * Validates the uploaded file.
     *
     * @param UploadedFileInterface $file
     * @return void
     * @throws UploadedFileException
     */
    public function validateUploadedFile(UploadedFileInterface $file): void;
}
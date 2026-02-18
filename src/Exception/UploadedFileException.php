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
 
namespace Tobento\Service\Upload\Exception;

use Psr\Http\Message\UploadedFileInterface;
use Throwable;

/**
 * UploadedFileException
 */
class UploadedFileException extends UploadException
{
    /**
     * Create a new UploadedFileException.
     *
     * @param UploadedFileInterface $uploadedFile
     * @param string $message The message
     * @param array $parameters Any message parameters
     * @param int $code
     * @param null|Throwable $previous
     */
    public function __construct(
        protected UploadedFileInterface $uploadedFile,
        string $message = '',
        protected array $parameters = [],
        int $code = 0,
        null|Throwable $previous = null
    ) {
        parent::__construct($message, $parameters, $code, $previous);
    }
    
    /**
     * Returns the uploaded file.
     *
     * @return UploadedFileInterface
     */
    public function uploadedFile(): UploadedFileInterface
    {
        return $this->uploadedFile;
    }
}
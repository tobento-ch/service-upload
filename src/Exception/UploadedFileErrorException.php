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

/**
 * UploadedFileErrorException
 */
class UploadedFileErrorException extends UploadedFileException
{
    /**
     * Create a new UploadedFileException.
     *
     * @param UploadedFileInterface $uploadedFile
     */
    public function __construct(
        protected UploadedFileInterface $uploadedFile,
    ) {
        [$message, $parameters] = $this->toErrorMessage($uploadedFile);
        parent::__construct($uploadedFile, $message, $parameters);
    }
    
    /**
     * Returns error message and parameters depeneding on the error code.
     *
     * @param UploadedFileInterface $uploadedFile
     * @return array
     */
    protected function toErrorMessage(UploadedFileInterface $uploadedFile): array
    {
        $filename = (string)$uploadedFile->getClientFilename();
        
        switch ($uploadedFile->getError()) {
            case UPLOAD_ERR_INI_SIZE:
                return [
                    'The uploaded file :name exceeds the upload_max_filesize directive in php.ini!',
                    [':name' => $filename],
                ];
            case UPLOAD_ERR_FORM_SIZE:
                return [
                    'The uploaded file :name exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.',
                    [':name' => $filename],
                ];
            case UPLOAD_ERR_PARTIAL:
                return [
                    'The uploaded file :name was only partially uploaded.',
                    [':name' => $filename],
                ];
            case UPLOAD_ERR_NO_FILE:
                return [
                    'No file was uploaded.',
                    [],
                ];
            case UPLOAD_ERR_NO_TMP_DIR:
                return [
                    'Missing a temporary folder!',
                    [],
                ];
            case UPLOAD_ERR_CANT_WRITE:
                return [
                    'Failed to write file to disk.',
                    [],
                ];
            case UPLOAD_ERR_EXTENSION:
                return [
                    'File upload stopped by extension.',
                    [],
                ];
            default:
                return [
                    'Unknown upload error.',
                    [],
                ];
        }
    }
}
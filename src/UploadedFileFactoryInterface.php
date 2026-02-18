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
use Tobento\Service\Upload\Exception\CreateUploadedFileException;
use Tobento\Service\FileStorage\FileInterface;

/**
 * UploadedFileFactoryInterface
 */
interface UploadedFileFactoryInterface
{
    /**
     * Create uploaded file from the given remote url.
     *
     * @param string $url
     * @return UploadedFileInterface
     * @throws CreateUploadedFileException
     */
    public function createFromRemoteUrl(string $url): UploadedFileInterface;
    
    /**
     * Create uploaded file from the given storage file.
     *
     * @param FileInterface $file
     * @return UploadedFileInterface
     * @throws CreateUploadedFileException
     */
    public function createFromStorageFile(FileInterface $file): UploadedFileInterface;
}
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

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Tobento\Service\Upload\Exception\WriteException;

interface FileStorageWriterInterface
{
    /**
     * Write file from stream to the storage.
     *
     * @param StreamInterface $stream
     * @param string $filename
     * @param string $folderPath
     * @return WriteResponseInterface
     * @throws WriteException
     */
    public function writeFromStream(StreamInterface $stream, string $filename, string $folderPath): WriteResponseInterface;
    
    /**
     * Write the uploaded file to the storage.
     *
     * @param UploadedFileInterface $file
     * @param string $folderPath
     * @return WriteResponseInterface
     * @throws WriteException
     */
    public function writeUploadedFile(UploadedFileInterface $file, string $folderPath): WriteResponseInterface;
    
    /**
     * Copy an existing file inside the storage into the given folder.
     *
     * @param string $path The existing file path inside the same storage.
     * @param string $folderPath The folder where the file should be copied to.
     * @return WriteResponseInterface
     * @throws WriteException
     */
    public function copyFile(string $path, string $folderPath): WriteResponseInterface;
}
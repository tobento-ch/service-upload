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

use Closure;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Tobento\Service\Upload\Exception\WriteException;
use Tobento\Service\Upload\Writer\WriterInterface;
use Tobento\Service\FileStorage\FileWriteException;
use Tobento\Service\FileStorage\StorageInterface;
use Tobento\Service\Message\MessagesInterface;

/**
 * Writing files to the given storage.
 */
class FileStorageWriter implements FileStorageWriterInterface
{
    public const RENAME = 'rename';
    public const ALNUM = 'alnum';
    public const KEEP = 'keep';
    public const OVERWRITE = 'overwrite';
    public const DENY = 'deny';
    
    /**
     * Create a new FileWriter.
     *
     * @param StorageInterface $storage
     * @param string|Closure $filenames E.g. 'rename', 'alnum' or 'keep'
     * @param string $duplicates E.g. 'rename', 'overwrite' or 'deny'
     * @param string|Closure $folders E.g. 'alnum' or 'keep'
     * @param int $folderDepthLimit
     * @param array<array-key, WriterInterface> $writers
     */
    public function __construct(
        protected StorageInterface $storage,
        protected string|Closure $filenames = 'alnum',
        protected string $duplicates = 'rename',
        protected string|Closure $folders = 'alnum',
        protected int $folderDepthLimit = 5,
        protected array $writers = [],
    ) {}
    
    /**
     * Write file from stream to the storage.
     *
     * @param StreamInterface $stream
     * @param string $filename
     * @param string $folderPath
     * @return WriteResponseInterface
     * @throws WriteException
     */
    public function writeFromStream(StreamInterface $stream, string $filename, string $folderPath): WriteResponseInterface
    {
        // verify folder:
        $folderPath = $this->verifyFolderPath($folderPath);
        
        // verify filename:
        $originalFilename = $filename;
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $filename = pathinfo($filename, PATHINFO_FILENAME);
        $filename = $this->verifyFilename($filename);
        
        // build file path for storage:
        $path = $this->buildPath($folderPath, $filename, $extension);
        
        // handle writers:
        foreach($this->writers as $writer) {
            if (!$writer instanceof WriterInterface) {
                continue;
            }
            
            $writeResponse = $writer->write(
                path: $path,
                stream: $stream,
                originalFilename: $originalFilename,
            );
            
            if (!is_null($writeResponse)) {
                return $this->writeToStorage($writeResponse);
            }
        }
        
        return $this->writeToStorage(new WriteResponse(
            path: $path,
            content: $stream,
            originalFilename: $originalFilename,
        ));
    }
    
    /**
     * Write the uploaded file to the storage.
     *
     * @param UploadedFileInterface $file
     * @param string $folderPath
     * @return WriteResponseInterface
     * @throws WriteException
     */
    public function writeUploadedFile(UploadedFileInterface $file, string $folderPath): WriteResponseInterface
    {
        return $this->writeFromStream(
            stream: $file->getStream(),
            filename: (string)$file->getClientFilename(),
            folderPath: $folderPath,
        );
    }
    
    /**
     * Copy an existing file inside the storage into the given folder.
     *
     * @param string $path The existing file path inside the same storage.
     * @param string $folderPath The folder where the file should be copied to.
     * @return WriteResponseInterface
     * @throws WriteException
     */
    public function copyFile(string $path, string $folderPath): WriteResponseInterface
    {
        // verify folder:
        $folderPath = $this->verifyFolderPath($folderPath);

        // ensure source exists:
        if (! $this->storage->exists($path)) {
            throw new WriteException(
                message: 'The source file :path does not exist in storage :storage.',
                parameters: [':path' => $path, ':storage' => $this->storage->name()],
            );
        }

        // extract filename + extension:
        $basename = basename($path);
        $extension = pathinfo($basename, PATHINFO_EXTENSION);
        $filename = pathinfo($basename, PATHINFO_FILENAME);

        // apply filename rules:
        $verifiedFilename = $this->verifyFilename($filename);

        // build target path:
        $targetPath = $this->buildPath($folderPath, $verifiedFilename, $extension);

        // apply duplicate rules:
        $targetPath = $this->verifyFileDuplicate($targetPath);

        try {
            // perform storage-level copy:
            $this->storage->copy(from: $path, to: $targetPath);
        } catch (FileWriteException $e) {
            throw new WriteException(
                message: 'Failed to copy the file :source to :target in storage :storage.',
                parameters: [
                    ':source' => $path,
                    ':target' => $targetPath,
                    ':storage' => $this->storage->name(),
                ],
                code: $e->getCode(),
                previous: $e,
            );
        }

        // return WriteResponse consistent with writeUploadedFile():
        return new WriteResponse(
            path: $targetPath,
            content: '', // no stream needed for copy
            originalFilename: $basename,
        );
    }
    
    /**
     * Verify the folder path.
     *
     * @param string $path
     * @return string The verified folder path.
     * @throws WriteException
     */
    protected function verifyFolderPath(string $path): string
    {
        if (count(explode('/', $path)) > $this->folderDepthLimit) {
            throw new WriteException(
                message: 'Allowed folder depth of :num exceeded for the folder :path.',
                parameters: [':num' => $this->folderDepthLimit, ':path' => $path],
            );
        }
        
        if ($this->folders instanceof Closure) {
            return call_user_func_array($this->folders, [$path]);
        }
        
        if ($this->folders === static::ALNUM) {
            return (string)preg_replace('/[^A-Za-z0-9_\-\/]/', '-', trim($path));
        }

        return $path;
    }
    
    /**
     * Verify the filename.
     *
     * @param string $filename
     * @return string The verified filename.
     */
    protected function verifyFilename(string $filename): string
    {
        if ($filename === '') {
            return $filename;
        }
        
        if ($this->filenames instanceof Closure) {
            return call_user_func_array($this->filenames, [$filename]);
        }
        
        if ($this->filenames === static::RENAME) {
            return bin2hex(random_bytes(20));
        }
        
        if ($this->filenames === static::ALNUM) {
            return (string)preg_replace('/[^A-Za-z0-9_\-]/', '-', trim($filename));
        }
        
        // static::KEEP original filename may be dangerous on upload!
        return $filename;
    }

    /**
     * Verify the file duplicate.
     *
     * @param string $path
     * @return string
     */
    protected function verifyFileDuplicate(string $path): string
    {
        if (! $this->storage->exists($path)) {
            return $path;
        }
        
        if ($this->duplicates === static::OVERWRITE) {
            return $path;
        }

        if ($this->duplicates === static::RENAME) {
            $dirname = str_replace(['\\'], '/', pathinfo($path, PATHINFO_DIRNAME));
            $filename = pathinfo($path, PATHINFO_FILENAME);
            $extension = pathinfo($path, PATHINFO_EXTENSION);
            
            if ($dirname === '.') {
                $dirname = '';
            }
            
            // Detect existing numeric suffix: file-2, file-10, etc.
            if (preg_match('/^(.*)-(\d+)$/', $filename, $matches)) {
                $base = $matches[1];
                $i = (int)$matches[2];
            } else {
                $base = $filename;
                $i = 1;
            }

            // Try incrementing suffix
            do {
                $newFilename = $base . '-' . $i++;
                $newPath = $this->buildPath($dirname, $newFilename, $extension);
            } while ($this->storage->exists($newPath));

            return $newPath;
        }
        
        throw new WriteException(
            message: 'Not allowed to overwrite the file :path.',
            parameters: [':path' => $path],
        );
    }
    
    /**
     * Write the given write response to the storage.
     *
     * @param WriteResponseInterface $writeResponse
     * @return WriteResponseInterface
     * @throws WriteException
     */
    protected function writeToStorage(
        WriteResponseInterface $writeResponse
    ): WriteResponseInterface {
        // verify file duplicate:
        $path = $this->verifyFileDuplicate($writeResponse->path());
        
        try {
            $this->storage->write(
                path: $path,
                content: $writeResponse->content()
            );
        } catch (FileWriteException $e) {
            throw new WriteException(
                message: 'Failed to write the file :path to the file storage :storage.',
                parameters: [':path' => $path, ':storage' => $this->storage->name()],
                code: $e->getCode(),
                previous: $e,
            );
        }
        
        return new WriteResponse(
            path: $path,
            content: $writeResponse->content(),
            originalFilename: $writeResponse->originalFilename(),
            messages: $writeResponse->messages(),
        );
    }
    
    /**
     * Returns the build path.
     *
     * @param string $folderPath
     * @param string $filename
     * @param string $extension
     * @return string The build path
     */
    protected function buildPath(string $folderPath, string $filename, string $extension): string
    {
        $basename = $extension ? $filename.'.'.$extension : $filename;
        
        return  $folderPath ? $folderPath.'/'.$basename : $basename;
    }
}
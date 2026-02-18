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

namespace Tobento\Service\Upload\Test;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Tobento\Service\FileStorage\Flysystem;
use Tobento\Service\FileStorage\StorageInterface;
use Tobento\Service\FileStorage\Storages;
use Tobento\Service\FileStorage\StoragesInterface;
use Tobento\Service\Filesystem\Dir;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

final class Factory
{
    public static function createStreamFactory(): StreamFactoryInterface
    {
        return new Psr17Factory();
    }
    
    public static function createUploadedFileFactory(): UploadedFileFactoryInterface
    {
        return new Psr17Factory();
    }
    
    public static function createFileStorage(
        string $name,
        null|string $folder = null,
        bool $withPublicUrl = true,
        string $type = 'public',
    ): StorageInterface {

        // Normalize folder
        $folder = $folder ?: $name;

        // Build path
        $root = __DIR__ . '/tmp/file-storage/' . $folder;

        // Ensure directory exists
        if (!is_dir($root)) {
            mkdir($root, 0777, true);
        }

        // Build config
        $config = [];

        if ($withPublicUrl) {
            $config['public_url'] = 'https://www.example.com/files/' . $folder;
        }

        // Create Flysystem filesystem
        $filesystem = new Filesystem(
            adapter: new LocalFilesystemAdapter($root),
            config: $config,
        );

        return new Flysystem\Storage(
            name: $name,
            flysystem: $filesystem,
            fileFactory: new Flysystem\FileFactory(
                flysystem: $filesystem,
                streamFactory: new Psr17Factory()
            ),
            type: $type,
        );
    }

    public static function createFileStorages(array $names = [], bool $withPublicUrl = true): StoragesInterface
    {
        $storages = new Storages();

        foreach ($names as $name) {
            $storages->add(static::createFileStorage(name: $name, withPublicUrl: $withPublicUrl));
        }

        return $storages;
    }
    
    /**
     * Delete the entire test file-storage directory.
     */
    public static function cleanupFileStorage(): void
    {
        new Dir()->delete(__DIR__.'/tmp/file-storage/');
    }
}
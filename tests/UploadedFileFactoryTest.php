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
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Symfony\Component\HttpClient\Psr18Client;
use Tobento\Service\Upload\Exception\CreateUploadedFileException;
use Tobento\Service\Upload\Test\FileStorageFactory;
use Tobento\Service\Upload\UploadedFileFactory;
use Tobento\Service\Upload\UploadedFileFactoryInterface;

class UploadedFileFactoryTest extends TestCase
{
    protected function tearDown(): void
    {
        Factory::cleanupFileStorage();
    }
    
    public function testThatImplementsInterface()
    {
        $factory = new UploadedFileFactory(
            uploadedFileFactory: new Psr17Factory(),
            streamFactory: new Psr17Factory(),
            client: new Psr18Client(),
            requestFactory: new Psr17Factory(),
        );
        
        $this->assertInstanceof(UploadedFileFactoryInterface::class, $factory);
    }
    
    public function testCreateFromRemoteUrl()
    {
        $factory = new UploadedFileFactory(
            uploadedFileFactory: new Psr17Factory(),
            streamFactory: new Psr17Factory(),
            client: new Psr18Client(),
            requestFactory: new Psr17Factory(),
        );
        
        $uploadedFile = $factory->createFromRemoteUrl(
            url: 'https://docs.tobento.ch/favicon.ico'
        );
        
        $this->assertInstanceof(UploadedFileInterface::class, $uploadedFile);
        $this->assertInstanceof(StreamInterface::class, $uploadedFile->getStream());
        $this->assertSame(3262, $uploadedFile->getSize());
        $this->assertSame('favicon.ico', $uploadedFile->getClientFilename());
        $this->assertSame(null, $uploadedFile->getClientMediaType());
    }
    
    public function testCreateFromRemoteUrlThrowsCreateUploadedFileExceptionIfNotFound()
    {
        $this->expectException(CreateUploadedFileException::class);
        $this->expectExceptionMessage('Creating uploaded file from remote file :url failed as not found.');
        
        $factory = new UploadedFileFactory(
            uploadedFileFactory: new Psr17Factory(),
            streamFactory: new Psr17Factory(),
            client: new Psr18Client(),
            requestFactory: new Psr17Factory(),
        );
        
        $uploadedFile = $factory->createFromRemoteUrl(
            url: 'https://docs.tobento.ch/abcdefgh'
        );
    }
    
    public function testCreateFromStorageFile()
    {
        $storage = Factory::createFileStorage(name: 'upload-factory');
        $storage->write(path: 'file.txt', content: 'content');
        
        $factory = new UploadedFileFactory(
            uploadedFileFactory: new Psr17Factory(),
            streamFactory: new Psr17Factory(),
            client: new Psr18Client(),
            requestFactory: new Psr17Factory(),
        );
        
        $uploadedFile = $factory->createFromStorageFile(
            file: $storage->with('stream')->file('file.txt')
        );
        
        $storage->delete(path: 'file.txt');
        
        $this->assertInstanceof(UploadedFileInterface::class, $uploadedFile);
        $this->assertInstanceof(StreamInterface::class, $uploadedFile->getStream());
        $this->assertSame(7, $uploadedFile->getSize());
        $this->assertSame('file.txt', $uploadedFile->getClientFilename());
        $this->assertSame(null, $uploadedFile->getClientMediaType());
    }
    
    public function testCreateFromStorageFileThrowsCreateUploadedFileExceptionIfNoStream()
    {
        $this->expectException(CreateUploadedFileException::class);
        $this->expectExceptionMessage('Writing storage file :file failed as no stream available.');
        
        $storage = Factory::createFileStorage(name: 'upload-factory');
        $storage->write(path: 'file.txt', content: 'content');
        
        $factory = new UploadedFileFactory(
            uploadedFileFactory: new Psr17Factory(),
            streamFactory: new Psr17Factory(),
            client: new Psr18Client(),
            requestFactory: new Psr17Factory(),
        );
        
        $uploadedFile = $factory->createFromStorageFile(
            file: $storage->file('file.txt')
        );
        
        $storage->delete(path: 'file.txt');
    }
    
    public function testCreateFromString()
    {
        $factory = new UploadedFileFactory(
            uploadedFileFactory: new Psr17Factory(),
            streamFactory: new Psr17Factory(),
            client: new Psr18Client(),
            requestFactory: new Psr17Factory(),
        );

        $uploadedFile = $factory->createFromString(
            content: 'hello world',
            clientFilename: 'imported.bin',
            clientMediaType: 'application/octet-stream',
        );

        $this->assertInstanceof(UploadedFileInterface::class, $uploadedFile);
        $this->assertInstanceof(StreamInterface::class, $uploadedFile->getStream());
        $this->assertSame(11, $uploadedFile->getSize());
        $this->assertSame('imported.bin', $uploadedFile->getClientFilename());
        $this->assertSame('application/octet-stream', $uploadedFile->getClientMediaType());
    }

    public function testCreateFromDataUriBase64()
    {
        $factory = new UploadedFileFactory(
            uploadedFileFactory: new Psr17Factory(),
            streamFactory: new Psr17Factory(),
            client: new Psr18Client(),
            requestFactory: new Psr17Factory(),
        );

        $dataUri = 'data:text/plain;base64,' . base64_encode('hello');

        $uploadedFile = $factory->createFromDataUri($dataUri);

        $this->assertInstanceof(UploadedFileInterface::class, $uploadedFile);
        $this->assertInstanceof(StreamInterface::class, $uploadedFile->getStream());
        $this->assertSame(5, $uploadedFile->getSize());
        $this->assertSame('imported.bin', $uploadedFile->getClientFilename());
        $this->assertSame('text/plain', $uploadedFile->getClientMediaType());
        $this->assertSame('hello', (string)$uploadedFile->getStream());
    }

    public function testCreateFromDataUriUrlEncoded()
    {
        $factory = new UploadedFileFactory(
            uploadedFileFactory: new Psr17Factory(),
            streamFactory: new Psr17Factory(),
            client: new Psr18Client(),
            requestFactory: new Psr17Factory(),
        );

        $dataUri = 'data:text/plain,Hello%20World';

        $uploadedFile = $factory->createFromDataUri($dataUri);

        $this->assertInstanceof(UploadedFileInterface::class, $uploadedFile);
        $this->assertInstanceof(StreamInterface::class, $uploadedFile->getStream());
        $this->assertSame(11, $uploadedFile->getSize());
        $this->assertSame('imported.bin', $uploadedFile->getClientFilename());
        $this->assertSame('text/plain', $uploadedFile->getClientMediaType());
        $this->assertSame('Hello World', (string)$uploadedFile->getStream());
    }

    public function testCreateFromDataUriThrowsExceptionOnInvalidUri()
    {
        $this->expectException(CreateUploadedFileException::class);
        $this->expectExceptionMessage('Invalid data URI');

        $factory = new UploadedFileFactory(
            uploadedFileFactory: new Psr17Factory(),
            streamFactory: new Psr17Factory(),
            client: new Psr18Client(),
            requestFactory: new Psr17Factory(),
        );

        $factory->createFromDataUri('not-a-data-uri');
    }

    public function testCreateFromDataUriThrowsExceptionOnMalformedUri()
    {
        $this->expectException(CreateUploadedFileException::class);
        $this->expectExceptionMessage('Malformed data URI');

        $factory = new UploadedFileFactory(
            uploadedFileFactory: new Psr17Factory(),
            streamFactory: new Psr17Factory(),
            client: new Psr18Client(),
            requestFactory: new Psr17Factory(),
        );

        // missing comma separator
        $factory->createFromDataUri('data:text/plain;base64');
    }
}
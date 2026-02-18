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

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Tobento\Service\Upload\InMemoryStream;
use Tobento\Service\Upload\InMemoryUploadedFile;

class InMemoryUploadedFileTest extends TestCase
{
    public function testImplementsUploadedFileInterface()
    {
        $file = new InMemoryUploadedFile('test.txt', 'hello');
        $this->assertInstanceOf(UploadedFileInterface::class, $file);
    }

    public function testGetClientFilename()
    {
        $file = new InMemoryUploadedFile('example.txt', 'content');
        $this->assertSame('example.txt', $file->getClientFilename());
    }

    public function testGetClientMediaType()
    {
        $file = new InMemoryUploadedFile('file.bin', 'xxx', 'application/test');
        $this->assertSame('application/test', $file->getClientMediaType());
    }
    
    public function testSetSize()
    {
        $file = new InMemoryUploadedFile('a.txt', 'hello');

        $this->assertSame(5, $file->getSize());

        $file->setSize(10);

        $this->assertSame(10 * 1024, $file->getSize());
    }

    public function testGetSize()
    {
        $file = new InMemoryUploadedFile('a.txt', '12345');
        $this->assertSame(5, $file->getSize());
    }

    public function testSetError()
    {
        $file = new InMemoryUploadedFile('a.txt', 'hello');

        $this->assertSame(UPLOAD_ERR_OK, $file->getError());

        $file->setError(UPLOAD_ERR_NO_FILE);

        $this->assertSame(UPLOAD_ERR_NO_FILE, $file->getError());
    }
    
    public function testGetErrorAlwaysOk()
    {
        $file = new InMemoryUploadedFile('a.txt', 'data');
        $this->assertSame(\UPLOAD_ERR_OK, $file->getError());
    }

    public function testGetStreamReturnsStreamInterface()
    {
        $file = new InMemoryUploadedFile('a.txt', 'hello');
        $stream = $file->getStream();

        $this->assertInstanceOf(StreamInterface::class, $stream);
        $this->assertInstanceOf(InMemoryStream::class, $stream);
        $this->assertSame('hello', (string)$stream);
    }

    public function testMoveToWritesFile()
    {
        $file = new InMemoryUploadedFile('a.txt', 'hello world');

        $tmp = tempnam(sys_get_temp_dir(), 'inmem_');
        $file->moveTo($tmp);

        $this->assertSame('hello world', file_get_contents($tmp));
        unlink($tmp);
    }
}
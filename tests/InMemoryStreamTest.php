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
use Tobento\Service\Upload\InMemoryStream;

class InMemoryStreamTest extends TestCase
{
    public function testImplementsStreamInterface()
    {
        $stream = new InMemoryStream('hello');
        $this->assertInstanceOf(StreamInterface::class, $stream);
    }

    public function testToStringReturnsFullContent()
    {
        $stream = new InMemoryStream('hello world');
        $this->assertSame('hello world', (string)$stream);
    }

    public function testGetSize()
    {
        $stream = new InMemoryStream('12345');
        $this->assertSame(5, $stream->getSize());
    }

    public function testTellReturnsCurrentPosition()
    {
        $stream = new InMemoryStream('abcdef');
        $stream->read(3);
        $this->assertSame(3, $stream->tell());
    }

    public function testEof()
    {
        $stream = new InMemoryStream('abc');

        $this->assertFalse($stream->eof());

        $stream->read(3);
        $this->assertFalse($stream->eof(), 'Cursor at end is not EOF yet');

        $stream->read(1); // read past end
        $this->assertTrue($stream->eof());
    }

    public function testSeekAndRewind()
    {
        $stream = new InMemoryStream('abcdef');

        $stream->seek(2);
        $this->assertSame('cdef', $stream->getContents());

        $stream->rewind();
        $this->assertSame('abcdef', $stream->getContents());
    }

    public function testWriteOverwritesAtCurrentPosition()
    {
        $stream = new InMemoryStream('abc');
        $stream->write('def');

        $stream->rewind();
        $this->assertSame('def', $stream->getContents());
    }

    public function testReadReadsCorrectLength()
    {
        $stream = new InMemoryStream('abcdef');
        $this->assertSame('abc', $stream->read(3));
        $this->assertSame('def', $stream->read(3));
    }

    public function testGetContentsReadsRemaining()
    {
        $stream = new InMemoryStream('abcdef');
        $stream->read(2);

        $this->assertSame('cdef', $stream->getContents());
    }

    public function testDetachReturnsResourceAndInvalidatesStream()
    {
        $stream = new InMemoryStream('abc');
        $resource = $stream->detach();

        $this->assertIsResource($resource);

        $this->expectException(\RuntimeException::class);
        $stream->getSize();
    }

    public function testCloseClosesResource()
    {
        $stream = new InMemoryStream('abc');
        $stream->close();

        $this->assertFalse(is_resource($stream->detach()));
    }

    public function testGetMetadata()
    {
        $stream = new InMemoryStream('abc');

        $meta = $stream->getMetadata();
        $this->assertIsArray($meta);

        $this->assertSame($meta['mode'], $stream->getMetadata('mode'));
    }
}
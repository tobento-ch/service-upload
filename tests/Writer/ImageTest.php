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

namespace Tobento\Service\Upload\Test\Writer;

use PHPUnit\Framework\TestCase;
use Tobento\Service\Upload\Exception\WriteException;
use Tobento\Service\Upload\Writer\Image;
use Tobento\Service\Upload\Writer\WriterInterface;
use Tobento\Service\Upload\WriteResponseInterface;
use Tobento\Service\Upload\ImageProcessor;
use Tobento\Service\Upload\Test\Factory;
use Tobento\Service\Imager\Response\Encoded;
use Tobento\Service\Message\MessagesInterface;

class ImageTest extends TestCase
{
    public function testImplementsWriterInterface()
    {
        $writer = new Image(
            imageProcessor: new ImageProcessor(),
        );
        
        $this->assertInstanceof(WriterInterface::class, $writer);
    }
    
    public function testWriteMethod()
    {
        $writer = new Image(
            imageProcessor: new ImageProcessor(
                actions: [
                    'orientate' => [],
                    'resize' => ['width' => 25],
                ],
            ),
        );
        
        $writeResponse = $writer->write(
            path: 'image.jpg',
            stream: Factory::createStreamFactory()->createStreamFromFile(
                filename: __DIR__.'/../resources/uploads-private/image.jpg'
            ),
            originalFilename: 'orgfilename.jpg',
        );
        
        $encoded = $writeResponse->content();
        $this->assertInstanceof(Encoded::class, $encoded);
        $this->assertSame(25, $encoded->width());
        $this->assertSame('image.jpg', $writeResponse->path());
        $this->assertSame('orgfilename.jpg', $writeResponse->originalFilename());
        $this->assertSame('Auto orientated image.', $writeResponse->messages()->first()->message());
    }
    
    public function testWriteMethodRebuildsPathIfExtensionChanges()
    {
        $writer = new Image(
            imageProcessor: new ImageProcessor(
                actions: [
                    'orientate' => [],
                    'resize' => ['width' => 25],
                ],
                convert: ['image/jpeg' => 'image/gif'],
            ),
        );
        
        $writeResponse = $writer->write(
            path: 'foo/bar/image.jpg',
            stream: Factory::createStreamFactory()->createStreamFromFile(
                filename: __DIR__.'/../resources/uploads-private/image.jpg'
            ),
            originalFilename: 'orgfilename.jpg',
        );
        
        $this->assertSame('foo/bar/image.gif', $writeResponse->path());
        $this->assertSame('orgfilename.jpg', $writeResponse->originalFilename());
    }
    
    public function testWriteMethodReturnsNullIfUnsupportedFile()
    {
        $writer = new Image(
            imageProcessor: new ImageProcessor(
                actions: [
                    'orientate' => [],
                    'resize' => ['width' => 25],
                ],
                convert: ['image/jpeg' => 'image/gif'],
            ),
        );
        
        $writeResponse = $writer->write(
            path: 'foo.txt',
            stream: Factory::createStreamFactory()->createStream('content'),
            originalFilename: 'foo.txt',
        );
        
        $this->assertNull($writeResponse);
    }
}
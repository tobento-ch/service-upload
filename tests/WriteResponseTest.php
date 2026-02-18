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
use Tobento\Service\Upload\WriteResponse;
use Tobento\Service\Upload\WriteResponseInterface;
use Tobento\Service\Upload\Test\Factory;
use Tobento\Service\Message\MessagesInterface;
use Tobento\Service\Message\Messages;

class WriteResponseTest extends TestCase
{
    public function testInterfaceMethods()
    {
        $response = new WriteResponse(
            path: 'path/file.txt',
            content: 'content',
            originalFilename: 'filename.txt'
        );
        
        $this->assertInstanceof(WriteResponseInterface::class, $response);
        $this->assertSame('path/file.txt', $response->path());
        $this->assertSame('content', $response->content());
        $this->assertSame('filename.txt', $response->originalFilename());
        $this->assertInstanceof(MessagesInterface::class, $response->messages());
    }
    
    public function testWithMessages()
    {
        $messages = new Messages();
        
        $response = new WriteResponse(
            path: 'path/file.txt',
            content: 'content',
            originalFilename: 'filename.txt',
            messages: $messages,
        );
        
        $this->assertSame($messages, $response->messages());
    }
    
    public function testWithStreamContent()
    {
        $stream = Factory::createStreamFactory()->createStream('content');

        $response = new WriteResponse(
            path: 'path/file.txt',
            content: $stream,
            originalFilename: 'filename.txt'
        );

        $this->assertSame($stream, $response->content());
        $this->assertSame('path/file.txt', $response->path());
        $this->assertSame('filename.txt', $response->originalFilename());
    }
}
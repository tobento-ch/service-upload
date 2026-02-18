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

use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Tobento\Service\Imager\Action;
use Tobento\Service\Imager\Resource\File;
use Tobento\Service\Imager\Response\Encoded;
use Tobento\Service\Upload\Exception\ImageProcessException;
use Tobento\Service\Upload\Exception\UnsupportedImageException;
use Tobento\Service\Upload\ImageProcessor;
use Tobento\Service\Upload\ImageProcessorInterface;
use Tobento\Service\Upload\Test\Factory;

class ImageProcessorTest extends TestCase
{
    public function testThatImplementsImageProcessorInterface()
    {
        $this->assertInstanceof(ImageProcessorInterface::class, new ImageProcessor());
    }
    
    public function testWithActionsMethod()
    {
        $processor = new ImageProcessor();
        $processorNew = $processor->withActions(['resize' => ['width' => 30]]);
        
        $this->assertFalse($processor === $processorNew);
    }
    
    public function testWithConvertMethod()
    {
        $processor = new ImageProcessor();
        $processorNew = $processor->withConvert(['image/png' => 'image/jpeg']);
        
        $this->assertFalse($processor === $processorNew);
    }
    
    public function testWithQualityMethod()
    {
        $processor = new ImageProcessor();
        $processorNew = $processor->withQuality(['image/jpeg' => 60]);
        
        $this->assertFalse($processor === $processorNew);
    }
    
    public function testProcessFromResourceMethod()
    {
        $encoded = new ImageProcessor()->processFromResource(
            resource: new File(__DIR__.'/resources/uploads-private/image.jpg'),
        );
        
        $this->assertInstanceof(Encoded::class, $encoded);
        $this->assertNotEmpty($encoded->encoded());
        $this->assertSame('image/jpeg', $encoded->mimeType());
        $this->assertSame('jpg', $encoded->extension());
        $this->assertSame(200, $encoded->width());
        $this->assertSame(150, $encoded->height());
        $this->assertSame(12733, $encoded->size());
        $this->assertFalse($encoded->actions()->empty());
    }
    
    public function testProcessFromStreamMethod()
    {
        $encoded = new ImageProcessor()->processFromStream(
            stream: Factory::createStreamFactory()->createStream(file_get_contents(__DIR__.'/resources/uploads-private/image.jpg')),
        );
        
        $this->assertInstanceof(Encoded::class, $encoded);
        $this->assertNotEmpty($encoded->encoded());
        $this->assertSame('image/jpeg', $encoded->mimeType());
        $this->assertSame('jpg', $encoded->extension());
        $this->assertSame(200, $encoded->width());
        $this->assertSame(150, $encoded->height());
        $this->assertSame(12733, $encoded->size());
        $this->assertFalse($encoded->actions()->empty());
    }

    public function testThrowsUnsupportedImageExceptionIfInvalidMimeType()
    {
        $this->expectException(UnsupportedImageException::class);
        
        $encoded = new ImageProcessor()->processFromStream(
            stream: Factory::createStreamFactory()->createStream('content'),
        );
    }
    
    public function testThrowsUnsupportedImageExceptionIfUnsupportedMimeType()
    {
        $this->expectException(UnsupportedImageException::class);
        
        $encoded = new ImageProcessor(supportedMimeTypes: ['image/gif'])->processFromResource(
            resource: new File(__DIR__.'/resources/uploads-private/image.jpg'),
        );
    }
    
    public function testActionsGetsProcessed()
    {
        $processor = new ImageProcessor(
            actions: [
                new Action\Orientate(),
                'resize' => ['width' => 20],
            ],
        );
        
        $encoded = $processor->processFromResource(
            resource: new File(__DIR__.'/resources/uploads-private/image.jpg'),
        );
        
        $this->assertNotEmpty($encoded->encoded());
        $this->assertSame(20, $encoded->width());
        $this->assertSame(15, $encoded->height());
        $this->assertSame(889, $encoded->size());
        $this->assertSame(3, count($encoded->actions()->all()));
        $this->assertInstanceof(Action\Orientate::class, $encoded->actions()->all()[0]);
        $this->assertInstanceof(Action\Resize::class, $encoded->actions()->all()[1]);
        $this->assertInstanceof(Action\Encode::class, $encoded->actions()->all()[2]);
    }
    
    public function testOnlyAllowedActionsGetsProcessed()
    {
        $processor = new ImageProcessor(
            actions: [
                'orientate' => [],
                'resize' => ['width' => 20],
            ],
            allowedActions: [
                Action\Orientate::class,
            ],
        );
        
        $encoded = $processor->processFromResource(
            resource: new File(__DIR__.'/resources/uploads-private/image.jpg'),
        );
        
        $this->assertSame(2, count($encoded->actions()->all()));
        $this->assertInstanceof(Action\Orientate::class, $encoded->actions()->all()[0]);
        $this->assertInstanceof(Action\Encode::class, $encoded->actions()->all()[1]);
    }
    
    public function testAllowedActionsLogsDisallowedActions()
    {
        $logger = new Logger('name');
        $testHandler = new TestHandler();
        $logger->pushHandler($testHandler);
        
        $processor = new ImageProcessor(
            actions: [
                'orientate' => [],
                'resize' => ['width' => 20],
            ],
            allowedActions: [
                Action\Orientate::class,
            ],
            logger: $logger,
        );
        
        $encoded = $processor->processFromResource(
            resource: new File(__DIR__.'/resources/uploads-private/image.jpg'),
        );
        
        $this->assertTrue($testHandler->hasRecord('Disallowed action '.Action\Resize::class.' skipped', Level::Notice));
    }
    
    public function testDisallowedActionsGetSkipped()
    {
        $processor = new ImageProcessor(
            actions: [
                'orientate' => [],
                'resize' => ['width' => 20],
            ],
            disallowedActions: [
                Action\Resize::class,
            ],
        );
        
        $encoded = $processor->processFromResource(
            resource: new File(__DIR__.'/resources/uploads-private/image.jpg'),
        );
        
        $this->assertSame(2, count($encoded->actions()->all()));
        $this->assertInstanceof(Action\Orientate::class, $encoded->actions()->all()[0]);
        $this->assertInstanceof(Action\Encode::class, $encoded->actions()->all()[1]);
    }
    
    public function testDisallowedActionsGetsLoggedIfSkipped()
    {
        $logger = new Logger('name');
        $testHandler = new TestHandler();
        $logger->pushHandler($testHandler);
        
        $processor = new ImageProcessor(
            actions: [
                'orientate' => [],
                'resize' => ['width' => 20],
            ],
            disallowedActions: [
                Action\Resize::class,
            ],
            logger: $logger,
        );
        
        $encoded = $processor->processFromResource(
            resource: new File(__DIR__.'/resources/uploads-private/image.jpg'),
        );
        
        $this->assertTrue($testHandler->hasRecord('Disallowed action '.Action\Resize::class.' skipped', Level::Notice));
    }
    
    public function testInvalidActionsGetsSkipped()
    {
        $processor = new ImageProcessor(
            actions: [
                'invalid' => [],
            ],
            allowedActions: [
                Action\Orientate::class,
            ],
        );
        
        $encoded = $processor->processFromResource(
            resource: new File(__DIR__.'/resources/uploads-private/image.jpg'),
        );
        
        $this->assertSame(1, count($encoded->actions()->all()));
        $this->assertInstanceof(Action\Encode::class, $encoded->actions()->all()[0]);
    }
    
    public function testInvalidActionsGetsLogged()
    {
        $logger = new Logger('name');
        $testHandler = new TestHandler();
        $logger->pushHandler($testHandler);
        
        $processor = new ImageProcessor(
            actions: [
                'resize' => ['invalid' => ''],
            ],
            logger: $logger,
        );
        
        $encoded = $processor->processFromResource(
            resource: new File(__DIR__.'/resources/uploads-private/image.jpg'),
        );
        
        $this->assertTrue($testHandler->hasRecord('Unable to create action resize', Level::Notice));
    }    
    
    public function testConvertsImage()
    {
        $processor = new ImageProcessor(
            actions: [new Action\Resize(width: 20)],
            convert: ['image/jpeg' => 'image/gif'],
        );
        
        $encoded = $processor->processFromResource(
            resource: new File(__DIR__.'/resources/uploads-private/image.jpg'),
        );
        
        $this->assertSame('image/gif', $encoded->mimeType());
        $this->assertSame('gif', $encoded->extension());
    }
    
    public function testNotConvertsImageIfInvalidMimeType()
    {
        $processor = new ImageProcessor(
            actions: [new Action\Resize(width: 20)],
            convert: ['image/jpeg' => 'image/invalid'],
        );
        
        $encoded = $processor->processFromResource(
            resource: new File(__DIR__.'/resources/uploads-private/image.jpg'),
        );
        
        $this->assertSame('image/jpeg', $encoded->mimeType());
        $this->assertSame('jpg', $encoded->extension());
    }
    
    public function testNotConvertsImageIfUnsupportedMimeType()
    {
        $processor = new ImageProcessor(
            actions: [new Action\Resize(width: 20)],
            convert: ['image/jpeg' => 'image/gif'],
            supportedMimeTypes: ['image/jpeg'],
        );
        
        $encoded = $processor->processFromResource(
            resource: new File(__DIR__.'/resources/uploads-private/image.jpg'),
        );
        
        $this->assertSame('image/jpeg', $encoded->mimeType());
        $this->assertSame('jpg', $encoded->extension());
    }
    
    public function testQualityIsUsed()
    {
        $processor = new ImageProcessor(
            actions: [new Action\Resize(width: 20)],
            quality: ['image/jpeg' => 33],
        );
        
        $encoded = $processor->processFromResource(
            resource: new File(__DIR__.'/resources/uploads-private/image.jpg'),
        );

        $this->assertSame(33, $encoded->actions()->all()[1]->quality());
    }
    
    public function testQualityIsNotUsedIfInvalid()
    {
        $processor = new ImageProcessor(
            actions: [new Action\Resize(width: 20)],
            quality: ['image/jpeg' => 'invalid'],
        );
        
        $encoded = $processor->processFromResource(
            resource: new File(__DIR__.'/resources/uploads-private/image.jpg'),
        );

        $this->assertSame(90, $encoded->actions()->all()[1]->quality());
    }
}
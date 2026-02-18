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

namespace Tobento\Service\Upload\Test\Validator;

use PHPUnit\Framework\TestCase;
use Tobento\Service\Upload\Exception\UploadedFileException;
use Tobento\Service\Upload\Test\FileFactory;
use Tobento\Service\Upload\Validator\Svg;
use Tobento\Service\Upload\ValidatorInterface;

class SvgTest extends TestCase
{
    public function testImplementsInterface()
    {
        $this->assertInstanceOf(ValidatorInterface::class, new Svg());
    }

    public function testSupportsExtensionsMethod()
    {
        $this->assertSame(['svg'], new Svg()->supportsExtensions());
    }

    public function testValidSvgPasses()
    {
        $validator = new Svg(allowedExtensions: ['svg']);

        $validator->validateUploadedFile(
            file: new FileFactory()->createFileWithContent(
                filename: 'image.svg',
                content: '<svg xmlns="http://www.w3.org/2000/svg"><rect width="10" height="10"/></svg>',
                mimeType: 'image/svg+xml'
            )
        );

        $this->assertTrue(true);
    }

    public function testFailsIfSanitizerRemovesUnsafeContent()
    {
        $this->expectException(UploadedFileException::class);
        $this->expectExceptionMessage('SVG contains XML issues: :issues.');

        new Svg(allowedExtensions: ['svg'])
            ->validateUploadedFile(
                file: new FileFactory()->createFileWithContent(
                    filename: 'image.svg',
                    content: '<svg><script>alert("x")</script></svg>',
                    mimeType: 'image/svg+xml'
                )
            );
    }

    public function testFailsIfInvalidXml()
    {
        $this->expectException(UploadedFileException::class);
        $this->expectExceptionMessage('SVG file is invalid or contains unsafe XML.');

        new Svg(allowedExtensions: ['svg'])
            ->validateUploadedFile(
                file: new FileFactory()->createFileWithContent(
                    filename: 'image.svg',
                    content: '<svg><rect></svg', // broken XML
                    mimeType: 'image/svg+xml'
                )
            );
    }

    public function testFailsIfXmlIssuesDetected()
    {
        $this->expectException(UploadedFileException::class);
        $this->expectExceptionMessage('SVG file is invalid or contains unsafe XML.');

        new Svg(allowedExtensions: ['svg'])
            ->validateUploadedFile(
                file: new FileFactory()->createFileWithContent(
                    filename: 'image.svg',
                    content: '<svg><rect width="10" height="10"></rect></svg><extra>', // extra invalid tag
                    mimeType: 'image/svg+xml'
                )
            );
    }

    public function testFailsIfExtensionNotSvg()
    {
        $this->expectException(UploadedFileException::class);

        new Svg(allowedExtensions: ['svg'])
            ->validateUploadedFile(
                file: new FileFactory()->createFileWithContent(
                    filename: 'file.txt',
                    content: '<svg></svg>',
                    mimeType: 'text/plain'
                )
            );
    }
}
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
use Tobento\Service\Upload\Validator\Ndjson;
use Tobento\Service\Upload\ValidatorInterface;

class NdjsonTest extends TestCase
{
    public function testImplementsInterface()
    {
        $this->assertInstanceof(ValidatorInterface::class, new Ndjson());
    }

    public function testSupportsExtensionsMethod()
    {
        $this->assertSame(['ndjson'], new Ndjson()->supportsExtensions());
    }

    public function testAcceptsNdjsonMimeTypes()
    {
        $validator = new Ndjson(allowedExtensions: ['ndjson']);

        $validMimes = [
            'application/x-ndjson',
            'application/ndjson',
            'application/json',
            'text/plain',
        ];

        foreach ($validMimes as $mime) {
            $validator->validateUploadedFile(
                file: new FileFactory()->createFileWithContent(
                    filename: 'file.ndjson',
                    content: "{\"id\":1}\n{\"id\":2}\n",
                    mimeType: $mime
                )
            );
        }

        $this->assertTrue(true);
    }

    public function testFailsOnInvalidMimeType()
    {
        $this->expectException(UploadedFileException::class);

        new Ndjson(
            allowedExtensions: ['ndjson'],
            validateClientMediaType: true,
        )->validateUploadedFile(
            file: new FileFactory()->createFileWithContent(
                filename: 'file.ndjson',
                content: "{\"id\":1}\n",
                mimeType: 'image/jpeg'
            )
        );
    }

    public function testFailsOnMalformedNdjson()
    {
        $this->expectException(UploadedFileException::class);

        new Ndjson(
            allowedExtensions: ['ndjson']
        )->validateUploadedFile(
            file: new FileFactory()->createFileWithContent(
                filename: 'file.ndjson',
                content: "{\"id\":1}\nINVALID_JSON\n{\"id\":3}\n",
                mimeType: 'application/x-ndjson'
            )
        );
    }

    public function testPassesWithUppercaseExtension()
    {
        new Ndjson(
            allowedExtensions: ['ndjson']
        )->validateUploadedFile(
            file: new FileFactory()->createFileWithContent(
                filename: 'file.NDJSON',
                content: "{\"id\":1}\n{\"id\":2}\n",
                mimeType: 'application/x-ndjson'
            )
        );

        $this->assertTrue(true);
    }

    public function testFailsIfExtensionNotNdjson()
    {
        $this->expectException(UploadedFileException::class);

        new Ndjson(
            allowedExtensions: ['ndjson']
        )->validateUploadedFile(
            file: new FileFactory()->createFileWithContent(
                filename: 'file.txt',
                content: "{\"id\":1}\n",
                mimeType: 'text/plain'
            )
        );
    }

    public function testEmptyLinesAreIgnored()
    {
        new Ndjson(
            allowedExtensions: ['ndjson']
        )->validateUploadedFile(
            file: new FileFactory()->createFileWithContent(
                filename: 'file.ndjson',
                content: "\n{\"id\":1}\n\n{\"id\":2}\n",
                mimeType: 'application/x-ndjson'
            )
        );

        $this->assertTrue(true);
    }
}
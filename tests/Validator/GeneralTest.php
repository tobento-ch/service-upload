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
use Tobento\Service\Upload\Validator\General;
use Tobento\Service\Upload\ValidatorInterface;

class GeneralTest extends TestCase
{
    public function testThatImplementsInterface()
    {
        $this->assertInstanceof(ValidatorInterface::class, new General());
    }

    public function testSupportsExtensionsMethod()
    {
        $this->assertSame([], new General()->supportsExtensions());
    }
    
    public function testFailsIfUploadErr()
    {
        $this->expectException(UploadedFileException::class);
        $this->expectExceptionMessage('No file was uploaded.');
        
        new General(
            allowedExtensions: ['jpg']
        )->validateUploadedFile(
            file: new FileFactory()->createImage(filename: 'profile.jpg')->setError(UPLOAD_ERR_NO_FILE)
        );
    }
    
    public function testFailsIfFileIsEmpty()
    {
        $this->expectException(UploadedFileException::class);
        $this->expectExceptionMessage('The file :name is empty.');

        new General(
            allowedExtensions: ['txt'],
            validateNotEmpty: true,
        )->validateUploadedFile(
            file: new FileFactory()->createFileWithContent(
                filename: 'empty.txt',
                content: ''
            )->setSize(0)
        );
    }
    
    public function testDoesNotFailIfValidateNotEmptyIsDisabled()
    {
        $validator = new General(
            allowedExtensions: ['txt'],
            validateNotEmpty: false,
        );

        $file = (new FileFactory())->createFileWithContent(
            filename: 'empty.txt',
            content: ''
        )->setSize(0);

        try {
            $validator->validateUploadedFile($file);
        } catch (UploadedFileException $e) {
            // Assert that the exception is NOT the empty-file exception
            $this->assertStringNotContainsString('The file :name is empty.', $e->getMessage());
            return;
        }

        // If no exception was thrown at all, that's also fine
        $this->assertTrue(true);
    }
    
    public function testPassesIfAllowedExtension()
    {
        new General(
            allowedExtensions: ['jpg']
        )->validateUploadedFile(
            file: (new FileFactory())->createImage(filename: 'profile.jpg')
        );
        
        $this->assertTrue(true);
    }

    public function testFailsIfNotAllowedExtension()
    {
        $this->expectException(UploadedFileException::class);
        $this->expectExceptionMessage('The extension :extension of the file :name is disallowed. Allowed extensions are :extensions.');
        
        new General(
            allowedExtensions: ['gif']
        )->validateUploadedFile(
            file: new FileFactory()->createImage(filename: 'profile.jpg')
        );
    }

    public function testPassesIfClientMimeTypeIsNull()
    {
        new General(
            allowedExtensions: ['txt']
        )->validateUploadedFile(
            file: new FileFactory()->createFileWithContent(
                filename: 'file.txt',
                content: 'Lorem',
                mimeType: null,
            )
        );
        
        $this->assertTrue(true);
    }
    
    public function testFailsIfClientMimeTypeIsNotConsistentWithItsContent()
    {
        $this->expectException(UploadedFileException::class);
        $this->expectExceptionMessage('The mime type :type of the file :name is invalid. Allowed mime types are :types.');
        
        new General(
            allowedExtensions: ['txt'],
            validateClientMediaType: true,
        )->validateUploadedFile(
            file: new FileFactory()->createFileWithContent(
                filename: 'file.txt',
                content: 'Lorem',
                mimeType: 'image/jpeg'
            )
        );
    }
    
    public function testFailsIfFileExtensionIsNotConsistentWithItsContent()
    {
        $this->expectException(UploadedFileException::class);
        $this->expectExceptionMessage('The mime type :type of the file :name is invalid. Allowed mime types are :types.');
        
        new General(
            allowedExtensions: ['jpg', 'gif']
        )->validateUploadedFile(
            file: new FileFactory()->createFileWithContent(
                filename: 'image.jpg',
                content: 'GIF87a',
                mimeType: 'image/jpeg'
            )
        );
    }
    
    public function testFailsIfMimeTypesCannotBeDeterminedByExtension()
    {
        $this->expectException(UploadedFileException::class);
        $this->expectExceptionMessage('Unable to determine the mime types for the extension :extension.');
        
        new General(
            allowedExtensions: ['unsupported']
        )->validateUploadedFile(
            file: new FileFactory()->createFileWithContent(
                filename: 'file.unsupported',
                content: 'Lorem',
            )
        );
    }
    
    public function testPassesIfFileExtensionIsUppercase()
    {
        new General(
            allowedExtensions: ['txt']
        )->validateUploadedFile(
            file: new FileFactory()->createFileWithContent(
                filename: 'file.TXT',
                content: 'Lorem',
            )
        );
        
        $this->assertTrue(true);
    }

    public function testFailsIfFilenameMissing()
    {
        $this->expectException(UploadedFileException::class);
        $this->expectExceptionMessage('The extension :extension of the file :name is disallowed. Allowed extensions are :extensions.');
        
        new General(
            allowedExtensions: ['jpg'],
        )->validateUploadedFile(
            file: new FileFactory()->createImage(filename: 'file.')
        );
    }
    
    public function testFailsIfFilenameExtensionMissing()
    {
        $this->expectException(UploadedFileException::class);
        $this->expectExceptionMessage('The extension :extension of the file :name is disallowed. Allowed extensions are :extensions.');
        
        new General(
            allowedExtensions: ['jpg'],
        )->validateUploadedFile(
            file: new FileFactory()->createImage(filename: 'file')
        );
    }
    
    public function testFailsIfInvalidFilenameCharactersWhenStrict()
    {
        $this->expectException(UploadedFileException::class);
        $this->expectExceptionMessage('The filename :name has invalid characters. Only alphanumeric characters, hyphen, spaces, and periods are allowed.');
        
        new General(
            allowedExtensions: ['jpg'],
            strictFilenameCharacters: true,
        )->validateUploadedFile(
            file: new FileFactory()->createImage(filename: 'a%bc.jpg')
        );
    }
    
    public function testPassesIfAnyFilenameCharactersWhenNotStrict()
    {
        new General(
            allowedExtensions: ['jpg'],
            strictFilenameCharacters: false,
        )->validateUploadedFile(
            file: new FileFactory()->createImage(filename: 'Foo /a%bc.jpg')
        );
        
        $this->assertTrue(true);
    }
    
    public function testFailsIfMaxFilenameLengthExceeded()
    {
        $this->expectException(UploadedFileException::class);
        $this->expectExceptionMessage('The filename :name must have at most :num characters.');
        
        new General(
            allowedExtensions: ['jpg'],
            maxFilenameLength: 7,
        )->validateUploadedFile(
            file: new FileFactory()->createImage(filename: 'file.jpg')
        );
    }
    
    public function testDoesNotFailIfFileSizeIsWithinLimit()
    {
        new General(
            allowedExtensions: ['txt'],
            maxFileSizeInKb: 6,
        )->validateUploadedFile(
            file: new FileFactory()->createFileWithContent(
                filename: 'file.txt',
                content: 'Lorem',
            )->setSize(5)
        );

        $this->assertTrue(true);
    }
    
    public function testFailsIfMaxFileSizeExceeded()
    {
        $this->expectException(UploadedFileException::class);
        $this->expectExceptionMessage('The file :name exceeded the max upload size of :num KB.');
        
        new General(
            allowedExtensions: ['txt'],
            maxFileSizeInKb: 6,
        )->validateUploadedFile(
            file: new FileFactory()->createFileWithContent(
                filename: 'file.txt',
                content: 'Lorem',
            )->setSize(7)
        );
    }
}
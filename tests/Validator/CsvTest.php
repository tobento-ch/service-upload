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
use Tobento\Service\Upload\Validator\Csv;
use Tobento\Service\Upload\ValidatorInterface;

class CsvTest extends TestCase
{
    public function testImplementsInterface()
    {
        $this->assertInstanceof(ValidatorInterface::class, new Csv());
    }
    
    public function testSupportsExtensionsMethod()
    {
        $this->assertSame(['csv'], new Csv()->supportsExtensions());
    }

    public function testAcceptsCsvMimeTypes()
    {
        $validator = new Csv(allowedExtensions: ['csv']);

        $validMimes = [
            'text/csv',
            'text/plain',
            'application/csv',
            'application/vnd.ms-excel',
        ];

        foreach ($validMimes as $mime) {
            $validator->validateUploadedFile(
                file: new FileFactory()->createFileWithContent(
                    filename: 'file.csv',
                    content: "a,b,c\n1,2,3",
                    mimeType: $mime
                )
            );
        }

        $this->assertTrue(true);
    }

    public function testFailsOnInvalidMimeType()
    {
        $this->expectException(UploadedFileException::class);
        $this->expectExceptionMessage('The mime type :type of the file :name is invalid.');

        new Csv(
            allowedExtensions: ['csv'],
            validateClientMediaType: true,
        )->validateUploadedFile(
            file: new FileFactory()->createFileWithContent(
                filename: 'file.csv',
                content: "a,b,c\n1,2,3",
                mimeType: 'image/jpeg'
            )
        );
    }

    public function testFailsIfNotParseableAsCsv()
    {
        $this->expectException(UploadedFileException::class);
        $this->expectExceptionMessage('The mime type :type of the file :name is invalid.');

        new Csv(
            allowedExtensions: ['csv']
        )->validateUploadedFile(
            file: new FileFactory()->createFileWithContent(
                filename: 'file.csv',
                content: "\x00\x01\x02\x03",
                mimeType: 'text/csv'
            )
        );
    }

    public function testFailsIfColumnCountInconsistent()
    {
        $this->expectException(UploadedFileException::class);
        $this->expectExceptionMessage('The CSV file :name has inconsistent column counts.');

        new Csv(
            allowedExtensions: ['csv']
        )->validateUploadedFile(
            file: new FileFactory()->createFileWithContent(
                filename: 'file.csv',
                content: "a,b,c\n1,2\n3,4,5,6",
                mimeType: 'text/csv'
            )
        );
    }

    public function testDetectsFormulaInjection()
    {
        $this->expectException(UploadedFileException::class);
        $this->expectExceptionMessage('The CSV file :name contains potentially dangerous spreadsheet formulas.');

        new Csv(
            allowedExtensions: ['csv']
        )->validateUploadedFile(
            file: new FileFactory()->createFileWithContent(
                filename: 'file.csv',
                content: "=SUM(A1:A2),2,3\n1,2,3",
                mimeType: 'text/csv'
            )
        );
    }

    public function testPassesWithUppercaseExtension()
    {
        new Csv(
            allowedExtensions: ['csv']
        )->validateUploadedFile(
            file: new FileFactory()->createFileWithContent(
                filename: 'file.CSV',
                content: "a,b,c\n1,2,3",
                mimeType: 'text/csv'
            )
        );

        $this->assertTrue(true);
    }

    public function testFailsIfExtensionNotCsv()
    {
        $this->expectException(UploadedFileException::class);

        new Csv(
            allowedExtensions: ['csv']
        )->validateUploadedFile(
            file: new FileFactory()->createFileWithContent(
                filename: 'file.txt',
                content: "a,b,c\n1,2,3",
                mimeType: 'text/plain'
            )
        );
    }
    
    public function testWithValidateCsvContentDisablesCsvValidation()
    {
        // CSV is malformed (inconsistent columns), but validation is disabled
        $validator = new Csv(allowedExtensions: ['csv']);
        
        $validatorNew = $validator->withValidateCsvContent(false);
        
        $this->assertFalse($validator === $validatorNew);
        
        // Should NOT throw, because CSV content validation is disabled
        $validatorNew->validateUploadedFile(
            file: new FileFactory()->createFileWithContent(
                filename: 'file.csv',
                content: "a,b,c\n1,2\n3,4,5,6",
                mimeType: 'text/csv'
            )
        );

        $this->assertTrue(true);
    }
}
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
use Tobento\Service\Upload\Validator\Pdf;
use Tobento\Service\Upload\ValidatorInterface;

class PdfTest extends TestCase
{
    public function testImplementsInterface()
    {
        $this->assertInstanceOf(ValidatorInterface::class, new Pdf());
    }

    public function testSupportsExtensionsMethod()
    {
        $this->assertSame(['pdf'], new Pdf()->supportsExtensions());
    }

    public function testValidPdfPasses()
    {
        $validator = new Pdf(allowedExtensions: ['pdf']);

        $validator->validateUploadedFile(
            file: new FileFactory()->createFileWithContent(
                filename: 'file.pdf',
                content: "%PDF-1.7\nSome content here",
                mimeType: 'application/pdf'
            )
        );

        $this->assertTrue(true);
    }

    public function testFailsIfEncryptedMixedCase()
    {
        $this->expectException(UploadedFileException::class);
        $this->expectExceptionMessage('PDF contains :feature, which is not allowed.');

        new Pdf(allowedExtensions: ['pdf'])
            ->validateUploadedFile(
                file: new FileFactory()->createFileWithContent(
                    filename: 'file.pdf',
                    content: "%PDF-1.7\n/EnCrYpT 123",
                    mimeType: 'application/pdf'
                )
            );
    }

    public function testFailsIfContainsJavaScriptMixedCase()
    {
        $this->expectException(UploadedFileException::class);
        $this->expectExceptionMessage('PDF contains :feature, which is not allowed.');

        new Pdf(allowedExtensions: ['pdf'])
            ->validateUploadedFile(
                file: new FileFactory()->createFileWithContent(
                    filename: 'file.pdf',
                    content: "%PDF-1.7\n/JaVaScRiPt (alert('x'))",
                    mimeType: 'application/pdf'
                )
            );
    }

    public function testFailsIfContainsEmbeddedFileMixedCase()
    {
        $this->expectException(UploadedFileException::class);
        $this->expectExceptionMessage('PDF contains :feature, which is not allowed.');

        new Pdf(allowedExtensions: ['pdf'])
            ->validateUploadedFile(
                file: new FileFactory()->createFileWithContent(
                    filename: 'file.pdf',
                    content: "%PDF-1.7\n/EmBeDdEdFiLe",
                    mimeType: 'application/pdf'
                )
            );
    }

    public function testFailsIfContainsLaunchActionMixedCase()
    {
        $this->expectException(UploadedFileException::class);
        $this->expectExceptionMessage('PDF contains :feature, which is not allowed.');

        new Pdf(allowedExtensions: ['pdf'])
            ->validateUploadedFile(
                file: new FileFactory()->createFileWithContent(
                    filename: 'file.pdf',
                    content: "%PDF-1.7\n/LaUnCh (cmd.exe)",
                    mimeType: 'application/pdf'
                )
            );
    }

    public function testFailsIfContainsOpenAction()
    {
        $this->expectException(UploadedFileException::class);
        $this->expectExceptionMessage('PDF contains :feature, which is not allowed.');

        new Pdf(allowedExtensions: ['pdf'])
            ->validateUploadedFile(
                file: new FileFactory()->createFileWithContent(
                    filename: 'file.pdf',
                    content: "%PDF-1.7\n/OpenAction << /JS (alert('x')) >>",
                    mimeType: 'application/pdf'
                )
            );
    }

    public function testFailsIfContainsAdditionalActionsAA()
    {
        $this->expectException(UploadedFileException::class);
        $this->expectExceptionMessage('PDF contains :feature, which is not allowed.');

        new Pdf(allowedExtensions: ['pdf'])
            ->validateUploadedFile(
                file: new FileFactory()->createFileWithContent(
                    filename: 'file.pdf',
                    content: "%PDF-1.7\n/AA << /O (alert('x')) >>",
                    mimeType: 'application/pdf'
                )
            );
    }

    public function testFailsIfExtensionNotPdf()
    {
        $this->expectException(UploadedFileException::class);

        new Pdf(allowedExtensions: ['pdf'])
            ->validateUploadedFile(
                file: new FileFactory()->createFileWithContent(
                    filename: 'file.txt',
                    content: "%PDF-1.7\nSome content",
                    mimeType: 'text/plain'
                )
            );
    }
    
    public function testWithChecksAllowsOnlySpecifiedChecks()
    {
        $validator = new Pdf(allowedExtensions: ['pdf'])
            ->withChecks('js'); // only JS checks enabled

        // Should fail because JS is present
        $this->expectException(UploadedFileException::class);

        $validator->validateUploadedFile(
            file: new FileFactory()->createFileWithContent(
                filename: 'file.pdf',
                content: "%PDF-1.7\n/JavaScript (alert('x'))",
                mimeType: 'application/pdf'
            )
        );
    }

    public function testWithChecksDoesNotRunOtherChecks()
    {
        $validator = (new Pdf(allowedExtensions: ['pdf']))
            ->withChecks('encrypt'); // only encryption check enabled

        // Contains JavaScript, but JS check is disabled
        $validator->validateUploadedFile(
            file: new FileFactory()->createFileWithContent(
                filename: 'file.pdf',
                content: "%PDF-1.7\n/JavaScript (alert('x'))",
                mimeType: 'application/pdf'
            )
        );

        $this->assertTrue(true);
    }

    public function testWithChecksThrowsForInvalidCheck()
    {
        $this->expectException(\InvalidArgumentException::class);

        new Pdf()->withChecks('invalidCheckName');
    }
    
    public function testWithChecksIsImmutable()
    {
        $validator = new Pdf(allowedExtensions: ['pdf']);

        $newValidator = $validator->withChecks('js');

        // The two instances must not be the same object
        $this->assertNotSame($validator, $newValidator);

        // The original validator should still have no checks defined
        $this->assertSame([], (new \ReflectionClass($validator))
            ->getProperty('checks')
            ->getValue($validator));

        // The new validator should contain only the 'js' check
        $this->assertSame(['js'], (new \ReflectionClass($newValidator))
            ->getProperty('checks')
            ->getValue($newValidator));
    }
}
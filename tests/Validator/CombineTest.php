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
use Tobento\Service\Upload\Validator\Combine;
use Tobento\Service\Upload\ValidatorInterface;

class CombineTest extends TestCase
{
    public function testImplementsInterface()
    {
        $this->assertInstanceof(ValidatorInterface::class, new Combine());
    }
    
    public function testSupportsExtensionsMethod()
    {
        $this->assertSame([], new Combine()->supportsExtensions());
    }
    
    public function testDispatchesToMatchingValidator()
    {
        $csvValidator = new class implements ValidatorInterface {
            public bool $called = false;

            public function supportsExtensions(): array
            {
                return ['csv'];
            }

            public function validateUploadedFile($file): void
            {
                $this->called = true;
            }
        };

        $fallbackValidator = new class implements ValidatorInterface {
            public bool $called = false;

            public function supportsExtensions(): array
            {
                return [];
            }

            public function validateUploadedFile($file): void
            {
                $this->called = true;
            }
        };

        $validator = new Combine($csvValidator, $fallbackValidator);

        $validator->validateUploadedFile(
            new FileFactory()->createFileWithContent(
                filename: 'file.csv',
                content: "a,b,c\n1,2,3",
                mimeType: 'text/csv'
            )
        );

        $this->assertTrue($csvValidator->called);
        $this->assertFalse($fallbackValidator->called);
    }

    public function testFallsBackWhenNoExtensionMatches()
    {
        $csvValidator = new class implements ValidatorInterface {
            public bool $called = false;

            public function supportsExtensions(): array
            {
                return ['csv'];
            }

            public function validateUploadedFile($file): void
            {
                $this->called = true;
            }
        };

        $fallbackValidator = new class implements ValidatorInterface {
            public bool $called = false;

            public function supportsExtensions(): array
            {
                return []; // supports all
            }

            public function validateUploadedFile($file): void
            {
                $this->called = true;
            }
        };

        $validator = new Combine($csvValidator, $fallbackValidator);

        $validator->validateUploadedFile(
            new FileFactory()->createFileWithContent(
                filename: 'file.txt',
                content: "hello",
                mimeType: 'text/plain'
            )
        );

        $this->assertFalse($csvValidator->called);
        $this->assertTrue($fallbackValidator->called);
    }

    public function testThrowsIfNoValidatorSupportsExtension()
    {
        $this->expectException(UploadedFileException::class);
        $this->expectExceptionMessage('No validator available for file :name.');

        $validator = new Combine(
            new class implements ValidatorInterface {
                public function supportsExtensions(): array
                {
                    return ['csv'];
                }
                public function validateUploadedFile($file): void {}
            }
        );

        $validator->validateUploadedFile(
            new FileFactory()->createFileWithContent(
                filename: 'file.jpg',
                content: "binary",
                mimeType: 'image/jpeg'
            )
        );
    }

    public function testValidatorsAreCheckedInOrder()
    {
        $first = new class implements ValidatorInterface {
            public bool $called = false;
            public function supportsExtensions(): array { return []; }
            public function validateUploadedFile($file): void { $this->called = true; }
        };

        $second = new class implements ValidatorInterface {
            public bool $called = false;
            public function supportsExtensions(): array { return []; }
            public function validateUploadedFile($file): void { $this->called = true; }
        };

        $validator = new Combine($first, $second);

        $validator->validateUploadedFile(
            new FileFactory()->createFileWithContent(
                filename: 'file.any',
                content: "x",
                mimeType: 'text/plain'
            )
        );

        $this->assertTrue($first->called);
        $this->assertFalse($second->called);
    }
}
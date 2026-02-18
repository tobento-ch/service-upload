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
use Tobento\Service\Upload\Validator\Zip;
use Tobento\Service\Upload\ValidatorInterface;
use ZipArchive;

class ZipTest extends TestCase
{
    protected function createZip(array $entries): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ziptest_');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);

        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }

        $zip->close();
        return $tmp;
    }

    public function testImplementsInterface()
    {
        $this->assertInstanceof(ValidatorInterface::class, new Zip());
    }
    
    public function testSupportsExtensionsMethod()
    {
        $this->assertSame(['zip'], new Zip()->supportsExtensions());
    }
    
    public function testValidZipPasses()
    {
        $zipPath = $this->createZip([
            'file1.txt' => 'hello',
            'file2.txt' => 'world',
        ]);

        $file = new FileFactory()->createFileWithContent(
            filename: 'test.zip',
            content: file_get_contents($zipPath),
            mimeType: 'application/zip'
        );

        $validator = new Zip(allowedExtensions: ['zip']);

        $this->assertNull($validator->validateUploadedFile($file));
    }

    public function testTooManyEntriesFails()
    {
        $entries = [];
        for ($i = 0; $i < 3000; $i++) {
            $entries["file_$i.txt"] = 'x';
        }

        $zipPath = $this->createZip($entries);

        $file = new FileFactory()->createFileWithContent(
            filename: 'bomb.zip',
            content: file_get_contents($zipPath),
            mimeType: 'application/zip'
        );

        $validator = new Zip(allowedExtensions: ['zip'])
            ->withMaxEntries(1000);

        $this->expectException(UploadedFileException::class);
        $validator->validateUploadedFile($file);
    }

    public function testUncompressedSizeLimitFails()
    {
        $zipPath = $this->createZip([
            'bigfile.txt' => str_repeat('A', 10_000_000),
        ]);

        $file = new FileFactory()->createFileWithContent(
            filename: 'big.zip',
            content: file_get_contents($zipPath),
            mimeType: 'application/zip'
        );

        $validator = new Zip(allowedExtensions: ['zip'])
            ->withMaxTotalUncompressedBytes(5_000_000);

        $this->expectException(UploadedFileException::class);
        $validator->validateUploadedFile($file);
    }

    public function testCompressionRatioFails()
    {
        $zipPath = $this->createZip([
            'bomb.txt' => str_repeat('A', 5_000_000),
        ]);

        $file = new FileFactory()->createFileWithContent(
            filename: 'ratio.zip',
            content: file_get_contents($zipPath),
            mimeType: 'application/zip'
        );

        $validator = new Zip(allowedExtensions: ['zip'])
            ->withMaxCompressionRatio(5);

        $this->expectException(UploadedFileException::class);
        $validator->validateUploadedFile($file);
    }

    public function testDirectoryTraversalFails()
    {
        $zipPath = $this->createZip([
            '../evil.txt' => 'malicious',
        ]);

        $file = new FileFactory()->createFileWithContent(
            filename: 'traversal.zip',
            content: file_get_contents($zipPath),
            mimeType: 'application/zip'
        );

        $validator = new Zip(allowedExtensions: ['zip']);

        $this->expectException(UploadedFileException::class);
        $validator->validateUploadedFile($file);
    }

    public function testNestedZipDepthFails()
    {
        $innerZipPath = $this->createZip(['inner.txt' => 'x']);
        $innerContent = file_get_contents($innerZipPath);

        $outerZipPath = $this->createZip([
            'nested.zip' => $innerContent,
        ]);

        $file = new FileFactory()->createFileWithContent(
            filename: 'nested.zip',
            content: file_get_contents($outerZipPath),
            mimeType: 'application/zip'
        );

        $validator = new Zip(allowedExtensions: ['zip'])
            ->withMaxDepth(0);

        $this->expectException(UploadedFileException::class);
        $validator->validateUploadedFile($file);
    }
}
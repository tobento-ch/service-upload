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
use Tobento\Service\Upload\CopyFileWrapper;
use Tobento\Service\Upload\InMemoryStream;
use Tobento\Service\Upload\InMemoryUploadedFile;

class CopyFileWrapperTest extends TestCase
{
    public function testWrapper()
    {
        $stream = new InMemoryStream('content');

        $uploadedFile = new InMemoryUploadedFile(
            filename: 'image.jpg',
            content: (string)$stream,
            mediaType: 'image/jpeg'
        );

        $wrapper = new CopyFileWrapper(
            uploadedFile: $uploadedFile,
            storage: 'uploads',
            path: 'foo/image.jpg',
        );

        $this->assertSame($uploadedFile, $wrapper->uploadedFile());
        $this->assertSame('uploads', $wrapper->storage());
        $this->assertSame('foo/image.jpg', $wrapper->path());
    }
}
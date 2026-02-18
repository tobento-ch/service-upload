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

use Psr\Http\Message\UploadedFileInterface;
use Tobento\Service\Upload\InMemoryUploadedFile;

final class FileFactory
{
    /**
     * Create a new fake uploaded file.
     *
     * @param string $filename
     * @param null|int $kilobytes
     * @param null|string $mimeType
     * @return UploadedFileInterface
     */
    public function createFile(
        string $filename,
        null|int $kilobytes = null,
        null|string $mimeType = null
    ): UploadedFileInterface {
        $content = '';

        if ($kilobytes !== null) {
            $content = str_repeat('0', $kilobytes * 1024);
        }

        return new InMemoryUploadedFile(
            filename: $filename,
            contents: $content,
            mediaType: $mimeType ?? 'application/octet-stream',
            error: UPLOAD_ERR_OK
        );
    }

    /**
     * Create a new fake file with given content.
     *
     * @param string $filename
     * @param string $content
     * @param null|string $mimeType
     * @return UploadedFileInterface
     */
    public function createFileWithContent(
        string $filename,
        string $content,
        null|string $mimeType = null
    ): UploadedFileInterface {
        return new InMemoryUploadedFile(
            filename: $filename,
            content: $content,
            mediaType: $mimeType ?? 'application/octet-stream',
            error: UPLOAD_ERR_OK
        );
    }

    /**
     * Create a new fake image.
     *
     * @param string $filename
     * @param int $width
     * @param int $height
     * @return UploadedFileInterface
     */
    public function createImage(
        string $filename,
        int $width = 50,
        int $height = 50
    ): UploadedFileInterface {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $supported = ['jpeg', 'jpg', 'png', 'gif', 'webp', 'bmp', 'wbmp'];
        $extension = in_array($extension, $supported, true) ? $extension : 'jpeg';

        // Create an in-memory image
        $image = imagecreatetruecolor($width, $height);

        ob_start();
        $func = sprintf('image%s', $extension === 'jpg' ? 'jpeg' : $extension);
        $func($image);
        $content = ob_get_clean();

        return new InMemoryUploadedFile(
            filename: $filename,
            content: $content,
            mediaType: sprintf('image/%s', $extension === 'jpg' ? 'jpeg' : $extension),
            error: UPLOAD_ERR_OK
        );
    }
}
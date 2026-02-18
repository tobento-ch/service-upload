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
 
namespace Tobento\Service\Upload\Writer;

use Psr\Http\Message\StreamInterface;
use Tobento\Service\Upload\Exception\WriteException;
use Tobento\Service\Upload\WriteResponseInterface;

interface WriterInterface
{
    /**
     * Write the file if supported.
     *
     * Returns a WriteResponseInterface when the writer supports the file.
     * Returns null when the writer does not support the given file, allowing
     * the FileWriter to try the next writer.
     *
     * @param string $path
     * @param StreamInterface $stream
     * @param string $originalFilename
     * @return null|WriteResponseInterface
     * @throws WriteException
     */
    public function write(
        string $path,
        StreamInterface $stream,
        string $originalFilename,
    ): null|WriteResponseInterface;
}
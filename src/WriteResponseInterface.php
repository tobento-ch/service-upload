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
 
namespace Tobento\Service\Upload;

use Psr\Http\Message\StreamInterface;
use Stringable;
use Tobento\Service\Message\MessagesInterface;

/**
 * WriteResponseInterface
 */
interface WriteResponseInterface
{
    /**
     * Returns the path.
     *
     * @return string
     */
    public function path(): string;
    
    /**
     * Returns the content.
     *
     * @return string|Stringable|StreamInterface
     */
    public function content(): string|Stringable|StreamInterface;
    
    /**
     * Returns the original filename (unmodified). Might come from client.
     *
     * @return string
     */
    public function originalFilename(): string;
    
    /**
     * Returns the messages.
     *
     * @return MessagesInterface
     */
    public function messages(): MessagesInterface;
}
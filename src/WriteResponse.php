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
use Tobento\Service\Message\HasMessages;
use Tobento\Service\Message\MessagesInterface;

/**
 * WriteResponse
 */
class WriteResponse implements WriteResponseInterface
{
    use HasMessages;
    
    /**
     * Create a new WriteResponse.
     *
     * @param string $path
     * @param string|Stringable|StreamInterface $content
     * @param string $originalFilename The original filename (unmodified). Might come from client.
     * @param null|MessagesInterface $messages
     */
    public function __construct(
        protected string $path,
        protected string|Stringable|StreamInterface $content,
        protected string $originalFilename,
        null|MessagesInterface $messages = null,
    ) {
        $this->messages = $messages;
    }
    
    /**
     * Returns the path.
     *
     * @return string
     */
    public function path(): string
    {
        return $this->path;
    }
    
    /**
     * Returns the content.
     *
     * @return string|Stringable|StreamInterface
     */
    public function content(): string|Stringable|StreamInterface
    {
        return $this->content;
    }
    
    /**
     * Returns the original filename (unmodified). Might come from client.
     *
     * @return string
     */
    public function originalFilename(): string
    {
        return $this->originalFilename;
    }
}
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
use Tobento\Service\Imager\ResourceInterface;
use Tobento\Service\Imager\Response\Encoded;
use Tobento\Service\Imager\Response\Action;
use Tobento\Service\Upload\Exception\ImageProcessException;

interface ImageProcessorInterface
{
    /**
     * Returns a new instance with the specified actions to process.
     *
     * @param array $actions ['resize' => ['width' => 300], new Action\Resize(width: 300)]
     * @return static
     */
    public function withActions(array $actions): static;
    
    /**
     * Returns a new instance with the specified convert to process.
     *
     * @param array $convert ['image/png' => 'image/jpeg']
     * @return static
     */
    public function withConvert(array $convert): static;
    
    /**
     * Returns a new instance with the specified quality to process.
     *
     * @param array $quality ['image/jpeg' => 90]
     * @return static
     */
    public function withQuality(array $quality): static;
    
    /**
     * Process image from resource.
     *
     * @param ResourceInterface $resource
     * @return Encoded
     * @throws ImageProcessException
     */
    public function processFromResource(ResourceInterface $resource): Encoded;
    
    /**
     * Process image from stream.
     *
     * @param StreamInterface $stream
     * @return Encoded
     * @throws ImageProcessException
     */
    public function processFromStream(StreamInterface $stream): Encoded;
}
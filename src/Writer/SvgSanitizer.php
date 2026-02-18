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

use enshrined\svgSanitize\Sanitizer;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Tobento\Service\Upload\Exception\WriteException;
use Tobento\Service\Upload\WriteResponse;
use Tobento\Service\Upload\WriteResponseInterface;

/**
 * SvgSanitizer writer
 */
class SvgSanitizer implements WriterInterface
{
    /**
     * @var Sanitizer
     */
    protected Sanitizer $sanitizer;
    
    /**
     * Create a new SvgSanitizerWriter instance.
     *
     * @param null|Sanitizer $sanitizer
     * @param bool $logIssues
     * @param null|LoggerInterface $logger
     */
    public function __construct(
        null|Sanitizer $sanitizer = null,
        protected bool $logIssues = true,
        protected null|LoggerInterface $logger = null,
    ) {
        if (is_null($sanitizer)) {
            $sanitizer = new Sanitizer();
            $sanitizer->removeXMLTag(true);
            $sanitizer->removeRemoteReferences(true);
            //$sanitizer->minify(true);
        }
        
        $this->sanitizer = $sanitizer;
    }
    
    /**
     * Write.
     *
     * @param string $path
     * @param StreamInterface $stream
     * @param string $originalFilename
     * @return null|WriteResponseInterface Null if not supports writing for the given path and stream.
     * @throws WriteException
     */
    public function write(
        string $path,
        StreamInterface $stream,
        string $originalFilename,
    ): null|WriteResponseInterface {
        if (!str_ends_with($path, '.svg')) {
            return null;
        }
        
        $cleanSVG = $this->sanitizer->sanitize((string)$stream);
        $issues = $this->sanitizer->getXmlIssues();
        
        if ($this->logIssues && !empty($issues)) {
            $this->log(
                level: LogLevel::INFO,
                message: sprintf('SVG has sanitizing issues for the file %s.', $path),
                context: ['issues' => $issues],
            );
        }
        
        if (!is_string($cleanSVG)) {
            throw new WriteException(
                message: 'SVG sanitizing failed for the file :path.',
                parameters: [':path' => $path],
            );
        }
        
        return new WriteResponse(path: $path, content: $cleanSVG, originalFilename: $originalFilename);
    }
    
    /**
     * Logs a message if a logger has been provided.
     *
     * This method is a lightweight wrapper around the PSR-3 logger,
     * allowing the writer to perform optional logging without
     * requiring a logger implementation. If no logger is set, the call
     * is silently ignored.
     *
     * @param string $level The PSR-3 log level (use LogLevel::* constants).
     * @param string $message The log message.
     * @param array  $context Additional context passed to the logger.
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        $this->logger?->log($level, $message, $context);
    }
}
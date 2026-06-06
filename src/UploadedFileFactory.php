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

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface as Psr17UploadedFileFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;
use Tobento\Service\Upload\Exception\CreateUploadedFileException;
use Tobento\Service\FileStorage\FileInterface;

class UploadedFileFactory implements UploadedFileFactoryInterface
{
    /**
     * Create a new UploadedFileFactory instance.
     *
     * @param Psr17UploadedFileFactoryInterface $uploadedFileFactory
     * @param StreamFactoryInterface $streamFactory
     * @param ClientInterface $client
     * @param RequestFactoryInterface $requestFactory
     */
    public function __construct(
        protected Psr17UploadedFileFactoryInterface $uploadedFileFactory,
        protected StreamFactoryInterface $streamFactory,
        protected ClientInterface $client,
        protected RequestFactoryInterface $requestFactory,
    ) {}
    
    /**
     * Create uploaded file from the given remote url.
     *
     * @param string $url
     * @return UploadedFileInterface
     * @throws CreateUploadedFileException
     */
    public function createFromRemoteUrl(string $url): UploadedFileInterface
    {
        $request = $this->requestFactory->createRequest('GET', $url);

        try {
            $response = $this->client->sendRequest($request);
        } catch (\Throwable $e) {
            throw new CreateUploadedFileException(
                message: 'Creating uploaded file from remote file :url failed: :error',
                parameters: [':url' => $url, ':error' => $e->getMessage()],
            );
        }

        if ($response->getStatusCode() !== 200) {
            throw new CreateUploadedFileException(
                message: 'Creating uploaded file from remote file :url failed as not found.',
                parameters: [':url' => $url],
            );
        }

        $stream = $response->getBody();

        return $this->uploadedFileFactory->createUploadedFile(
            stream: $stream,
            size: (int) $stream->getSize(),
            clientFilename: pathinfo($url, PATHINFO_BASENAME),
        );
    }
    
    /**
     * Create uploaded file from the given storage file.
     *
     * @param FileInterface $file
     * @return UploadedFileInterface
     * @throws CreateUploadedFileException
     */
    public function createFromStorageFile(FileInterface $file): UploadedFileInterface
    {
        if (is_null($file->stream())) {
            throw new CreateUploadedFileException(
                message: 'Writing storage file :file failed as no stream available.',
                parameters: [':file' => $file->path()],
            );
        }
        
        return $this->uploadedFileFactory->createUploadedFile(
            stream: $file->stream(),
            size: (int) $file->stream()->getSize(),
            clientFilename: $file->name(),
        );
    }
    
    /**
     * Create uploaded file from the given string.
     *
     * @param string $content
     * @param string $clientFilename
     * @param string $clientMediaType
     * @return UploadedFileInterface
     * @throws CreateUploadedFileException
     */
    public function createFromString(
        string $content,
        string $clientFilename,
        string $clientMediaType = 'application/octet-stream'
    ): UploadedFileInterface {
        try {
            $stream = $this->streamFactory->createStream($content);

            return $this->uploadedFileFactory->createUploadedFile(
                stream: $stream,
                size: strlen($content),
                error: \UPLOAD_ERR_OK,
                clientFilename: $clientFilename,
                clientMediaType: $clientMediaType,
            );
        } catch (\Throwable $e) {
            throw new CreateUploadedFileException(
                message: 'Creating uploaded file from string failed: :error',
                parameters: [':error' => $e->getMessage()],
            );
        }
    }
    
    /**
     * Create uploaded file from the given data uri.
     *
     * @param string $uri
     * @return UploadedFileInterface
     * @throws CreateUploadedFileException
     */
    public function createFromDataUri(string $uri): UploadedFileInterface
    {
        if (! str_starts_with($uri, 'data:')) {
            throw new CreateUploadedFileException('Invalid data URI');
        }

        if (! preg_match('/^data:([^,]*),(.*)$/s', $uri, $matches)) {
            throw new CreateUploadedFileException('Malformed data URI');
        }

        $meta = $matches[1];
        $data = $matches[2];

        $parts = explode(';', $meta);
        $mime = $parts[0] ?: 'application/octet-stream';
        $isBase64 = in_array('base64', $parts, true);

        $binary = $isBase64
            ? base64_decode($data, true)
            : urldecode($data);

        if ($binary === false) {
            throw new CreateUploadedFileException('Failed decoding data URI payload');
        }

        return $this->createFromString(
            content: $binary,
            clientFilename: 'imported.bin',
            clientMediaType: $mime,
        );
    }
}
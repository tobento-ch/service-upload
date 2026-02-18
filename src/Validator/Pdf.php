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
 
namespace Tobento\Service\Upload\Validator;

use Psr\Http\Message\UploadedFileInterface;
use Tobento\Service\Upload\Exception\UploadedFileException;

/**
 * Validates uploaded PDF files by extending the base upload validator
 * with PDF‑specific security checks such as header validation and
 * detection of unsafe PDF features (JavaScript, encryption, embedded files).
 */
class Pdf extends General
{
    /**
     * @var array<int, string>
     */    
    protected array $checks = [];
    
    /**
     * Returns the file extensions handled by this specialized PDF validator (lowercase, without dot).
     *
     * @return array<int, string>
     */
    public function supportsExtensions(): array
    {
        return ['pdf'];
    }
    
    /**
     * Validate the uploaded PDF file.
     *
     * @param UploadedFileInterface $file
     * @return void
     *
     * @throws UploadedFileException If the PDF is invalid or unsafe.
     */
    public function validateUploadedFile(UploadedFileInterface $file): void
    {
        // Run base validation first (extension, mime, size, etc.)
        parent::validateUploadedFile($file);
        
        // Run PDF-specific validation
        $this->validatePdfStructure($file);
    }
    
    /**
     * Returns a new instance with the checks to perform.
     *
     * @param string ...$checks
     * @return static
     */
    public function withChecks(string ...$checks): static
    {
        $validChecks = array_keys($this->getAvailableChecks());
        
        foreach($checks as $check) {
            if (!in_array($check, $validChecks, true)) {
                throw new \InvalidArgumentException(sprintf('The check %s is not supported or invalid', $check));
            }
        }
        
        $new = clone $this;
        $new->checks = $checks;
        return $new;
    }
    
    /**
     * Returns the checks to perform.
     *
     * @return array<int, string>
     */
    protected function getChecks(): array
    {
        if (empty($this->checks)) {
            return array_keys($this->getAvailableChecks());
        } 
        
        return $this->checks;
    }

    /**
     * Perform lightweight structural and security checks on the PDF content.
     *
     * @param UploadedFileInterface $file
     * @return void
     * @throws UploadedFileException
     */
    protected function validatePdfStructure(UploadedFileInterface $file): void
    {
        $content = strtolower((string)$file->getStream());

        $available = $this->getAvailableChecks();
        $active = $this->getChecks(); // names like 'js', 'encrypt'

        // Build the actual list of needles to check
        $checks = [];

        foreach ($active as $name) {
            foreach ($available[$name] as $needle => $feature) {
                $checks[$needle] = $feature;
            }
        }

        foreach ($checks as $needle => $feature) {
            if (str_contains($content, $needle)) {
                throw new UploadedFileException(
                    uploadedFile: $file,
                    message: 'PDF contains :feature, which is not allowed.',
                    parameters: [
                        ':feature' => $feature,
                    ],
                );
            }
        }
    }
    
    /**
     * Returns the available checks
     *
     * @return array
     */
    protected function getAvailableChecks(): array
    {
        return [
            'encrypt' => ['/encrypt' => 'encryption'],
            'js' => ['/js' => 'JavaScript', '/javascript' => 'JavaScript'],
            'embedded' => ['/embeddedfile' => 'embedded files'],
            'launch' => ['/launch' => 'launch actions'],
            'openaction' => ['/openaction' => 'OpenAction'],
            'aa' => ['/aa' => 'additional actions (AA)'],
        ];
    }
}
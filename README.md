# Upload Service

The **Upload Service** provides a secure and flexible foundation for handling uploaded files in PHP applications.  
It offers tools for validating incoming files, creating PSR-7 `UploadedFileInterface` instances from various sources, writing files to storage, processing images, and safely copying existing files within a storage system.

The service is designed to be framework-agnostic, fully PSR-compliant, and easy to extend with custom validators, writers, or processing logic.

## Key Capabilities

- **Upload Validators**: Validate uploaded files with specialized rules for general files, CSV, NDJSON, PDFs, ZIP archives, and more.
- **Uploaded File Factory**: Create PSR-7 `UploadedFileInterface` instances from remote URLs or storage files.
- **File Writer**: Safely write uploaded files or streams to any supported [file storage](https://github.com/tobento-ch/service-file-storage).
- **Copy Mode**: Duplicate existing storage files without re-uploading or re-processing.
- **Image Processor**: Apply [Imager actions](https://github.com/tobento-ch/service-imager) such as resizing, orienting, or converting images.
- **Flexibility**: Works with any PSR-7 `StreamInterface` implementation (Nyholm, Guzzle, Laminas, Slim, etc.).
- **Extensibility**: Easily integrate custom validators, writers, or image actions.

## Table of Contents

- [Getting started](#getting-started)
    - [Requirements](#requirements)
- [Documentation](#documentation)
    - [Upload Validators](#upload-validators)
        - [General Validator](#general-validator)
        - [CSV Validator](#csv-validator)
        - [NDJSON Validator](#ndjson-validator)
        - [PDF Validator](#pdf-validator)
        - [SVG Validator](#svg-validator)
        - [ZIP Validator](#zip-validator)
        - [Combine Validator](#combine-validator)
    - [Uploaded File Factory](#uploaded-file-factory)
    - [File Storage Writer](#file-storage-writer)
    - [Writers](#writers)
        - [Image Writer](#image-writer)
        - [SVG Sanitizer Writer](#svg-sanitizer-writer)
    - [Copy Mode (CopyFileWrapper)](#copy-mode-copyfilewrapper)
    - [Image Processor](#image-processor)
- [Credits](#credits)
___

# Getting started

Add the latest version of the upload project running this command.

```
composer require tobento/service-upload
```

## Requirements

- PHP 8.4 or above

# Documentation

## Upload Validators

Upload validators provide a secure and consistent way to inspect incoming files before processing them.  
Each validator operates on a PSR-7 `UploadedFileInterface` instance and applies a focused set of rules to ensure the file is safe, well-formed, and matches your application's expectations.  
Validators can be used individually or combined to support multiple file types with minimal effort.

### General Validator

The general validator validates a given uploaded file against a set of configurable security and consistency rules.

```php
use Tobento\Service\Upload\Validator;
use Tobento\Service\Upload\ValidatorInterface;

$validator = new Validator\General(
    // Allowed file extensions:
    allowedExtensions: ['jpg', 'png', 'gif', 'webp'],

    // Restrict filenames to alphanumeric characters,
    // hyphens, underscores, spaces, and periods:
    strictFilenameCharacters: true, // default

    // Maximum allowed filename length:
    maxFilenameLength: 255, // default

    // Maximum file size in kilobytes (null = unlimited):
    maxFileSizeInKb: 2000,

    // Validate the client-provided media type against the
    // detected mime type (disabled by default):
    validateClientMediaType: true,

    // Validate that the uploaded file is not empty.
    // When enabled, files with size 0 or unknown size are rejected.
    validateNotEmpty: true,
);

var_dump($validator instanceof ValidatorInterface);
// bool(true)
```

**validateUploadedFile**

Use the ```validateUploadedFile``` method to validate the given uploaded file:

```php
use Psr\Http\Message\UploadedFileInterface;
use Tobento\Service\Upload\Exception\UploadedFileException;

try {
    $validator->validateUploadedFile(
        file: $uploadedFile, // UploadedFileInterface
    );
} catch (UploadedFileException $e) {
    // validation failed.
}
```

#### Security

The validator ensures that:

- the file extension is allowed
- the mime type detected from the file's content is allowed
- the client filename extension is consistent with the file's content
- the client media type is consistent with the detected mime type  
  (only if `validateClientMediaType` is enabled)
- the filename contains only alphanumeric characters, hyphens, underscores, spaces, and periods  
  (if `strictFilenameCharacters` is `true`)
- the filename length does not exceed the configured `maxFilenameLength`
- the file size does not exceed the configured `maxFileSizeInKb`  
  (default: `null` = unlimited)

Once the uploaded file is validated and accepted, you can rely on:

- `$uploadedFile->getClientMediaType()` being allowed and consistent with the file content  
  (if strict client media type validation is enabled)
- `$uploadedFile->getClientFilename()` having a valid and consistent extension

The only remaining responsibility is verifying the filename itself, excluding the extension:

```php
$filename = $uploadedFile->getClientFilename();

$extension = pathinfo($filename, PATHINFO_EXTENSION);
// is valid as verified
```

If you use the [File Storage Writer](#file-storage-writer) to store files, ensure the ```filenames``` parameter is configured safely.

```php
use Tobento\Service\Upload\FileStorageWriter;

$fileStorageWriter = new FileStorageWriter(
    filenames: FileStorageWriter::ALNUM,
    
    // or
    filenames: FileStorageWriter::RENAME,
    
    // or
    filenames: function (string $filename): string {
        // verify filename!
        return $verifiedFilename;
    },
);
```

**File Storage Location**

Always store uploaded files outside the webroot or on a separate host.  
If you use the [File Storage Writer](#file-storage-writer), ensure the configured `storage` location is outside the webroot - such as the default ```uploads-private``` or ```uploads-public``` storage.

**Resources**

For further guidance on secure file uploads, refer to:  
[File Upload Cheatsheet - owasp.org](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html).

### CSV Validator

The CSV validator extends the [general validator](#general-validator) with additional CSV-specific security checks.  
It ensures that uploaded CSV files are structurally valid, safe to process, and free from spreadsheet-formula injection.

```php
use Tobento\Service\Upload\Exception\UploadedFileException;
use Tobento\Service\Upload\Validator;
use Tobento\Service\Upload\ValidatorInterface;

$validator = new Validator\Csv(
    allowedExtensions: ['csv'],
);

// Disable deep CSV content validation if needed returning a new instance:
$validator = $validator->withValidateCsvContent(false);

var_dump($validator instanceof ValidatorInterface);
// bool(true)

try {
    $validator->validateUploadedFile($uploadedFile);
} catch (UploadedFileException $e) {
    // CSV validation failed.
}
```

**CSV-Specific Security**

The CSV validator ensures:
- the file extension is csv
- the detected mime type is one of the allowed CSV mime types: text/csv, text/plain, application/csv, application/vnd.ms-excel
- the CSV can be parsed line-by-line
- all rows have a consistent number of columns
- no cell begins with =, +, -, or @ (prevents spreadsheet formula injection)
- UTF-8 BOM is handled correctly
- empty lines are ignored safely

### NDJSON Validator

The NDJSON validator extends the [general validator](#general-validator) with line-by-line JSON validation.  
It ensures that uploaded NDJSON files contain **one valid JSON object per line**, ignore empty lines, and safely reject malformed entries.

```php
use Tobento\Service\Upload\Exception\UploadedFileException;
use Tobento\Service\Upload\Validator;
use Tobento\Service\Upload\ValidatorInterface;

$validator = new Validator\Ndjson(
    allowedExtensions: ['ndjson'],
);

var_dump($validator instanceof ValidatorInterface);
// bool(true)

try {
    $validator->validateUploadedFile($uploadedFile);
} catch (UploadedFileException $e) {
    // NDJSON validation failed.
}
```

### PDF Validator

The PDF validator extends the [general validator](#general-validator) with additional PDF-specific security checks.  
It ensures that uploaded PDF files are structurally safe by detecting features commonly used for malicious behavior, such as JavaScript, embedded files, encryption, and auto-execution actions.

```php
use Tobento\Service\Upload\Exception\UploadedFileException;
use Tobento\Service\Upload\Validator;
use Tobento\Service\Upload\ValidatorInterface;

// All PDF security checks are enabled by default:
$validator = new Validator\Pdf(
    allowedExtensions: ['pdf'],
);

// Enable only specific checks (returns a new immutable instance):
$validator = $validator->withChecks(
    'encrypt',
    'js',
    'embedded',
    'launch',
    'openaction',
    'aa',
);

var_dump($validator instanceof ValidatorInterface);
// bool(true)

try {
    $validator->validateUploadedFile($uploadedFile);
} catch (UploadedFileException $e) {
    // PDF validation failed.
}
```

### SVG Validator

The SVG validator extends the [general validator](#general-validator) with additional SVG-specific security checks.  
It ensures that uploaded SVG files are structurally safe by validating XML integrity and detecting features commonly associated with malicious behavior.

Unlike formats such as PDF, SVGs are XML-based and can contain embedded scripts or external references.
This validator uses the excellent [enshrined/svg-sanitize](https://github.com/darylldoyle/svg-sanitizer) library to sanitize and inspect SVG content safely.

**Requirements**

To enable this validator, install:

```
composer require enshrined/svg-sanitize
```

**Example**

```php
use enshrined\svgSanitize\Sanitizer;
use Tobento\Service\Upload\Exception\UploadedFileException;
use Tobento\Service\Upload\Validator;
use Tobento\Service\Upload\ValidatorInterface;

// Create a custom sanitizer (optional)
$sanitizer = new Sanitizer();
$sanitizer->removeXMLTag(true);
$sanitizer->removeRemoteReferences(true);
// $sanitizer->minify(true); // optional

$validator = new Validator\Svg(
    allowedExtensions: ['svg'],

    // You may pass a custom sanitizer:
    sanitizer: $sanitizer, // default is: new Sanitizer()
);

var_dump($validator instanceof ValidatorInterface);
// bool(true)

try {
    $validator->validateUploadedFile($uploadedFile);
} catch (UploadedFileException $e) {
    // SVG validation failed.
    echo $e->getMessage();
}
```

**Note:** If you only need to sanitize SVG files before saving them, consider using the [SVG Sanitizer Writer](#svg-sanitizer-writer). The SVG validator is still recommended when you want to validate uploads and reject malformed or unsafe SVGs early.

### ZIP Validator

The ZIP validator extends the [general validator](#general-validator) with archive-specific security checks.  
It ensures that uploaded ZIP files are safe to extract, structurally valid, and free from common archive-based attack vectors such as ZIP bombs, directory traversal, and excessive nesting.

```php
use Tobento\Service\Upload\Exception\UploadedFileException;
use Tobento\Service\Upload\Validator;
use Tobento\Service\Upload\ValidatorInterface;

$validator = new Validator\Zip(
    allowedExtensions: ['zip'],
);

// Configure optional ZIP-specific limits returning a new instance:
$validator = $validator
    ->withMaxEntries(1000) // Maximum number of files inside the ZIP (default: 2000)
    ->withMaxTotalUncompressedBytes(10_000) // Total uncompressed size limit (default: 50_000_000 (50 MB))
    ->withMaxCompressionRatio(20) // Prevent ZIP bombs (default: 200)
    ->withMaxDepth(1); // Maximum nested ZIP depth (default: 3)

var_dump($validator instanceof ValidatorInterface);
// bool(true)

try {
    $validator->validateUploadedFile($uploadedFile);
} catch (UploadedFileException $e) {
    // ZIP validation failed.
}
```

**ZIP-Specific Security Features**

The `ZipValidator` performs several safety checks to ensure uploaded archives are safe to process:

- **Maximum entry count**  
  Prevents ZIP files containing thousands of entries, which can overwhelm extraction routines.

- **Maximum total uncompressed size**  
  Protects against ZIP bombs that expand to massive sizes when extracted.

- **Maximum compression ratio**  
  Detects malicious archives with extreme compression ratios.

- **Directory traversal protection**  
  Blocks unsafe paths such as:
```text
../evil.txt
../../etc/passwd
```

- **Nested ZIP depth**  
  Controls how many layers of ZIP-within-ZIP are allowed. Useful for preventing recursive archive bombs.

- **In-memory nested ZIP validation**  
  Nested ZIPs are validated using an internal in‑memory uploaded file implementation, without writing to disk.

### Upload Combine Validator

The combine validator allows you to register multiple validators and automatically dispatches validation to the first validator that supports the file's extension.

This is ideal when your application accepts multiple file types, each with its own specialized validator.

```php
use Tobento\Service\Upload\Exception\UploadedFileException;
use Tobento\Service\Upload\Validator;
use Tobento\Service\Upload\ValidatorInterface;

$validator = new Validator\Combine(
    new Validator\Csv(allowedExtensions: ['csv']), // handles .csv
    new Validator\General(allowedExtensions: ['jpg', 'png']), // fallback for all other extensions
);

var_dump($validator instanceof ValidatorInterface);
// bool(true)

try {
    $validator->validateUploadedFile($uploadedFile);
} catch (UploadedFileException $e) {
    // no matching validator or validation failed
}
```

## Uploaded File Factory

The uploaded file factory creates PSR-7 `UploadedFileInterface` instances from different resources such as remote URLs or storage files.

```php
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface as Psr17UploadedFileFactoryInterface;
use Tobento\Service\Upload\UploadedFileFactory;
use Tobento\Service\Upload\UploadedFileFactoryInterface;

$factory = new UploadedFileFactory(
    uploadedFileFactory: $uploadedFileFactory, // Psr17UploadedFileFactoryInterface
    streamFactory: $streamFactory, // StreamFactoryInterface
    client: $client, // ClientInterface (PSR-18)
    requestFactory: $requestFactory, // RequestFactoryInterface (PSR-17)
);

var_dump($factory instanceof UploadedFileFactoryInterface);
// bool(true)
```

**createFromRemoteUrl**

Creates an uploaded file by downloading the content from a remote URL using a PSR-18 HTTP client.

```php
use Psr\Http\Message\UploadedFileInterface;
use Tobento\Service\Upload\Exception\CreateUploadedFileException;

try {
    $uploadedFile = $factory->createFromRemoteUrl(
        url: 'https://example.com/image.jpg' // string
    );
    
    var_dump($uploadedFile instanceof UploadedFileInterface);
    // bool(true)
} catch (CreateUploadedFileException $e) {
    // creating uploaded file failed.
}
```

If the remote request fails or the response status code is not 200, a `CreateUploadedFileException` is thrown.

**createFromStorageFile**

Creates an uploaded file from a [Storage File](https://github.com/tobento-ch/service-file-storage#file-interface).

This feature is optional. To use it, install the `file-storage` package:

```
composer require tobento/service-file-storage
```

```php
use Psr\Http\Message\UploadedFileInterface;
use Tobento\Service\Upload\Exception\CreateUploadedFileException;
use Tobento\Service\FileStorage\FileInterface;

try {
    $uploadedFile = $factory->createFromStorageFile(
        file: $file // FileInterface
    );
    
    var_dump($uploadedFile instanceof UploadedFileInterface);
    // bool(true)
} catch (CreateUploadedFileException $e) {
    // creating uploaded file failed.
}
```

If the storage file does not provide a stream, a `CreateUploadedFileException` is thrown.

**createFromString**

Creates an uploaded file from a raw string.  
Useful when you already have the file contents in memory (for example, generated files or decoded binary data).

```php
use Psr\Http\Message\UploadedFileInterface;
use Tobento\Service\Upload\Exception\CreateUploadedFileException;

try {
    $uploadedFile = $factory->createFromString(
        content: 'file content', // string (binary-safe)
        clientFilename: 'imported.bin', // string
        clientMediaType: 'application/octet-stream', // string
    );
    
    var_dump($uploadedFile instanceof UploadedFileInterface);
    // bool(true)
} catch (CreateUploadedFileException $e) {
    // creating uploaded file failed.
}
```

If creating the stream or uploaded file fails, a `CreateUploadedFileException` is thrown.

**createFromDataUri**

Creates an uploaded file from a Data URI such as:

`data:text/plain;base64,SGVsbG8=`  
or  
`data:text/plain,Hello%20World`

Supports both:

- Base64-encoded data URIs  
- URL-encoded (non-base64) data URIs  

The MIME type is extracted from the data URI, and the filename defaults to `imported.bin`.

```php
use Psr\Http\Message\UploadedFileInterface;
use Tobento\Service\Upload\Exception\CreateUploadedFileException;

try {
    $uploadedFile = $factory->createFromDataUri(
        uri: 'data:text/plain;base64,SGVsbG8=' // string
    );
    
    var_dump($uploadedFile instanceof UploadedFileInterface);
    // bool(true)
} catch (CreateUploadedFileException $e) {
    // creating uploaded file failed.
}
```

If the data URI is invalid, malformed, or cannot be decoded, a `CreateUploadedFileException` is thrown.

## File Storage Writer

The file storage writer writes the given file to the defined [File Storage](https://github.com/tobento-ch/service-file-storage).  
Before writing, you may use [upload validators](#upload-validators) to ensure the file meets your requirements (e.g., type, size, or structure).  
[Writers](#writers) can also be used to sanitize or process files before they are stored, allowing you to modify or transform the file content as needed.

**Requirements**

This feature is optional. To enable the file writer, install:

```
composer require tobento/service-file-storage tobento/service-message
```

**Example**

```php
use Tobento\Service\Upload\FileStorageWriter;
use Tobento\Service\Upload\FileStorageWriterInterface;
use Tobento\Service\Upload\Writer;
use Tobento\Service\Upload\ImageProcessor;
use Tobento\Service\FileStorage\StorageInterface;

$fileStorageWriter = new FileStorageWriter(
    // Define the file storage where to write the files to:
    storage: $storage, // StorageInterface
    
    // Define how filenames should be handled:
    filenames: FileStorageWriter::ALNUM, // RENAME, ALNUM, KEEP
    
    // Or using a closure for customized filenames:
    filenames: function (string $filename): string {
        // customize
        return $filename;
    },
    
    // Define how duplicates should be handled:
    duplicates: FileStorageWriter::RENAME, // RENAME, OVERWRITE, DENY
    
    // Define how folders should be handled:
    folders: FileStorageWriter::ALNUM, // or KEEP
    
    // Or using a closure for customized folders:
    folders: function (string $path): string {
        // customize
        return $path;
    },
    
    // Define the max folder depth limit:
    folderDepthLimit: 5,

    // Add writers handling specific file types:
    writers: [
        new Writer\Image(
            imageProcessor: new ImageProcessor(
                actions: [
                    'orientate' => [],
                    'resize' => ['width' => 2000],
                ],
            ),
        ),
        new Writer\SvgSanitizer(),
    ],
);

var_dump($fileStorageWriter instanceof FileStorageWriterInterface);
// bool(true)
```

Check out the [Available Writers](#writers) section for details on each writer and their requirements.

**writeFromStream**

Use the ```writeFromStream``` method to write the given stream to the file storage:

```php
use Psr\Http\Message\StreamInterface;
use Tobento\Service\Upload\Exception\WriteException;
use Tobento\Service\Upload\WriteResponseInterface;

$writeResponse = $fileStorageWriter->writeFromStream(
    stream: $stream, // StreamInterface
    filename: 'file.txt',
    folderPath: 'path/to', // or an empty string if no path at all
);

var_dump($writeResponse instanceof WriteResponseInterface);
// bool(true)

// throws WriteException if writing failed!
```

**writeUploadedFile**

Use the ```writeUploadedFile``` method to write the given uploaded file to the file storage:

```php
use Psr\Http\Message\UploadedFileInterface;
use Tobento\Service\Upload\Exception\WriteException;
use Tobento\Service\Upload\WriteResponseInterface;

$writeResponse = $fileStorageWriter->writeUploadedFile(
    file: $uploadedFile, // UploadedFileInterface
    folderPath: 'path/to', // or an empty string if no path at all
);

var_dump($writeResponse instanceof WriteResponseInterface);
// bool(true)

// throws WriteException if writing failed!
```

It is highly recommended to use the [Upload Validator](#upload-validator) before writing the uploaded file to the file storage.

**copyFile**

Use the `copyFile` method to copy an existing file inside the same file storage to a new folder.
This is useful when selecting files from a file manager or when you want to duplicate files without re-uploading or re-processing them.  
This method does not run any writers.

```php
use Tobento\Service\Upload\Exception\WriteException;
use Tobento\Service\Upload\WriteResponseInterface;

$writeResponse = $fileStorageWriter->copyFile(
    path: 'foo/image.jpg', // existing file path inside the storage
    folderPath: 'path/to', // target folder, or an empty string for root
);

// Result: 'path/to/image.jpg'
// Note: copyFile() does NOT preserve the source folder structure.

var_dump($writeResponse instanceof WriteResponseInterface);
// bool(true)

// throws WriteException if copying failed!
```

This method performs a storage-level copy (e.g. local to local, S3 to S3) without reading streams or applying any image processing.
It is ideal for file-manager selections or fast, lossless duplication.

**writeResponse**

```php
use Psr\Http\Message\UploadedFileInterface;
use Tobento\Service\Upload\WriteResponseInterface;
use Tobento\Service\Message\MessagesInterface;

$writeResponse = $fileStorageWriter->writeUploadedFile(file: $uploadedFile, folderPath: '');

var_dump($writeResponse instanceof WriteResponseInterface);
// bool(true)

// Get the path (string) e.g. path/to/file.txt
$path = $writeResponse->path();

// Get the content (string|\Stringable):
$content = $writeResponse->content();

// Get the original filename (unmodified). Might come from client.
$originalFilename = $writeResponse->originalFilename();

// Get the messages:
$messages = $writeResponse->messages();
// MessagesInterface
```

## Writers

Writers are responsible for sanitizing, processing, or transforming files before they are stored. They can modify file contents, optimize images, sanitize SVGs, or perform other processing tasks depending on the writer implementation.

Writers are typically used by the [File Storage Writer](#file-storage-writer), which selects the appropriate writer based on the file type before writing the file to storage.  
However, writers may also be used as standalone components when you need to process files independently of the storage workflow.

### Image Writer

The Image Writer applies image transformations such as orientation correction, resizing, or any other actions supported by the underlying [Image Processor](#image-processor).

**Requirements**

To enable this writer, install:

```
composer require tobento/service-imager
```

**Example**

```php
use Psr\Http\Message\StreamInterface;
use Tobento\Service\Upload\Exception\WriteException;
use Tobento\Service\Upload\ImageProcessor;
use Tobento\Service\Upload\Writer;
use Tobento\Service\Upload\WriteResponseInterface;

$writer = new Writer\Image(
    imageProcessor: new ImageProcessor(
        actions: [
            'orientate' => [],
            'resize' => ['width' => 2000],
        ],
    ),
);

var_dump($writer instanceof Writer\WriterInterface);
// bool(true)

try {
    $response = $writer->write(
        path: 'path/file.svg',
        stream: $stream, // StreamInterface
        originalFilename: 'file.svg',
    );
    
    // $response is either:
    // - WriteResponseInterface (supported)
    // - null (not supported)
} catch (WriteException $e) {
    // handle the exception as needed
}
```

### SVG Sanitizer Writer

The SVG Sanitizer Writer handles `.svg` files by sanitizing their XML markup using a dedicated SVG sanitizing library.  
This helps prevent security issues such as embedded scripts or malicious attributes, making SVG uploads safer for display in browsers.

**Requirements**

To enable this writer, install:

```
composer require enshrined/svg-sanitize
```

**Example**

```php
use Psr\Http\Message\StreamInterface;
use Tobento\Service\Upload\Exception\WriteException;
use Tobento\Service\Upload\Writer;
use Tobento\Service\Upload\WriteResponseInterface;

$writer = new Writer\SvgSanitizer(
    // Optional logger:
    //logger: $logger, // null|\Psr\Log\LoggerInterface
);

var_dump($writer instanceof Writer\WriterInterface);
// bool(true)

try {
    $response = $writer->write(
        path: 'path/file.svg',
        stream: $stream, // StreamInterface
        originalFilename: 'file.svg',
    );
    
    // $response is either:
    // - WriteResponseInterface (supported)
    // - null (not supported)
} catch (WriteException $e) {
    // handle the exception as needed
}
```

## Copy Mode (CopyFileWrapper)

Copy mode can be used when you want to copy an existing file inside the same [file storage](https://github.com/tobento-ch/app-file-storage) instead of uploading a new one.  
A `CopyFileWrapper` contains:

- the original `UploadedFileInterface` (metadata only)
- the storage name where the file currently exists
- the path of the file inside that storage
    
```php
use Tobento\Service\Upload\CopyFileWrapper;

if ($inputFile instanceof CopyFileWrapper) {
    $writeResponse = $writer->copyFile(
        sourcePath: $inputFile->path(),
        folderPath: $folderPath,
    );
} else {
    $writeResponse = $writer->writeUploadedFile($inputFile, $folderPath);
}
```

## Image Processor

The image processor applies the configured Imager actions to an image stream or resource, using the underlying [Imager Service](https://github.com/tobento-ch/service-imager).

You can find all available Imager actions in the [Imager Actions documentation](https://github.com/tobento-ch/service-imager#action).

```php
use Tobento\Service\Upload\ImageProcessor;
use Tobento\Service\Upload\ImageProcessorInterface;
use Tobento\Service\Imager\Action;
use Tobento\Service\Imager\ActionFactoryInterface;

$imageProcessor = new ImageProcessor(
    // Define the imager actions to be processed:
    actions: [
        'orientate' => [],
        'resize' => ['width' => 300],
        new Action\Contrast(20),
    ],
    
    // Allowed actions (only these may be executed).
    // If empty, all actions are allowed unless listed in disallowedActions.    
    allowedActions: [
        Action\Greyscale::class,
    ],
    
    // Disallowed actions (these will be skipped):
    disallowedActions: [
        Action\Colorize::class,
    ],
    
    // Convert certain image types (e.g. PNG → JPEG):
    convert: ['image/png' => 'image/jpeg'],
    
    // Adjust image quality:
    quality: ['image/jpeg' => 90, 'image/webp' => 90],
    
    // Supported mime types:
    supportedMimeTypes: ['image/png', 'image/jpeg', 'image/gif'], // default
    
    // Custom action factory:
    //actionFactory: $customActionFactory, // ActionFactoryInterface
    
    // Optional logger:
    //logger: $logger, // null|\Psr\Log\LoggerInterface
);

var_dump($imageProcessor instanceof ImageProcessorInterface);
// bool(true)

// Use the following methods to modify the image processor returning a new instance:
$imageProcessor = $imageProcessor->withActions([
    'resize' => ['width' => 300],
]);

$imageProcessor = $imageProcessor->withConvert([
    'image/png' => 'image/jpeg',
]);

$imageProcessor = $imageProcessor->withQuality([
    'image/jpeg' => 90,
    'image/webp' => 90,
]);
```

**processFromResource**

Use the ```processFromResource``` method to process the given resource:

```php
use Tobento\Service\Upload\Exception\ImageProcessException;
use Tobento\Service\Imager\ResourceInterface;
use Tobento\Service\Imager\Response\Encoded;

$encoded = $imageProcessor->processFromResource(
    resource: $resource, // ResourceInterface
);

var_dump($encoded instanceof Encoded);
// bool(true)

// throws ImageProcessException if image cannot get processed!
```

Check out the [Resource](https://github.com/tobento-ch/service-imager#resource) and [Encoded](https://github.com/tobento-ch/service-imager#encoded-response) documentation to learn more.

**processFromStream**

Use the ```processFromStream``` method to process the given stream:

```php
use Psr\Http\Message\StreamInterface;
use Tobento\Service\Upload\Exception\ImageProcessException;
use Tobento\Service\Imager\Response\Encoded;

$encoded = $imageProcessor->processFromStream(
    stream: $stream, // StreamInterface
);

var_dump($encoded instanceof Encoded);
// bool(true)

// throws ImageProcessException if image cannot get processed!
```

Check out the [Encoded](https://github.com/tobento-ch/service-imager#encoded-response) documentation to learn more.

# Credits

- [Tobias Strub](https://www.tobento.ch)
- [All Contributors](../../contributors)
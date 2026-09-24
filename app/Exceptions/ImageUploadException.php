<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown by fileUploader() when an uploaded image cannot be processed
 * (corrupted, truncated or unsupported file). Handled globally in
 * bootstrap/app.php (->withExceptions) so the request aborts before any data
 * is persisted and the user is told why the upload failed.
 */
class ImageUploadException extends Exception
{
    public function __construct(string $message = '')
    {
        parent::__construct($message !== '' ? $message : IMAGE_UPLOAD_FAILED_422['message']);
    }
}

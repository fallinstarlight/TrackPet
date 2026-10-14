<?php

declare(strict_types=1);

namespace TrackPet\utils;

use RuntimeException;

class Upload
{
    /** Max file size in bytes (5 MB). */
    private const MAX_SIZE = 5 * 1024 * 1024;

    /** Maximum pixel dimensions (width or height). */
    private const MAX_DIMENSION = 5000;

    private const ALLOWED_FORMATS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * MIME types getimagesize() is allowed to report for an image upload.
     * SVG is intentionally excluded: it is XML, can carry scripts, and
     * getimagesize() cannot validate it.
     */
    private const ALLOWED_MIME = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    private string $uploadDir;
    private string $originalName;
    private string $tmpPath;
    private int    $size;
    private string $extension;

    public function __construct(
        private string $fieldName = 'fileToUpload',
        ?string $uploadDir = null,
    ) {
        $this->uploadDir = $uploadDir
            ?? dirname(__DIR__, 2) . '/public/assets/img/uploads/';

        if (!isset($_FILES[$fieldName])) {
            throw new RuntimeException("No file received in field '$fieldName'.");
        }

        $file = $_FILES[$fieldName];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException(
                'Upload failed with error code ' . $file['error'] . '.'
            );
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('Invalid upload source.');
        }

        $this->originalName = $file['name'];
        $this->tmpPath      = $file['tmp_name'];
        $this->size         = (int) $file['size'];
        $this->extension    = strtolower(pathinfo($this->originalName, PATHINFO_EXTENSION));
    }

    /**
     * Validate the upload and, on success, move the file into place.
     * Returns the generated filename on success.
     *
     * @throws RuntimeException on any validation or move failure.
     */
    public function publish(): string
    {
        $this->checkExtension();
        $this->checkSize();
        $this->checkIsRealImage();

        if (!is_dir($this->uploadDir) && !mkdir($this->uploadDir, 0755, true) && !is_dir($this->uploadDir)) {
            throw new RuntimeException('Upload directory is not writable.');
        }

        $filename = $this->generateFilename();
        $destination = $this->uploadDir . $filename;

        if (!move_uploaded_file($this->tmpPath, $destination)) {
            throw new RuntimeException('Failed to move uploaded file into place.');
        }

        return $filename;
    }

    private function checkExtension(): void
    {
        if (!in_array($this->extension, self::ALLOWED_FORMATS, true)) {
            throw new RuntimeException(
                "File type '.{$this->extension}' is not allowed."
            );
        }
    }

    private function checkSize(): void
    {
        if ($this->size <= 0) {
            throw new RuntimeException('Uploaded file is empty.');
        }
        if ($this->size > self::MAX_SIZE) {
            throw new RuntimeException(
                'File exceeds the maximum allowed size of ' . self::MAX_SIZE . ' bytes.'
            );
        }
    }

    private function checkIsRealImage(): void
    {
        $info = @getimagesize($this->tmpPath);

        if ($info === false) {
            throw new RuntimeException('File is not a valid image.');
        }

        [$width, $height, $mime] = $info;

        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            throw new RuntimeException("Image MIME type '$mime' is not allowed.");
        }

        if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            throw new RuntimeException('Image dimensions are too large.');
        }
    }

    private function generateFilename(): string
    {
        return bin2hex(random_bytes(16)) . '.' . $this->extension;
    }
}

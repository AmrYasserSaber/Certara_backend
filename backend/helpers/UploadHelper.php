<?php

declare(strict_types=1);

namespace App\Helpers;

final class UploadHelper
{
    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB

    public static function saveFile(array $file, string $directory): string
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('doc_', true) . '.' . $extension;
        $path = rtrim($directory, '/') . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $path)) {
            throw new \RuntimeException('Failed to move uploaded file.');
        }

        return $path;
    }

    public static function deleteFile(string $path): bool
    {
        if (file_exists($path)) {
            return unlink($path);
        }
        return false;
    }

    public static function validatePDF(array $file): ?string
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return 'Upload error: ' . $file['error'];
        }

        if ($file['size'] > self::MAX_FILE_SIZE) {
            return 'File size exceeds 10MB limit.';
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if ($mimeType !== 'application/pdf') {
            return 'Only PDF files are allowed.';
        }

        return null;
    }
}

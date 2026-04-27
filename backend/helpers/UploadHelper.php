<?php

declare(strict_types=1);

namespace App\Helpers;

final class UploadHelper
{
    private const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB

    public static function saveFile(array $file, string $directory): string
    {
        $absoluteDirectory = self::resolvePath($directory);
        if (!is_dir($absoluteDirectory)) {
            $didCreateDirectory = mkdir($absoluteDirectory, 0755, true);
            if ($didCreateDirectory === false) {
                throw new \RuntimeException("Failed to create upload directory: {$directory}");
            }
        }
        if (!is_writable($absoluteDirectory)) {
            throw new \RuntimeException("Upload directory is not writable: {$directory}");
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('doc_', true) . '.' . $extension;
        $relativePath = rtrim($directory, '/') . '/' . $filename;
        $absolutePath = self::resolvePath($relativePath);

        if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
            throw new \RuntimeException("Failed to move uploaded file to: {$relativePath}");
        }

        return $relativePath;
    }

    public static function deleteFile(string $path): bool
    {
        $absolutePath = self::resolvePath($path);
        if (file_exists($absolutePath)) {
            return unlink($absolutePath);
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

    private static function resolvePath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }
        $backendRootPath = dirname(__DIR__);
        return rtrim($backendRootPath, '/') . '/' . ltrim($path, '/');
    }
}

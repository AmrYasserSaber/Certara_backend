<?php

declare(strict_types=1);

namespace App\Services;

final class UploadedFileValidator
{
    private const UPLOAD_ERR_MESSAGES = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds server upload_max_filesize.',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds form MAX_FILE_SIZE.',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder on the server.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
    ];

    /**
     * @param array<string,mixed>|null $file
     */
    public function createUploadedFile(string $fieldName, ?array $file): UploadedPhpFile {
        if ($file === null) {
            throw new UploadException('Missing file.', 400, 'missing_file');
        }

        $originalName = (string) ($file['name'] ?? '');
        $tmpName = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        return new UploadedPhpFile(
            fieldName: $fieldName,
            originalName: $originalName,
            tmpName: $tmpName,
            sizeBytes: $size,
            errorCode: $error,
        );
    }

    /**
     * @return array{mimeType:string}
     */
    public function validateUploadedFile(UploadedPhpFile $file, UploadableFile $strategy): array {
        if ($file->errorCode !== UPLOAD_ERR_OK) {
            $iniMaxFileSize = (string) ini_get('upload_max_filesize');
            $iniPostMaxSize = (string) ini_get('post_max_size');
            $message = self::UPLOAD_ERR_MESSAGES[$file->errorCode] ?? 'File upload failed.';
            throw new UploadException('File upload failed.', 400, 'upload_failed', [
                'errorCode' => $file->errorCode,
                'errorMessage' => $message,
                'limits' => [
                    'upload_max_filesize' => $iniMaxFileSize,
                    'post_max_size' => $iniPostMaxSize,
                ],
            ]);
        }

        if ($file->tmpName === '' || !is_uploaded_file($file->tmpName)) {
            throw new UploadException('File upload failed.', 400, 'upload_failed');
        }

        if ($file->sizeBytes <= 0) {
            throw new UploadException('Empty file.', 422, 'empty_file');
        }

        if ($file->sizeBytes > $strategy->getMaxBytes()) {
            throw new UploadException('File is too large.', 413, 'file_too_large', [
                'maxBytes' => $strategy->getMaxBytes(),
                'sizeBytes' => $file->sizeBytes,
            ]);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file->tmpName);
        if (!is_string($mimeType) || $mimeType === '') {
            throw new UploadException('Could not determine file type.', 422, 'unknown_file_type');
        }

        $allowed = $strategy->getAllowedMimeTypes();
        if (!in_array($mimeType, $allowed, true)) {
            throw new UploadException('Invalid file type.', 422, 'invalid_file_type', [
                'mimeType' => $mimeType,
            ]);
        }

        if ($this->shouldValidateImage($mimeType) && @getimagesize($file->tmpName) === false) {
            throw new UploadException('Invalid image file.', 422, 'invalid_image');
        }

        return ['mimeType' => $mimeType];
    }

    private function shouldValidateImage(string $mimeType): bool {
        return str_starts_with($mimeType, 'image/');
    }
}


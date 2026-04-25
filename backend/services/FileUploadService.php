<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Core\Logger;
use App\Models\User;

final class FileUploadService
{
    public function __construct(
        private readonly ImageKitClient $imageKitClient,
        private readonly UploadedFileValidator $validator,
    ) {
    }

    public function upload(Request $request, UploadableFile $strategy, UploadContext $context): UploadedFileResult {
        $startedAt = microtime(true);
        $this->assertAuthorized($request, $strategy, $context);

        $fieldName = $strategy->getFileFieldName();
        $uploadedFile = $this->validator->createUploadedFile($fieldName, $request->file($fieldName));
        $validation = $this->validator->validateUploadedFile($uploadedFile, $strategy);

        $payload = $this->buildUploadPayload($strategy, $context, $uploadedFile);
        $stream = $payload['file'] ?? null;
        $uploadStartedAt = microtime(true);
        try {
            $result = $this->imageKitClient->upload($payload);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $fileId = (string) ($result['fileId'] ?? '');
        $filePath = (string) ($result['filePath'] ?? '');
        $url = (string) ($result['url'] ?? '');
        if ($fileId === '' || $filePath === '' || $url === '') {
            Logger::error('ImageKit upload missing required fields', [
                'result' => $result,
            ]);
            throw new \RuntimeException('Upload failed.');
        }

        Logger::info('File uploaded to ImageKit', [
            'actor_user_id' => $context->actorUserId,
            'category' => $strategy->getCategory(),
            'file_id' => $fileId,
            'file_path' => $filePath,
            'size_bytes' => $uploadedFile->sizeBytes,
            'mime_type' => $validation['mimeType'],
            'is_sensitive' => $strategy->isSensitive(),
            'timings_ms' => [
                'upload' => (int) round((microtime(true) - $uploadStartedAt) * 1000),
                'total' => (int) round((microtime(true) - $startedAt) * 1000),
            ],
        ]);

        return new UploadedFileResult(
            fileId: $fileId,
            filePath: $filePath,
            url: $url,
            originalName: $uploadedFile->originalName,
            sizeBytes: $uploadedFile->sizeBytes,
            mimeType: (string) $validation['mimeType'],
        );
    }

    private function assertAuthorized(Request $request, UploadableFile $strategy, UploadContext $context): void {
        $user = $request->user();
        if (!$user instanceof User) {
            throw new UploadException('Unauthenticated.', 401, 'unauthenticated');
        }

        $userId = (int) $user->id;
        if ($userId <= 0 || $userId !== $context->actorUserId) {
            throw new UploadException('Forbidden.', 403, 'forbidden');
        }

        if ($strategy->getCategory() === 'research_attachment' && $context->researchId === null) {
            throw new UploadException('Invalid research target.', 400, 'invalid_research_target');
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function buildUploadPayload(UploadableFile $strategy, UploadContext $context, UploadedPhpFile $file): array {
        $maxBytes = $strategy->getMaxBytes();
        $checks = $strategy->getChecksExpression();
        if ($checks === null) {
            $checks = sprintf('"file.size" < "%d"', max(1, $maxBytes));
        }

        $stream = fopen($file->tmpName, 'rb');
        if ($stream === false) {
            throw new \RuntimeException('Could not read uploaded file.');
        }

        try {
            $payload = [
                'file' => $stream,
                'fileName' => $strategy->getSafeFileName($context, $file),
                'folder' => $this->normalizeFolder($strategy->getFolderPath($context)),
                'useUniqueFileName' => (bool) env('IMAGEKIT_USE_UNIQUE_FILE_NAME', true),
                'checks' => $checks,
            ];

            $transformations = $strategy->getTransformations();
            if ($transformations !== []) {
                $payload['transformation'] = $transformations;
            }

            return $payload;
        } catch (\Throwable $err) {
            fclose($stream);
            throw $err;
        }
    }

    private function normalizeFolder(string $folder): string {
        $root = (string) env('IMAGEKIT_FOLDER_ROOT', '/irb');
        $root = '/' . trim($root, '/');
        $folder = '/' . trim($folder, '/');

        if ($folder === '/') {
            return $root;
        }

        if (!str_starts_with($folder, $root . '/')) {
            return rtrim($root, '/') . $folder;
        }

        return $folder;
    }

}

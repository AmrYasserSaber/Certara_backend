<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use ImageKit\ImageKit;
use InvalidArgumentException;

final class ImageKitClient {
    private ImageKit $client;
    private string $urlEndpoint;

    public function __construct() {
        $publicKey = (string) env('IMAGEKIT_PUBLIC_KEY', '');
        $privateKey = (string) env('IMAGEKIT_PRIVATE_KEY', '');
        $urlEndpoint = (string) env('IMAGEKIT_URL_ENDPOINT', '');

        if ($publicKey === '' || $privateKey === '' || $urlEndpoint === '') {
            throw new \RuntimeException('ImageKit configuration is missing.');
        }

        $this->urlEndpoint = $urlEndpoint;
        $this->client = new ImageKit($publicKey, $privateKey, $urlEndpoint);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function upload(array $payload): array {
        $response = $this->client->uploadFile($payload);

        if (!is_object($response)) {
            Logger::error('ImageKit upload unexpected response', ['type' => gettype($response)]);
            throw new \RuntimeException('Image upload failed.');
        }

        if (($response->error ?? null) !== null) {
            Logger::warning('ImageKit upload failed', [
                'error' => $response->error,
            ]);
            throw new \RuntimeException('Image upload failed.');
        }

        $result = $response->result ?? null;
        if (!is_object($result)) {
            Logger::error('ImageKit upload missing result', [
                'type' => gettype($result),
            ]);
            throw new \RuntimeException('Image upload failed.');
        }

        /** @var array<string,mixed> $uploaded */
        $uploaded = (array) $result;
        return $uploaded;
    }

    public function deleteByFileId(string $fileId, bool $suppressExceptions = false): bool {
        if ($fileId === '') {
            return false;
        }

        try {
            $response = $this->client->deleteFile($fileId);
            if (is_object($response) && ($response->error ?? null) !== null) {
                Logger::warning('ImageKit delete failed', [
                    'file_id' => $fileId,
                    'error' => $response->error,
                ]);
                return false;
            }
            return true;
        } catch (\Throwable $err) {
            if (!$suppressExceptions) {
                throw $err;
            }
            Logger::warning('ImageKit delete threw exception', [
                'file_id' => $fileId,
                'message' => $err->getMessage(),
            ]);
            return false;
        }
    }

    public function buildSignedUrl(string $filePath, int $expireSeconds): string {
        if ($expireSeconds <= 0) {
            throw new InvalidArgumentException('Signed URL expiry must be a positive integer.');
        }
        $filePath = '/' . ltrim($filePath, '/');
        $url = $this->client->url([
            'urlEndpoint' => $this->urlEndpoint,
            'path' => $filePath,
            'signed' => true,
            'expireSeconds' => $expireSeconds,
        ]);

        if (!is_string($url) || $url === '') {
            throw new \RuntimeException('Could not generate signed URL.');
        }

        return $url;
    }
}


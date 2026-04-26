<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Models\Research;
use App\Models\Document;
use App\Models\Review;
use App\Helpers\UploadHelper;
use App\Enums\Roles;
use App\Enums\ResearchStatus;
use App\Services\FileUploadService;
use App\Services\ImageKitClient;
use App\Services\UploadContext;
use App\Services\UploadedFileValidator;
use App\Services\UploadException;
use App\Services\UploadStrategies\ResearchDocumentUpload;

final class DocumentController extends Controller
{
    public function store(Request $request): never
    {
        $researchId = (int) $request->param('id');
        $studentId = $request->user()->id;
        
        $research = Research::where('student_id', $studentId)->find($researchId);
        if (!$research) {
            $this->fail('Research not found.', 404, 'not_found');
        }

        $type = $request->input('type', 'protocol');
        
        $files = $_FILES['documents'] ?? null;
        if (!$files) {
            $this->fail('No documents uploaded.', 400, 'missing_files');
        }

        $uploadedDocuments = [];
        $errors = [];

        $normalizedFiles = $this->normalizeFiles($files);
        $uploadService = $this->buildFileUploadService();
        $context = new UploadContext(actorUserId: $studentId, researchId: $researchId);

        foreach ($normalizedFiles as $index => $file) {
            $fileType = $file['key'] ?? $type; // Use the key from FormData if available

            try {
                $result = $uploadService->uploadFromFileArray(
                    request: $request,
                    strategy: new ResearchDocumentUpload((string) $fileType),
                    context: $context,
                    file: $file,
                );

                $uploadedDocuments[] = Document::create([
                    'research_id'   => $researchId,
                    'type'          => $fileType,
                    'file_id'       => $result->fileId,
                    'file_path'     => $result->filePath,
                    'file_url'      => $result->url,
                    'original_name' => $result->originalName,
                    'size_bytes'    => $result->sizeBytes,
                    'mime_type'     => $result->mimeType,
                ]);
            } catch (UploadException $err) {
                $errors[] = "File {$index}: " . $err->getMessage();
            } catch (\Exception $e) {
                $errors[] = "File {$index}: " . $e->getMessage();
            }
        }

        if ($errors !== [] && $uploadedDocuments === []) {
            $this->fail('Upload failed.', 422, 'upload_error', $errors);
        }

        $this->created([
            'documents' => $uploadedDocuments,
            'errors'    => $errors ?: null
        ]);
    }

    public function index(Request $request): never
    {
        $researchId = (int) $request->param('id');
        $userId = (int) ($request->user()->id ?? 0);
        $role = (string) ($request->user()->role ?? '');

        if ($role === Roles::REVIEWER) {
            $review = Review::findByResearchAndReviewer($researchId, $userId);
            if ($review === false) {
                $this->fail('Research not found.', 404, 'not_found');
            }

            $research = Research::find($researchId);
        } else {
            $research = Research::where('student_id', $userId)->find($researchId);
        }

        if (!$research) {
            $this->fail('Research not found.', 404, 'not_found');
        }

        $this->ok($research->documents);
    }

    public function destroy(Request $request): never
    {
        $researchId = (int) $request->param('id');
        $docId = (int) $request->param('doc_id');
        $studentId = (int) $request->user()->id;

        $research = Research::where('student_id', $studentId)->find($researchId);
        if (!$research) {
            $this->fail('Research not found.', 404, 'not_found');
        }

        if ($research->status !== ResearchStatus::DRAFT) {
            $this->fail('Documents can only be deleted if the research is in DRAFT status.', 403, 'forbidden');
        }

        $document = Document::where('research_id', $researchId)->find($docId);
        if (!$document) {
            $this->fail('Document not found.', 404, 'not_found');
        }

        $fileId = (string) ($document->file_id ?? '');
        if ($fileId !== '') {
            $client = new ImageKitClient();
            $client->deleteByFileId($fileId, suppressExceptions: true);
        } else {
            UploadHelper::deleteFile((string) $document->file_path);
        }
        $document->delete();

        $this->ok(['message' => 'Document deleted successfully.']);
    }

    public function signedUrl(Request $request): never
    {
        $researchId = (int) $request->param('id');
        $docId = (int) $request->param('doc_id');
        $user = $request->user();
        $userId = (int) ($user->id ?? 0);
        $role = (string) ($user->role ?? '');

        if ($researchId <= 0 || $docId <= 0) {
            $this->fail('Invalid research or document id.', 422, 'validation_error');
        }

        if ($userId <= 0 || $role === '') {
            $this->fail('Unauthenticated.', 401, 'unauthenticated');
        }

        $document = Document::query()
            ->where('research_id', $researchId)
            ->find($docId);
        if ($document === null) {
            $this->fail('Document not found.', 404, 'not_found');
        }

        $this->assertCanAccessResearchDocuments(role: $role, userId: $userId, researchId: $researchId);

        $filePath = (string) ($document->file_path ?? '');
        if ($filePath === '') {
            $this->fail('Document file path is missing.', 500, 'missing_file_path');
        }

        $expireSeconds = (int) env('IMAGEKIT_SIGNED_URL_EXPIRE_SECONDS', 300);
        $client = new ImageKitClient();
        try {
            $signedUrl = $client->buildSignedUrl($filePath, $expireSeconds);
        } catch (\Throwable $err) {
            $this->fail('Could not generate signed URL.', 500, 'signed_url_failed', [
                'reason' => $err->getMessage(),
            ]);
        }

        $this->ok([
            'documentId' => (int) $document->id,
            'researchId' => $researchId,
            'expiresIn' => $expireSeconds,
            'url' => $signedUrl,
        ]);
    }

    private function normalizeFiles(array $files): array
    {
        $normalized = [];

        // Check if $files['name'] is an array (multiple files)
        if (isset($files['name']) && is_array($files['name'])) {
            foreach ($files['name'] as $key => $name) {
                if (empty($name)) continue;
                $normalized[] = [
                    'key'      => $key, // This will be 'protocol', 'consent', etc.
                    'name'     => $files['name'][$key],
                    'type'     => $files['type'][$key],
                    'tmp_name' => $files['tmp_name'][$key],
                    'error'    => $files['error'][$key],
                    'size'     => $files['size'][$key],
                ];
            }
        } elseif (isset($files['name'])) {
            $normalized[] = $files;
        }

        return $normalized;
    }

    private function buildFileUploadService(): FileUploadService {
        return new FileUploadService(
            imageKitClient: new ImageKitClient(),
            validator: new UploadedFileValidator(),
        );
    }

    private function assertCanAccessResearchDocuments(string $role, int $userId, int $researchId): void
    {
        if (in_array($role, [Roles::ADMIN, Roles::MANAGER], true)) {
            return;
        }

        if ($role === Roles::STUDENT) {
            $research = Research::where('student_id', $userId)->find($researchId);
            if ($research === null) {
                $this->fail('Research not found.', 404, 'not_found');
            }
            return;
        }

        if ($role === Roles::REVIEWER) {
            $review = Review::findByResearchAndReviewer($researchId, $userId);
            if ($review === false) {
                $this->fail('Forbidden.', 403, 'forbidden');
            }
            return;
        }

        $this->fail('Forbidden.', 403, 'forbidden');
    }
}

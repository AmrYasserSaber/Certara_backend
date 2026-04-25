<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Enums\Roles;
use App\Models\Research;
use App\Models\Document;
use App\Models\Review;
use App\Helpers\UploadHelper;
use App\Enums\ResearchStatus;

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

        // We expect a 'type' for the document(s) - optional if user wants to specify it per request
        // or we could expect an array of files where each might have a type.
        // For simplicity and matching common patterns, we'll assume a single 'type' for this batch
        // or default to 'protocol' if not provided.
        $type = $request->input('type', 'protocol');

        $files = $_FILES['documents'] ?? null;
        if (!$files) {
            $this->fail('No documents uploaded.', 400, 'missing_files');
        }

        $uploadedDocuments = [];
        $errors = [];

        // Normalize files array and use keys as types if they are provided as documents[type]
        $normalizedFiles = $this->normalizeFiles($files);

        foreach ($normalizedFiles as $file) {
            $fileType = $file['key'] ?? $type; // Use the key from FormData if available
            $error = UploadHelper::validatePDF($file);
            if ($error) {
                $errors[] = "File {$index}: {$error}";
                continue;
            }

            try {
                $directory = "uploads/documents/{$researchId}";
                $path = UploadHelper::saveFile($file, $directory);

                $uploadedDocuments[] = Document::create([
                    'research_id'   => $researchId,
                    'type'          => $fileType,
                    'file_path'     => $path,
                    'original_name' => $file['name'],
                    'size_bytes'    => $file['size'],
                ]);
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
        $studentId = $request->user()->id;

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

        UploadHelper::deleteFile($document->file_path);
        $document->delete();

        $this->ok(['message' => 'Document deleted successfully.']);
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
}

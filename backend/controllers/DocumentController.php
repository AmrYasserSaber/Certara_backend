<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Models\Research;
use App\Models\Document;
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

        // Normalize files array if multiple
        $normalizedFiles = $this->normalizeFiles($files);

        foreach ($normalizedFiles as $index => $file) {
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
                    'type'          => $type,
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
        $studentId = $request->user()->id;

        $research = Research::where('student_id', $studentId)->find($researchId);
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
        if (is_array($files['name'])) {
            foreach ($files['name'] as $i => $name) {
                $normalized[] = [
                    'name'     => $files['name'][$i],
                    'type'     => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error'    => $files['error'][$i],
                    'size'     => $files['size'][$i],
                ];
            }
        } else {
            $normalized[] = $files;
        }
        return $normalized;
    }
}

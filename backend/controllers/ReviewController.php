<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\NotificationService;
use App\Models\Review;
use App\Models\ReviewComment;

final class ReviewController extends Controller
{
    public function assigned(Request $request): never
    {
        $reviewerId = (int) ($request->user()->id ?? 0);
        $rows = Review::findByReviewer($reviewerId);

        $data = array_map(fn (array $row): array => $this->stripStudentPII($row), $rows);

        Response::json(['data' => $data], 200);
    }

    public function show(Request $request): never
    {
        $researchId = (int) ($request->param('research_id') ?? 0);
        if ($researchId <= 0) {
            $this->badRequest(['research_id' => 'معرف البحث غير صالح']);
        }

        $reviewerId = (int) ($request->user()->id ?? 0);
        $review = Review::findByResearchAndReviewer($researchId, $reviewerId);

        if ($review === false) {
            Response::json(['error' => 'غير مصرح لك بهذا الإجراء'], 403);
        }

        $research = Database::fetchOne(
            'SELECT id, student_id, title, principal_investigator, co_investigators, department, faculty, serial_number, status, created_at
             FROM research
             WHERE id = ?
             LIMIT 1',
            [$researchId]
        );

        if ($research === null) {
            Response::json(['error' => 'البحث غير موجود'], 404);
        }

        $documents = Database::fetchAll(
            'SELECT id, type, original_name, file_path
             FROM documents
             WHERE research_id = ?
             ORDER BY uploaded_at ASC',
            [$researchId]
        );

        $comments = ReviewComment::findByReview((int) $review['id']);

        Response::json([
            'data' => [
                'research' => $this->stripStudentPII($research),
                'documents' => $documents,
                'comments' => $comments,
                'review' => [
                    'id' => (int) $review['id'],
                    'status' => $review['status'],
                    'decision' => $review['decision'],
                    'decided_at' => $review['decided_at'],
                ],
            ],
        ], 200);
    }

    public function addComment(Request $request): never
    {
        $researchId = (int) ($request->param('research_id') ?? 0);
        if ($researchId <= 0) {
            $this->badRequest(['research_id' => 'معرف البحث غير صالح']);
        }

        $reviewerId = (int) ($request->user()->id ?? 0);
        $review = Review::findByResearchAndReviewer($researchId, $reviewerId);

        if ($review === false) {
            Response::json(['error' => 'غير مصرح لك بهذا الإجراء'], 403);
        }

        $commentText = trim((string) ($request->input('comment_text') ?? ''));
        $details = [];

        if ($commentText === '') {
            $details['comment_text'] = 'نص التعليق مطلوب';
        }

        if (mb_strlen($commentText) > 2000) {
            $details['comment_text'] = 'نص التعليق يجب ألا يتجاوز 2000 حرف';
        }

        if ($details !== []) {
            $this->badRequest($details);
        }

        $comment = ReviewComment::create([
            'review_id' => (int) $review['id'],
            'reviewer_id' => $reviewerId,
            'comment_text' => $commentText,
        ]);

        if ($comment === false) {
            Response::json(['error' => 'تعذر إضافة التعليق حالياً'], 500);
        }

        if (($review['status'] ?? '') === 'assigned') {
            Review::updateStatus((int) $review['id'], 'in_progress');
        }

        Response::json([
            'message' => 'تم إضافة التعليق',
            'data' => $comment,
        ], 201);
    }

    public function submitDecision(Request $request): never
    {
        $researchId = (int) ($request->param('research_id') ?? 0);
        if ($researchId <= 0) {
            $this->badRequest(['research_id' => 'معرف البحث غير صالح']);
        }

        $reviewerId = (int) ($request->user()->id ?? 0);
        $review = Review::findByResearchAndReviewer($researchId, $reviewerId);

        if ($review === false) {
            Response::json(['error' => 'غير مصرح لك بهذا الإجراء'], 403);
        }

        if (($review['status'] ?? '') === 'decided') {
            Response::json(['error' => 'تم تسجيل القرار مسبقاً'], 409);
        }

        $decision = (string) ($request->input('decision') ?? '');
        $finalComment = trim((string) ($request->input('comment') ?? ''));

        $allowedDecisions = ['approved', 'rejected', 'revision_requested'];
        $details = [];

        if (!in_array($decision, $allowedDecisions, true)) {
            $details['decision'] = 'القرار غير صالح';
        }

        if (in_array($decision, ['rejected', 'revision_requested'], true) && $finalComment === '') {
            $details['comment'] = 'يجب إضافة تعليق عند الرفض أو طلب التعديل';
        }

        if ($finalComment !== '' && mb_strlen($finalComment) > 2000) {
            $details['comment'] = 'التعليق النهائي يجب ألا يتجاوز 2000 حرف';
        }

        if ($details !== []) {
            $this->badRequest($details);
        }

        $research = Database::fetchOne(
            'SELECT id, student_id FROM research WHERE id = ? LIMIT 1',
            [$researchId]
        );

        if ($research === null) {
            Response::json(['error' => 'البحث غير موجود'], 404);
        }

        $researchStatus = match ($decision) {
            'approved' => 'reviewer_approved',
            'rejected' => 'rejected',
            'revision_requested' => 'revision_requested',
        };

        Database::transaction(function () use ($review, $decision, $researchStatus, $researchId, $reviewerId, $finalComment): void {
            Review::updateDecision((int) $review['id'], $decision);

            Database::execute(
                'UPDATE research SET status = ?, updated_at = NOW() WHERE id = ?',
                [$researchStatus, $researchId]
            );

            if ($finalComment !== '') {
                ReviewComment::create([
                    'review_id' => (int) $review['id'],
                    'reviewer_id' => $reviewerId,
                    'comment_text' => $finalComment,
                ]);
            }
        });

        $studentId = (int) ($research['student_id'] ?? 0);
        if ($studentId > 0) {
            if ($decision === 'approved') {
                NotificationService::notify(
                    $studentId,
                    'RESEARCH_APPROVED',
                    'تمت الموافقة على البحث',
                    'تمت مراجعة بحثك والموافقة عليه',
                    $researchId
                );
            } elseif ($decision === 'rejected') {
                NotificationService::notify(
                    $studentId,
                    'RESEARCH_REJECTED',
                    'تم رفض البحث',
                    'تم رفض بحثك. يرجى مراجعة التعليقات',
                    $researchId
                );
            } else {
                NotificationService::notify(
                    $studentId,
                    'REVISION_REQUESTED',
                    'مطلوب تعديل البحث',
                    'طلب المراجع إجراء تعديلات على بحثك',
                    $researchId
                );
            }
        }

        Response::json(['message' => 'تم تسجيل القرار بنجاح'], 200);
    }

    private function stripStudentPII(array $research): array
    {
        foreach (['student_id', 'name', 'email', 'phone', 'id_photo_path'] as $key) {
            unset($research[$key]);
        }

        return $research;
    }

    private function badRequest(array $details): never
    {
        Response::json([
            'error' => 'بيانات غير صالحة',
            'details' => $details,
        ], 400);
    }
}

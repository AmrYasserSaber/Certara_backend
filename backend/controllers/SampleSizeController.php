<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\NotificationService;
use App\Models\SampleSize;

final class SampleSizeController extends Controller
{
    public function pending(Request $request): never
    {
        $items = Database::fetchAll(
            'SELECT id, title, principal_investigator, department, faculty, created_at, serial_number
             FROM research
             WHERE status = ?
             ORDER BY created_at ASC',
            ['awaiting_sample_size']
        );

        Response::json(['data' => $items], 200);
    }

    public function submit(Request $request): never
    {
        $researchId = (int) ($request->param('research_id') ?? 0);
        if ($researchId <= 0) {
            $this->badRequest(['research_id' => 'معرف البحث غير صالح']);
        }

        $payload = $request->input();
        $calculatedSizeRaw = $payload['calculated_size'] ?? null;
        $feeAmountRaw = $payload['fee_amount'] ?? null;
        $notesRaw = $payload['notes'] ?? null;

        $details = [];

        $calculatedSize = filter_var($calculatedSizeRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($calculatedSize === false) {
            $details['calculated_size'] = 'حجم العينة يجب أن يكون رقماً صحيحاً موجباً';
        }

        if (!is_numeric($feeAmountRaw) || (float) $feeAmountRaw <= 0) {
            $details['fee_amount'] = 'قيمة الرسوم يجب أن تكون رقماً موجباً';
        }
        $feeAmount = (float) $feeAmountRaw;

        $notes = null;
        if ($notesRaw !== null) {
            $notes = trim((string) $notesRaw);
            if (mb_strlen($notes) > 5000) {
                $details['notes'] = 'الملاحظات طويلة جداً';
            }
            if ($notes === '') {
                $notes = null;
            }
        }

        if ($details !== []) {
            $this->badRequest($details);
        }

        $research = Database::fetchOne(
            'SELECT id, student_id, status FROM research WHERE id = ? LIMIT 1',
            [$researchId]
        );

        if ($research === null) {
            Response::json(['error' => 'البحث غير موجود'], 404);
        }

        if (($research['status'] ?? '') !== 'awaiting_sample_size') {
            Response::json(['error' => 'لا يمكن تسجيل حجم العينة في الحالة الحالية'], 409);
        }

        $existing = SampleSize::findByResearch($researchId);
        if ($existing !== false) {
            Response::json(['error' => 'تم تسجيل حجم العينة مسبقاً'], 409);
        }

        $user = $request->user();
        $officerId = (int) ($user->id ?? 0);

        $sampleSize = Database::transaction(function () use ($researchId, $officerId, $calculatedSize, $notes, $feeAmount) {
            $created = SampleSize::create([
                'research_id' => $researchId,
                'officer_id' => $officerId,
                'calculated_size' => $calculatedSize,
                'notes' => $notes,
                'fee_amount' => $feeAmount,
            ]);

            if ($created === false) {
                return false;
            }

            Database::execute(
                'UPDATE research SET status = ?, updated_at = NOW() WHERE id = ?',
                ['awaiting_payment_2', $researchId]
            );

            return $created;
        });

        if ($sampleSize === false) {
            Response::json(['error' => 'تعذر تسجيل حجم العينة حالياً'], 500);
        }

        $studentId = (int) ($research['student_id'] ?? 0);
        if ($studentId > 0) {
            NotificationService::notify(
                $studentId,
                'REVIEW_REQUESTED',
                'تم تحديد حجم العينة',
                'يرجى إتمام الدفعة الثانية لمتابعة البحث',
                $researchId
            );
        }

        Response::json([
            'message' => 'تم تسجيل حجم العينة بنجاح',
            'data' => $sampleSize,
        ], 201);
    }

    private function badRequest(array $details): never
    {
        Response::json([
            'error' => 'بيانات غير صالحة',
            'details' => $details,
        ], 400);
    }
}

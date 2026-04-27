<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Enums\NotificationType;
use App\Helpers\NotificationService;

final class ManagerController extends Controller
{
    public function getReviewedResearch(Request $request): never
    {
        $this->reviewedQueue($request);
    }

    public function getResearchDetail(Request $request): never
    {
        $this->researchDetail($request);
    }

    public function makeDecision(Request $request): never
    {
        $this->decision($request);
    }

    public function getDashboardStats(Request $request): never
    {
        $this->stats($request);
    }

    public function getApprovedCertificates(Request $request): never
    {
        $this->approvedCertificates($request);
    }

    public function reviewedQueue(Request $request): never
    {
        $page  = max(1, (int) $request->query('page', 1));
        $limit = min(50, max(1, (int) $request->query('limit', 20)));
        $search = trim((string) $request->query('q', ''));

        $where = ['r.status IN (?, ?)'];
        $bindings = ['reviewer_approved', 'manager_reviewing'];

        if ($search !== '') {
            $where[] = '(r.title LIKE ? OR r.serial_number LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';
            $like = '%' . $search . '%';
            array_push($bindings, $like, $like, $like, $like);
        }

        $result = $this->paginateResearch($where, $bindings, $page, $limit, 'r.updated_at DESC, r.id DESC');
        $this->ok(['items' => $result['items']], $result['meta']);
    }

    public function approvedCertificates(Request $request): never
    {
        $page  = max(1, (int) $request->query('page', 1));
        $limit = min(50, max(1, (int) $request->query('limit', 20)));
        $search = trim((string) $request->query('q', ''));

        $where = ['r.status = ?'];
        $bindings = ['approved'];

        if ($search !== '') {
            $where[] = '(r.title LIKE ? OR r.serial_number LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';
            $like = '%' . $search . '%';
            array_push($bindings, $like, $like, $like, $like);
        }

        $result = $this->paginateResearch($where, $bindings, $page, $limit, 'r.updated_at DESC, r.id DESC');
        $this->ok(['items' => $result['items']], $result['meta']);
    }

    public function researchDetail(Request $request): never
    {
        $id = (int) $request->param('id');
        if ($id <= 0) {
            $this->fail('معرف البحث غير صالح.', 422, 'validation_error');
        }

        $research = $this->loadResearchDetail($id);
        if ($research === null) {
            $this->fail('البحث غير موجود.', 404, 'not_found');
        }

        $this->ok(['research' => $research]);
    }

    public function decision(Request $request): never
    {
        $actor = $request->user();
        $id = (int) $request->param('id');
        $data = $this->validate($request, [
            'decision' => 'required|string|trim',
            'note' => 'nullable|string|trim|max:1000',
        ]);

        $decisionInput = strtolower((string) $data['decision']);
        $note = (string) ($data['note'] ?? '');

        $decision = match ($decisionInput) {
            'approved', 'approve', 'accept' => 'approved',
            'rejected', 'reject', 'decline' => 'rejected',
            default => null,
        };

        if ($id <= 0 || $decision === null) {
            $this->fail('بيانات القرار غير صالحة.', 422, 'validation_error');
        }

        $current = Database::fetchOne('SELECT id, student_id, status FROM research WHERE id = ?', [$id]);
        if ($current === null) {
            $this->fail('البحث غير موجود.', 404, 'not_found');
        }

        if (!in_array((string) $current['status'], ['reviewer_approved', 'manager_reviewing'], true)) {
            $this->fail('البحث غير جاهز لاتخاذ قرار نهائي.', 409, 'invalid_state');
        }

        Database::transaction(function () use ($id, $decision): void {
            Database::execute(
                'UPDATE research SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
                [$decision, $id]
            );
        });

        $details = [
            'decision' => $decision,
            'note' => $note !== '' ? $note : null,
        ];

        $this->logActivity(
            actorId: $actor?->id !== null ? (int) $actor->id : null,
            action: 'manager.decision_recorded',
            targetType: 'research',
            targetId: $id,
            details: $details
        );

        Logger::info('Manager decision recorded', [
            'research_id' => $id,
            'decision' => $decision,
            'actor_id' => $actor?->id,
        ]);

        $this->notifyResearchOwner($id, $decision, $note);

        $this->ok(['research' => $this->loadResearchDetail($id)]);
    }

    public function stats(Request $request): never
    {
        $queueRow = Database::fetchOne(
            "SELECT COUNT(*) AS total FROM research WHERE status IN ('reviewer_approved', 'manager_reviewing')"
        );
        $approvedRow = Database::fetchOne(
            "SELECT COUNT(*) AS total FROM research WHERE status = 'approved'"
        );
        $rejectedRow = Database::fetchOne(
            "SELECT COUNT(*) AS total FROM research WHERE status = 'rejected'"
        );
        $certificateRow = Database::fetchOne(
            'SELECT COUNT(*) AS total FROM certificates'
        );

        $queue = (int) ($queueRow['total'] ?? 0);
        $approved = (int) ($approvedRow['total'] ?? 0);
        $rejected = (int) ($rejectedRow['total'] ?? 0);
        $certificates = (int) ($certificateRow['total'] ?? 0);
        $totalFinalized = $approved + $rejected;
        $issuanceRate = $approved > 0 ? round(($certificates / $approved) * 100, 1) : 0.0;

        $this->ok([
            'queue' => $queue,
            'approved' => $approved,
            'rejected' => $rejected,
            'certificates' => $certificates,
            'finalized' => $totalFinalized,
            'issuance_rate' => $issuanceRate,
        ]);
    }

    public function issueCertificate(Request $request): never
    {
        $actor = $request->user();
        $id = (int) $request->param('id');

        if ($id <= 0) {
            $this->fail('معرف البحث غير صالح.', 422, 'validation_error');
        }

        $research = $this->loadResearchDetail($id);
        if ($research === null) {
            $this->fail('البحث غير موجود.', 404, 'not_found');
        }

        if ((string) ($research['status'] ?? '') !== 'approved') {
            $this->fail('يمكن إصدار الشهادة فقط بعد الموافقة النهائية.', 409, 'invalid_state');
        }

        $existing = Database::fetchOne('SELECT * FROM certificates WHERE research_id = ? LIMIT 1', [$id]);
        if ($existing !== null) {
            $certificate = $this->normalizeCertificate($existing);
            $this->ok(['certificate' => $certificate, 'research' => $research]);
        }

        $year = date('Y');
        $prefix = 'CERT-' . $year . '-';
        $countRow = Database::fetchOne('SELECT COUNT(*) AS total FROM certificates WHERE certificate_number LIKE ?', [$prefix . '%']);
        $next = ((int) ($countRow['total'] ?? 0)) + 1;
        $certificateNumber = $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);

        $filePath = $this->createCertificateFile($research, $certificateNumber);
        $issuedBy = (int) ($actor?->id ?? 0);

        if ($issuedBy <= 0) {
            $this->fail('غير مصرح بالدخول.', 401, 'unauthenticated');
        }

        Database::transaction(function () use ($id, $issuedBy, $certificateNumber, $filePath): void {
            Database::execute(
                'INSERT INTO certificates (research_id, issued_by, certificate_number, file_path, issued_at) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)',
                [$id, $issuedBy, $certificateNumber, $filePath]
            );
        });

        $certificate = Database::fetchOne('SELECT * FROM certificates WHERE research_id = ? LIMIT 1', [$id]);
        $certificate = $certificate !== null ? $this->normalizeCertificate($certificate) : null;

        $this->logActivity(
            actorId: $issuedBy,
            action: 'manager.certificate_issued',
            targetType: 'research',
            targetId: $id,
            details: [
                'certificate_number' => $certificateNumber,
                'file_path' => $filePath,
            ]
        );

        Logger::info('Certificate issued', [
            'research_id' => $id,
            'certificate_number' => $certificateNumber,
            'actor_id' => $issuedBy,
        ]);

        NotificationService::notify(
            (int) ($research['student_id'] ?? 0),
            NotificationType::CERTIFICATE_READY,
            'الشهادة جاهزة',
            'تم إصدار شهادة الموافقة الخاصة ببحثك ويمكنك تنزيلها الآن.',
            $id
        );

        $this->ok([
            'certificate' => $certificate,
            'research' => $this->loadResearchDetail($id),
        ]);
    }

    private function paginateResearch(array $where, array $bindings, int $page, int $limit, string $orderBy): array
    {
        $sqlWhere = implode(' AND ', $where);
        $countRow = Database::fetchOne(
            "SELECT COUNT(*) AS total FROM research r JOIN users u ON u.id = r.student_id WHERE {$sqlWhere}",
            $bindings
        );
        $total = (int) ($countRow['total'] ?? 0);
        $offset = ($page - 1) * $limit;

        $rows = Database::fetchAll(
            "SELECT r.id, r.student_id, r.title, r.principal_investigator, r.co_investigators, r.department, r.faculty, r.specialization, r.serial_number, r.status, r.created_at, r.updated_at,
                    u.name AS student_name, u.email AS student_email,
                    ss.calculated_size AS sample_calculated_size, ss.notes AS sample_notes, ss.fee_amount AS sample_fee_amount, ss.created_at AS sample_created_at,
                    so.id AS sample_officer_id, so.name AS sample_officer_name,
                    rev.id AS review_id, rev.reviewer_id, rev.round_number AS review_round_number, rev.previous_review_id AS review_previous_review_id, rv.name AS reviewer_name, rev.status AS review_status, rev.decision AS review_decision, rev.decided_at AS review_decided_at,
                    c.id AS certificate_id, c.certificate_number, c.file_path AS certificate_file_path, c.issued_at AS certificate_issued_at
             FROM research r
             JOIN users u ON u.id = r.student_id
             LEFT JOIN sample_sizes ss ON ss.research_id = r.id
             LEFT JOIN users so ON so.id = ss.officer_id
             LEFT JOIN (
                SELECT rr.*
                FROM reviews rr
                INNER JOIN (
                    SELECT research_id, MAX(round_number) AS max_round_number
                    FROM reviews
                    GROUP BY research_id
                ) latest ON latest.research_id = rr.research_id AND latest.max_round_number = rr.round_number
             ) rev ON rev.research_id = r.id
             LEFT JOIN users rv ON rv.id = rev.reviewer_id
             LEFT JOIN certificates c ON c.research_id = r.id
             WHERE {$sqlWhere}
             ORDER BY {$orderBy}
             LIMIT {$limit} OFFSET {$offset}
            ",
            $bindings
        );

        return [
            'items' => array_map([$this, 'buildResearchItem'], $rows),
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => max(1, (int) ceil($total / $limit)),
            ],
        ];
    }

    private function loadResearchDetail(int $id): ?array
    {
        $row = Database::fetchOne(
            "SELECT r.id, r.student_id, r.title, r.principal_investigator, r.co_investigators, r.department, r.faculty, r.specialization, r.serial_number, r.status, r.created_at, r.updated_at,
                    u.name AS student_name, u.email AS student_email, u.phone AS student_phone, u.department AS student_department, u.faculty AS student_faculty, u.specialization AS student_specialization,
                    ss.calculated_size AS sample_calculated_size, ss.notes AS sample_notes, ss.fee_amount AS sample_fee_amount, ss.created_at AS sample_created_at,
                    so.id AS sample_officer_id, so.name AS sample_officer_name, so.email AS sample_officer_email,
                    rev.id AS review_id, rev.reviewer_id, rev.round_number AS review_round_number, rev.previous_review_id AS review_previous_review_id, rv.name AS reviewer_name, rv.email AS reviewer_email, rev.status AS review_status, rev.decision AS review_decision, rev.decided_at AS review_decided_at,
                    c.id AS certificate_id, c.certificate_number, c.file_path AS certificate_file_path, c.issued_at AS certificate_issued_at,
                    p.first_payment_status, p.second_payment_status
             FROM research r
             JOIN users u ON u.id = r.student_id
             LEFT JOIN sample_sizes ss ON ss.research_id = r.id
             LEFT JOIN users so ON so.id = ss.officer_id
             LEFT JOIN (
                SELECT rr.*
                FROM reviews rr
                INNER JOIN (
                    SELECT research_id, MAX(round_number) AS max_round_number
                    FROM reviews
                    GROUP BY research_id
                ) latest ON latest.research_id = rr.research_id AND latest.max_round_number = rr.round_number
             ) rev ON rev.research_id = r.id
             LEFT JOIN users rv ON rv.id = rev.reviewer_id
             LEFT JOIN certificates c ON c.research_id = r.id
             LEFT JOIN (
                SELECT research_id,
                       MAX(CASE WHEN type = 'first' THEN status END) AS first_payment_status,
                       MAX(CASE WHEN type = 'second' THEN status END) AS second_payment_status
                FROM payments
                GROUP BY research_id
             ) p ON p.research_id = r.id
             WHERE r.id = ?
             LIMIT 1",
            [$id]
        );

        if ($row === null) {
            return null;
        }

        $documents = Database::fetchAll(
            'SELECT id, research_id, type, file_path, original_name, uploaded_at FROM documents WHERE research_id = ? ORDER BY uploaded_at DESC, id DESC',
            [$id]
        );
        $payments = Database::fetchAll(
            'SELECT id, research_id, amount, currency, type, status, gateway, gateway_ref, checkout_url, paid_at, created_at, updated_at FROM payments WHERE research_id = ? ORDER BY created_at DESC, id DESC',
            [$id]
        );
        $comments = [];
        if (!empty($row['review_id'])) {
            $comments = Database::fetchAll(
                'SELECT id, review_id, reviewer_id, comment_text, created_at FROM review_comments WHERE review_id = ? ORDER BY created_at ASC, id ASC',
                [(int) $row['review_id']]
            );
        }

        $research = $this->buildResearchItem($row);
        $research['documents'] = array_map([$this, 'normalizeRow'], $documents);
        $research['payments'] = array_map([$this, 'normalizeRow'], $payments);
        $research['review_comments'] = array_map([$this, 'normalizeRow'], $comments);
        return $research;
    }

    private function buildResearchItem(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'student_id' => (int) ($row['student_id'] ?? 0),
            'title' => (string) ($row['title'] ?? ''),
            'principal_investigator' => (string) ($row['principal_investigator'] ?? ''),
            'co_investigators' => $row['co_investigators'] ?? null,
            'department' => $row['department'] ?? null,
            'faculty' => $row['faculty'] ?? null,
            'specialization' => $row['specialization'] ?? null,
            'serial_number' => $row['serial_number'] ?? null,
            'status' => $row['status'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'student' => [
                'id' => (int) ($row['student_id'] ?? 0),
                'name' => (string) ($row['student_name'] ?? ''),
                'email' => (string) ($row['student_email'] ?? ''),
                'phone' => $row['student_phone'] ?? null,
                'department' => $row['student_department'] ?? null,
                'faculty' => $row['student_faculty'] ?? null,
                'specialization' => $row['student_specialization'] ?? null,
            ],
            'sample_size' => $row['sample_calculated_size'] !== null || $row['sample_fee_amount'] !== null ? [
                'calculated_size' => isset($row['sample_calculated_size']) ? (int) $row['sample_calculated_size'] : null,
                'notes' => $row['sample_notes'] ?? null,
                'fee_amount' => isset($row['sample_fee_amount']) ? (float) $row['sample_fee_amount'] : null,
                'created_at' => $row['sample_created_at'] ?? null,
                'officer' => [
                    'id' => isset($row['sample_officer_id']) ? (int) $row['sample_officer_id'] : null,
                    'name' => $row['sample_officer_name'] ?? null,
                    'email' => $row['sample_officer_email'] ?? null,
                ],
            ] : null,
            'review' => $row['review_id'] !== null ? [
                'id' => (int) $row['review_id'],
                'research_id' => (int) ($row['id'] ?? 0),
                'reviewer_id' => isset($row['reviewer_id']) ? (int) $row['reviewer_id'] : null,
                'reviewer' => [
                    'id' => isset($row['reviewer_id']) ? (int) $row['reviewer_id'] : null,
                    'name' => $row['reviewer_name'] ?? null,
                    'email' => $row['reviewer_email'] ?? null,
                ],
                'status' => $row['review_status'] ?? null,
                'decision' => $row['review_decision'] ?? null,
                'decided_at' => $row['review_decided_at'] ?? null,
            ] : null,
            'payment_statuses' => [
                'first' => $row['first_payment_status'] ?? null,
                'second' => $row['second_payment_status'] ?? null,
            ],
            'certificate' => $row['certificate_id'] !== null ? [
                'id' => (int) $row['certificate_id'],
                'certificate_number' => $row['certificate_number'] ?? null,
                'file_path' => $row['certificate_file_path'] ?? null,
                'issued_at' => $row['certificate_issued_at'] ?? null,
            ] : null,
        ];
    }

    private function notifyResearchOwner(int $researchId, string $decision, string $note): void
    {
        $row = Database::fetchOne('SELECT student_id, title FROM research WHERE id = ?', [$researchId]);
        if ($row === null) {
            return;
        }

        $title = $decision === 'approved' ? 'تمت الموافقة على البحث' : 'تم رفض البحث';
        $message = $decision === 'approved'
            ? 'تمت الموافقة النهائية على بحثك.'
            : 'تم رفض بحثك النهائي.';

        if ($note !== '') {
            $message .= ' الملاحظات: ' . $note;
        }

        NotificationService::notify(
            (int) $row['student_id'],
            $decision === 'approved' ? NotificationType::RESEARCH_APPROVED : NotificationType::RESEARCH_REJECTED,
            $title,
            $message,
            $researchId
        );
    }

    private function createCertificateFile(array $research, string $certificateNumber): string
    {
        $baseDir = dirname(__DIR__) . '/uploads/certificates';
        if (!is_dir($baseDir)) {
            @mkdir($baseDir, 0775, true);
        }

        $fileName = $certificateNumber . '.html';
        $relativePath = 'uploads/certificates/' . $fileName;
        $absolutePath = $baseDir . '/' . $fileName;

        $studentName = htmlspecialchars((string) ($research['student']['name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $researchTitle = htmlspecialchars((string) ($research['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $serial = htmlspecialchars((string) ($research['serial_number'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $today = htmlspecialchars(date('Y-m-d'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $html = <<<HTML
<!doctype html>
<html lang="ar" dir="rtl">
  <head>
    <meta charset="utf-8">
    <title>{$certificateNumber}</title>
    <style>
      body { font-family: Tahoma, Arial, sans-serif; background: #f8fafc; margin: 0; padding: 40px; color: #1f2937; }
      .sheet { max-width: 900px; margin: 0 auto; background: #fff; border: 8px solid #0f4c81; padding: 48px; box-shadow: 0 20px 60px rgba(15, 76, 129, 0.12); }
      .header { text-align: center; margin-bottom: 32px; }
      .title { font-size: 32px; font-weight: 700; color: #0f4c81; margin: 0 0 8px; }
      .subtitle { font-size: 18px; color: #475569; margin: 0; }
      .content { font-size: 20px; line-height: 1.9; text-align: center; }
      .meta { margin-top: 40px; display: grid; gap: 12px; grid-template-columns: repeat(2, 1fr); }
      .meta div { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 18px; font-size: 16px; }
      .footer { margin-top: 48px; display: flex; justify-content: space-between; gap: 20px; font-size: 14px; color: #64748b; }
      .badge { display: inline-block; padding: 8px 14px; border-radius: 999px; background: #dbeafe; color: #1d4ed8; font-weight: 700; }
    </style>
  </head>
  <body>
    <div class="sheet">
      <div class="header">
        <div class="title">شهادة موافقة بحثية</div>
        <p class="subtitle">Institutional Review Board Certificate</p>
      </div>

      <div class="content">
        تشهد لجنة المراجعة بأن الباحث <strong>{$studentName}</strong><br>
        قد حصل على الموافقة النهائية على بحثه بعنوان<br>
        <strong>{$researchTitle}</strong>
      </div>

      <div class="meta">
        <div><strong>رقم الشهادة:</strong> <span class="badge">{$certificateNumber}</span></div>
        <div><strong>الرقم التسلسلي:</strong> {$serial}</div>
        <div><strong>تاريخ الإصدار:</strong> {$today}</div>
        <div><strong>رقم البحث:</strong> {$research['id']}</div>
      </div>

      <div class="footer">
        <div>IRB Digital System</div>
        <div>هذه الشهادة تم إصدارها إلكترونيًا ويمكن التحقق من بياناتها من خلال النظام.</div>
      </div>
    </div>
  </body>
</html>
HTML;

        file_put_contents($absolutePath, $html);
        return $relativePath;
    }

    private function normalizeCertificate(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'research_id' => (int) ($row['research_id'] ?? 0),
            'issued_by' => (int) ($row['issued_by'] ?? 0),
            'certificate_number' => $row['certificate_number'] ?? null,
            'file_path' => $row['file_path'] ?? null,
            'issued_at' => $row['issued_at'] ?? null,
        ];
    }

    private function normalizeRow(array $row): array
    {
        foreach ($row as $key => $value) {
            if (is_string($value) && $key === 'details') {
                $decoded = json_decode($value, true);
                $row[$key] = json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
            }
        }

        return $row;
    }

    private function logActivity(?int $actorId, string $action, ?string $targetType, ?int $targetId, array $details = []): void
    {
        Database::execute(
            'INSERT INTO activity_logs (actor_id, action, target_type, target_id, details, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)',
            [
                $actorId,
                $action,
                $targetType,
                $targetId,
                $details === [] ? null : json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $_SERVER['REMOTE_ADDR'] ?? null,
                isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
            ]
        );
    }
}

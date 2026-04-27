<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Enums\NotificationType;
use App\Helpers\NotificationService;
use App\Models\User;

final class AdminController extends Controller
{
    public function getPendingUsers(Request $request): never
    {
        $this->pendingUsers($request);
    }

    public function getAllResearch(Request $request): never
    {
        $this->researchList($request);
    }

    public function getActivityLogs(Request $request): never
    {
        $this->logs($request);
    }

    public function getReviewers(Request $request): never
    {
        $this->reviewers($request);
    }

    public function pendingUsers(Request $request): never
    {
        $page  = max(1, (int) $request->query('page', 1));
        $limit = min(50, max(1, (int) $request->query('limit', 20)));
        $status = (string) $request->query('status', 'pending');
        $search = trim((string) $request->query('q', ''));

        $where = ["u.status = ?"];
        $bindings = [$status];

        if ($search !== '') {
            $where[] = "(u.name LIKE ? OR u.email LIKE ? OR u.national_id LIKE ? OR u.department LIKE ? OR u.faculty LIKE ? OR u.specialization LIKE ?)";
            $like = '%' . $search . '%';
            array_push($bindings, $like, $like, $like, $like, $like, $like);
        }

        $items = $this->paginateUsers($where, $bindings, $page, $limit, 'u.created_at ASC, u.id ASC');

        $this->ok(['items' => $items['items']], $items['meta']);
    }

    public function activateUser(Request $request): never
    {
        $actor = $request->user();
        $id = (int) $request->param('id');

        if ($id <= 0) {
            $this->fail('Invalid user id.', 422, 'validation_error');
        }

        $user = User::find($id);
        if ($user === null) {
            $this->fail('User not found.', 404, 'not_found');
        }

        if ((string) ($user->status ?? '') === 'active') {
            $this->fail('User is already active.', 409, 'already_active');
        }

        if ((string) ($user->status ?? '') !== 'pending') {
            $this->fail('Only pending users can be activated.', 409, 'invalid_state');
        }

        Database::execute('UPDATE users SET status = ? WHERE id = ? AND status = ?', ['active', $id, 'pending']);

        $safeUser = $this->safeUser($id);
        $this->logActivity(
            actorId: $actor?->id !== null ? (int) $actor->id : null,
            action: 'admin.user_activated',
            targetType: 'user',
            targetId: $id,
            details: ['status' => 'active']
        );

        Logger::info('Admin activated user', ['user_id' => $id, 'actor_id' => $actor?->id]);

        NotificationService::notify(
            $id,
            NotificationType::ACCOUNT_ACTIVATED,
            'تم تفعيل الحساب',
            'تم تفعيل حسابك بنجاح ويمكنك الآن تسجيل الدخول إلى النظام.',
            null
        );

        $this->ok(['user' => $safeUser]);
    }

    public function rejectUser(Request $request): never
    {
        $actor = $request->user();
        $id = (int) $request->param('id');
        $data = $this->validate($request, [
            'reason' => 'nullable|string|trim|max:500',
        ]);
        $reason = (string) ($data['reason'] ?? '');

        if ($id <= 0) {
            $this->fail('Invalid user id.', 422, 'validation_error');
        }

        $user = User::find($id);
        if ($user === null) {
            $this->fail('User not found.', 404, 'not_found');
        }

        if ((string) ($user->status ?? '') === 'rejected') {
            $this->fail('User is already rejected.', 409, 'already_rejected');
        }

        if ((string) ($user->status ?? '') !== 'pending') {
            $this->fail('Only pending users can be rejected.', 409, 'invalid_state');
        }

        Database::execute('UPDATE users SET status = ? WHERE id = ?', ['rejected', $id]);

        $message = $reason !== ''
            ? 'تم رفض حسابك. السبب: ' . $reason
            : 'تم رفض حسابك من قبل الإدارة.';

        $this->logActivity(
            actorId: $actor?->id !== null ? (int) $actor->id : null,
            action: 'admin.user_rejected',
            targetType: 'user',
            targetId: $id,
            details: ['status' => 'rejected', 'reason' => $reason !== '' ? $reason : null]
        );

        Logger::info('Admin rejected user', ['user_id' => $id, 'actor_id' => $actor?->id]);

        NotificationService::notify(
            $id,
            NotificationType::ACCOUNT_REJECTED,
            'تم رفض الحساب',
            $message,
            null
        );

        $this->ok(['user' => $this->safeUser($id)]);
    }

    public function researchList(Request $request): never
    {
        $page  = max(1, (int) $request->query('page', 1));
        $limit = min(50, max(1, (int) $request->query('limit', 20)));
        $status = trim((string) $request->query('status', ''));
        $department = trim((string) $request->query('department', ''));
        $date = trim((string) $request->query('date', ''));
        $search = trim((string) $request->query('q', ''));

        $where = ['1 = 1'];
        $bindings = [];

        if ($status !== '') {
            $where[] = 'r.status = ?';
            $bindings[] = $status;
        }

        if ($department !== '') {
            $where[] = 'r.department = ?';
            $bindings[] = $department;
        }

        if ($date !== '') {
            $where[] = 'DATE(r.created_at) = ?';
            $bindings[] = $date;
        }

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
            $this->fail('Invalid research id.', 422, 'validation_error');
        }

        $research = $this->loadResearchDetail($id);
        if ($research === null) {
            $this->fail('Research not found.', 404, 'not_found');
        }

        $this->ok(['research' => $research]);
    }

    public function assignReviewer(Request $request): never
    {
        $actor = $request->user();
        $id = (int) $request->param('id');
        $data = $this->validate($request, [
            'reviewer_id' => 'required|string|trim',
        ]);
        $reviewerId = (int) $data['reviewer_id'];

        if ($id <= 0 || $reviewerId <= 0) {
            $this->fail('Invalid research or reviewer id.', 422, 'validation_error');
        }

        $research = $this->loadResearchDetail($id);
        if ($research === null) {
            $this->fail('Research not found.', 404, 'not_found');
        }

        $reviewer = User::query()
            ->where('id', $reviewerId)
            ->where('role', 'reviewer')
            ->where('status', 'active')
            ->first();

        if ($reviewer === null) {
            $this->fail('Reviewer not found.', 404, 'not_found');
        }

        Database::transaction(function () use ($id, $reviewerId): void {
            Database::execute(
                'UPDATE research SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
                ['in_review', $id]
            );

            $existing = Database::fetchOne(
                'SELECT id FROM reviews WHERE research_id = ? AND reviewer_id = ? ORDER BY id DESC LIMIT 1',
                [$id, $reviewerId]
            );

            if ($existing !== null) {
                Database::execute(
                    'UPDATE reviews SET status = ?, decision = NULL, decided_at = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
                    ['assigned', (int) $existing['id']]
                );
            } else {
                Database::execute(
                    'INSERT INTO reviews (research_id, reviewer_id, status, decision, decided_at, created_at, updated_at) VALUES (?, ?, ?, NULL, NULL, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
                    [$id, $reviewerId, 'assigned']
                );
            }
        });

        $review = Database::fetchOne(
            'SELECT id, research_id, reviewer_id, status, decision, decided_at, created_at, updated_at FROM reviews WHERE research_id = ? AND reviewer_id = ? ORDER BY id DESC LIMIT 1',
            [$id, $reviewerId]
        );

        $this->logActivity(
            actorId: $actor?->id !== null ? (int) $actor->id : null,
            action: 'admin.reviewer_assigned',
            targetType: 'research',
            targetId: $id,
            details: ['reviewer_id' => $reviewerId]
        );

        Logger::info('Admin assigned reviewer', [
            'research_id' => $id,
            'reviewer_id' => $reviewerId,
            'actor_id' => $actor?->id,
        ]);

        NotificationService::notify(
            $reviewerId,
            NotificationType::REVIEW_REQUESTED,
            'تم إسناد بحث جديد إليك',
            'تم إسناد بحث جديد لك للمراجعة. يرجى الدخول إلى لوحة المراجع للاطلاع على التفاصيل.',
            $id
        );

        $this->ok([
            'research' => $this->loadResearchDetail($id),
            'review' => $review,
        ]);
    }

    public function generateSerial(Request $request): never
    {
        $actor = $request->user();
        $id = (int) $request->param('id');
        $data = $this->validate($request, [
            'amount' => 'required|numeric|min:0.01',
        ]);
        $amount = (float) $data['amount'];

        if ($id <= 0) {
            $this->fail('Invalid research id.', 422, 'validation_error');
        }

        $research = Database::fetchOne('SELECT * FROM research WHERE id = ?', [$id]);
        if ($research === null) {
            $this->fail('Research not found.', 404, 'not_found');
        }

        if ((string) $research['status'] !== 'pending_activation') {
            $this->fail('Serial numbers can only be generated for newly activated research.', 409, 'invalid_state');
        }

        if (!empty($research['serial_number'])) {
            $this->ok([
                'research' => $this->loadResearchDetail($id),
                'serial_number' => (string) $research['serial_number'],
            ]);
        }

        $year = date('Y');
        $prefix = 'IRB-' . $year . '-';
        $countRow = Database::fetchOne('SELECT COUNT(*) AS total FROM research WHERE serial_number LIKE ?', [$prefix . '%']);
        $next = ((int) ($countRow['total'] ?? 0)) + 1;
        $serial = $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);

        // Create Payment record
        $merchantRefNum = 'CERTARA-1-' . $id . '-' . time();
        $payLink = \App\Services\KashierService::generatePaymentLink([
            'amount'       => $amount,
            'reference_id' => $merchantRefNum,
            'email'        => $user->email ?? 'student@example.com',
            'phone_number' => $user->phone ?? '01000000000',
            'full_name'    => $user->name ?? 'Student ' . $id,
            'description'  => "First Payment - {$serial}",
        ]);

        if (!$payLink) {
            $this->fail('فشل إنشاء رابط الدفع. يرجى المحاولة لاحقاً', 500, 'gateway_error');
        }

        Database::transaction(function () use ($id, $serial, $amount, $merchantRefNum, $payLink): void {
            Database::execute(
                'UPDATE research SET serial_number = ?, status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
                [$serial, 'awaiting_payment_1', $id]
            );

            Database::execute(
                'INSERT INTO payments (research_id, amount, currency, type, status, gateway, gateway_ref, checkout_url, created_at, updated_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
                [$id, $amount, 'EGP', 'first', 'pending', 'paymob', $merchantRefNum, $payLink]
            );
        });

        $this->logActivity(
            actorId: $actor?->id !== null ? (int) $actor->id : null,
            action: 'admin.serial_generated',
            targetType: 'research',
            targetId: $id,
            details: ['serial_number' => $serial, 'status' => 'awaiting_payment_1', 'amount' => $amount]
        );

        Logger::info('Admin generated research serial', [
            'research_id' => $id,
            'serial_number' => $serial,
            'actor_id' => $actor?->id,
        ]);

        $this->ok([
            'research' => $this->loadResearchDetail($id),
            'serial_number' => $serial,
            'checkout_url' => $payLink
        ]);
    }

    public function setSecondPayment(Request $request): never
    {
        $actor = $request->user();
        $id = (int) $request->param('id');
        $data = $this->validate($request, [
            'amount' => 'required|numeric|min:0.01',
        ]);
        $amount = (float) $data['amount'];

        $research = Database::fetchOne('SELECT * FROM research WHERE id = ?', [$id]);
        if ($research === null) {
            $this->fail('Research not found.', 404, 'not_found');
        }

        if ((string) $research['status'] !== 'awaiting_payment_2') {
            $this->fail('Second payment can only be set when research is awaiting it.', 409, 'invalid_state');
        }

        // Create Payment record
        $merchantRefNum = 'CERTARA-2-' . $id . '-' . time();
        $payLink = \App\Services\KashierService::generatePaymentLink([
            'amount'       => $amount,
            'reference_id' => $merchantRefNum,
            'email'        => $user->email ?? 'student@example.com',
            'phone_number' => $user->phone ?? '01000000000',
            'full_name'    => $user->name ?? 'Student ' . $id,
            'description'  => "Second Payment - {$research['serial_number']}",
        ]);

        if (!$payLink) {
            $this->fail('فشل إنشاء رابط الدفع. يرجى المحاولة لاحقاً', 500, 'gateway_error');
        }

        Database::transaction(function () use ($id, $amount, $merchantRefNum, $payLink): void {
            Database::execute(
                'INSERT INTO payments (research_id, amount, currency, type, status, gateway, gateway_ref, checkout_url, created_at, updated_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
                [$id, $amount, 'EGP', 'second', 'pending', 'paymob', $merchantRefNum, $payLink]
            );
        });

        $this->logActivity(
            actorId: $actor?->id !== null ? (int) $actor->id : null,
            action: 'admin.second_payment_set',
            targetType: 'research',
            targetId: $id,
            details: ['amount' => $amount]
        );

        $this->ok([
            'research' => $this->loadResearchDetail($id),
            'checkout_url' => $payLink
        ]);
    }

    public function reviewers(Request $request): never
    {
        $items = Database::fetchAll(
            'SELECT id, name, email, phone, department, faculty, created_at FROM users WHERE role = ? AND status = ? ORDER BY name ASC, id ASC',
            ['reviewer', 'active']
        );

        $this->ok(['items' => array_map([$this, 'normalizeRow'], $items)]);
    }

    public function logs(Request $request): never
    {
        $page  = max(1, (int) $request->query('page', 1));
        $limit = min(100, max(1, (int) $request->query('limit', 20)));
        $action = trim((string) $request->query('action', ''));
        $targetType = trim((string) $request->query('target_type', ''));
        $actorId = (int) $request->query('actor_id', 0);

        $where = ['1 = 1'];
        $bindings = [];

        if ($action !== '') {
            $where[] = 'al.action = ?';
            $bindings[] = $action;
        }

        if ($targetType !== '') {
            $where[] = 'al.target_type = ?';
            $bindings[] = $targetType;
        }

        if ($actorId > 0) {
            $where[] = 'al.actor_id = ?';
            $bindings[] = $actorId;
        }

        $sqlWhere = implode(' AND ', $where);
        $countRow = Database::fetchOne(
            "SELECT COUNT(*) AS total FROM activity_logs al WHERE {$sqlWhere}",
            $bindings
        );
        $total = (int) ($countRow['total'] ?? 0);
        $offset = ($page - 1) * $limit;

        $rows = Database::fetchAll(
            "SELECT al.id, al.actor_id, al.action, al.target_type, al.target_id, al.details, al.ip_address, al.user_agent, al.created_at, u.name AS actor_name, u.email AS actor_email, u.role AS actor_role
             FROM activity_logs al
             LEFT JOIN users u ON u.id = al.actor_id
             WHERE {$sqlWhere}
             ORDER BY al.created_at DESC, al.id DESC
             LIMIT {$limit} OFFSET {$offset}",
            $bindings
        );

        $items = array_map(function (array $row): array {
            $row['details'] = $this->decodeJson($row['details'] ?? null);
            return $this->normalizeRow($row);
        }, $rows);

        $this->ok(['items' => $items], [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => max(1, (int) ceil($total / $limit)),
        ]);
    }

    private function paginateUsers(array $where, array $bindings, int $page, int $limit, string $orderBy): array
    {
        $sqlWhere = implode(' AND ', $where);
        $countRow = Database::fetchOne(
            "SELECT COUNT(*) AS total FROM users u WHERE {$sqlWhere}",
            $bindings
        );
        $total = (int) ($countRow['total'] ?? 0);
        $offset = ($page - 1) * $limit;

        $rows = Database::fetchAll(
            "SELECT u.id, u.name, u.email, u.phone, u.national_id, u.department, u.faculty, u.specialization, u.role, u.status, u.created_at, u.updated_at,
                    front.file_path AS front_file_path, front.file_url AS front_file_url, front.original_name AS front_original_name,
                    back.file_path AS back_file_path, back.file_url AS back_file_url, back.original_name AS back_original_name
             FROM users u
             LEFT JOIN user_identity_photos front ON front.user_id = u.id AND front.type = 'front' AND front.is_active = 1
             LEFT JOIN user_identity_photos back ON back.user_id = u.id AND back.type = 'back' AND back.is_active = 1
             WHERE {$sqlWhere}
             ORDER BY {$orderBy}
             LIMIT {$limit} OFFSET {$offset}",
            $bindings
        );

        return [
            'items' => array_map([$this, 'buildUserItem'], $rows),
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => max(1, (int) ceil($total / $limit)),
            ],
        ];
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
                    u.name AS student_name, u.email AS student_email, u.phone AS student_phone,
                    ss.calculated_size AS sample_calculated_size, ss.notes AS sample_notes, ss.fee_amount AS sample_fee_amount, ss.created_at AS sample_created_at,
                    so.id AS sample_officer_id, so.name AS sample_officer_name, so.email AS sample_officer_email,
                    rev.id AS review_id, rev.reviewer_id, rv.name AS reviewer_name, rv.email AS reviewer_email, rev.status AS review_status, rev.decision AS review_decision, rev.decided_at AS review_decided_at,
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
                    SELECT research_id, MAX(id) AS max_id
                    FROM reviews
                    GROUP BY research_id
                ) latest ON latest.max_id = rr.id
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
             WHERE {$sqlWhere}
             ORDER BY {$orderBy}
             LIMIT {$limit} OFFSET {$offset}",
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
                    rev.id AS review_id, rev.reviewer_id, rv.name AS reviewer_name, rv.email AS reviewer_email, rev.status AS review_status, rev.decision AS review_decision, rev.decided_at AS review_decided_at,
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
                    SELECT research_id, MAX(id) AS max_id
                    FROM reviews
                    GROUP BY research_id
                ) latest ON latest.max_id = rr.id
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

        $reviewComments = [];
        if (!empty($row['review_id'])) {
            $reviewComments = Database::fetchAll(
                'SELECT id, review_id, reviewer_id, comment_text, created_at FROM review_comments WHERE review_id = ? ORDER BY created_at ASC, id ASC',
                [(int) $row['review_id']]
            );
        }

        $research = $this->buildResearchItem($row);
        $research['documents'] = array_map([$this, 'normalizeRow'], $documents);
        $research['payments'] = array_map([$this, 'normalizeRow'], $payments);
        $research['review_comments'] = array_map([$this, 'normalizeRow'], $reviewComments);
        return $research;
    }

    private function buildUserItem(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'phone' => $row['phone'] ?? null,
            'national_id' => $row['national_id'] ?? null,
            'department' => $row['department'] ?? null,
            'faculty' => $row['faculty'] ?? null,
            'specialization' => $row['specialization'] ?? null,
            'role' => $row['role'] ?? null,
            'status' => $row['status'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'identity_photos' => [
                'front' => $this->buildPhotoItem(
                    $row['front_file_path'] ?? null,
                    $row['front_file_url'] ?? null,
                    $row['front_original_name'] ?? null
                ),
                'back' => $this->buildPhotoItem(
                    $row['back_file_path'] ?? null,
                    $row['back_file_url'] ?? null,
                    $row['back_original_name'] ?? null
                ),
            ],
        ];
    }

    private function buildPhotoItem(mixed $path, mixed $url, mixed $name): ?array
    {
        if ($path === null && $url === null && $name === null) {
            return null;
        }

        return [
            'file_path' => $path,
            'file_url' => $url,
            'original_name' => $name,
        ];
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
                'sample_size' => (($row['sample_calculated_size'] ?? null) !== null || ($row['sample_fee_amount'] ?? null) !== null) ? [                'calculated_size' => isset($row['sample_calculated_size']) ? (int) $row['sample_calculated_size'] : null,
                'notes' => $row['sample_notes'] ?? null,
                'fee_amount' => isset($row['sample_fee_amount']) ? (float) $row['sample_fee_amount'] : null,
                'created_at' => $row['sample_created_at'] ?? null,
                'officer' => [
                    'id' => isset($row['sample_officer_id']) ? (int) $row['sample_officer_id'] : null,
                    'name' => $row['sample_officer_name'] ?? null,
                    'email' => $row['sample_officer_email'] ?? null,
                ],
            ] : null,
                'review' => ($row['review_id'] ?? null) !== null ? [                'id' => (int) $row['review_id'],
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
                'certificate' => ($row['certificate_id'] ?? null) !== null ? [                'id' => (int) $row['certificate_id'],
                'certificate_number' => $row['certificate_number'] ?? null,
                'file_path' => $row['certificate_file_path'] ?? null,
                'issued_at' => $row['certificate_issued_at'] ?? null,
            ] : null,
        ];
    }

    private function normalizeRow(array $row): array
    {
        foreach ($row as $key => $value) {
            if (is_string($value) && in_array($key, ['details'], true)) {
                $row[$key] = $this->decodeJson($value);
            }
        }

        return $row;
    }

    private function decodeJson(mixed $value): mixed
    {
        if (!is_string($value) || $value === '') {
            return $value;
        }

        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    private function safeUser(int $id): ?array
    {
        $row = Database::fetchOne(
            'SELECT id, name, email, phone, national_id, department, faculty, specialization, role, status, created_at, updated_at FROM users WHERE id = ?',
            [$id]
        );

        return $row === null ? null : $this->normalizeRow($row);
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

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Enums\NotificationType;
use App\Helpers\NotificationService;
use App\Helpers\PDFHelper;
use App\Models\Certificate;

final class CertificateController extends Controller
{
    public function generate(Request $request): never
    {
        $db = Database::getInstance();
        $actor = $request->user();
        $researchId = (int) $request->param('id');
        $actorId = is_object($actor) ? (int) ($actor->id ?? 0) : 0;
        $managerName = is_object($actor) ? (string) ($actor->name ?? 'IRB Manager') : 'IRB Manager';

        if ($researchId <= 0) {
            $this->fail('معرف البحث غير صالح.', 422, 'validation_error');
        }

        $research = $db->fetchOne(
            'SELECT r.id, r.student_id, r.title, r.serial_number, r.status, r.created_at, u.name AS student_name, u.email AS student_email FROM research r INNER JOIN users u ON u.id = r.student_id WHERE r.id = ? LIMIT 1',
            [$researchId]
        );

        if ($research === null) {
            $this->fail('البحث غير موجود.', 404, 'not_found');
        }

        if ((string) ($research['status'] ?? '') !== 'approved') {
            $this->fail('يمكن إصدار الشهادة فقط بعد الموافقة النهائية.', 409, 'invalid_state');
        }

        $existing = Certificate::findByResearchId($researchId);
        if ($existing !== null) {
            $this->ok(['certificate' => $existing]);
        }

        $certificateNumber = 'CERT-' . date('Y') . '-' . str_pad((string) $researchId, 4, '0', STR_PAD_LEFT);
        $filePath = PDFHelper::generateCertificate([
            'research_id' => $researchId,
            'research_title' => (string) ($research['title'] ?? ''),
            'student_name' => (string) ($research['student_name'] ?? ''),
            'serial_number' => (string) ($research['serial_number'] ?? ''),
            'issue_date' => date('Y-m-d'),
            'certificate_number' => $certificateNumber,
            'manager_name' => $managerName,
        ]);

        $certificate = Certificate::create([
            'research_id' => $researchId,
            'issued_by' => $actorId,
            'certificate_number' => $certificateNumber,
            'file_path' => $filePath,
            'issued_at' => date('Y-m-d H:i:s'),
        ]);

        if ($certificate === null) {
            $this->fail('تعذر حفظ الشهادة.', 500, 'server_error');
        }

        $this->logAction(
            actorId: $actorId,
            action: 'certificate.generated',
            targetType: 'research',
            targetId: $researchId,
            details: [
                'certificate_number' => $certificateNumber,
                'file_path' => $filePath,
            ]
        );

        NotificationService::notify(
            (int) $research['student_id'],
            NotificationType::CERTIFICATE_READY,
            'الشهادة جاهزة',
            'تم إصدار شهادة الموافقة الخاصة ببحثك ويمكنك تنزيلها الآن.',
            $researchId
        );

        Logger::info('Certificate generated', [
            'research_id' => $researchId,
            'certificate_number' => $certificateNumber,
            'actor_id' => $actorId,
        ]);

        $this->ok(['certificate' => $certificate]);
    }

    public function download(Request $request): never
    {
        $db = Database::getInstance();
        $actor = $request->user();
        $researchId = (int) $request->param('id');
        $actorId = is_object($actor) ? (int) ($actor->id ?? 0) : 0;

        if ($researchId <= 0) {
            $this->fail('معرف البحث غير صالح.', 422, 'validation_error');
        }

        $role = is_object($actor) ? (string) ($actor->role ?? '') : '';
        $allowed = in_array($role, ['student', 'admin', 'manager'], true);
        if (!$allowed) {
            $this->fail('غير مسموح.', 403, 'forbidden');
        }

        $research = $db->fetchOne(
            'SELECT r.id, r.student_id, r.title, r.serial_number, r.status, c.certificate_number, c.file_path FROM research r INNER JOIN certificates c ON c.research_id = r.id WHERE r.id = ? LIMIT 1',
            [$researchId]
        );

        if ($research === null) {
            $this->fail('الشهادة غير موجودة.', 404, 'not_found');
        }

        if ($role === 'student' && (int) ($research['student_id'] ?? 0) !== (int) ($actor->id ?? 0)) {
            $this->fail('غير مسموح.', 403, 'forbidden');
        }

        $relativePath = (string) ($research['file_path'] ?? '');
        $absolutePath = dirname(__DIR__) . '/' . ltrim($relativePath, '/');
        if ($relativePath === '' || !is_file($absolutePath)) {
            $this->fail('ملف الشهادة غير موجود.', 404, 'not_found');
        }

        $this->logAction(
            actorId: $actorId,
            action: 'certificate.downloaded',
            targetType: 'research',
            targetId: $researchId,
            details: [
                'certificate_number' => $research['certificate_number'] ?? null,
                'file_path' => $relativePath,
            ]
        );

        Logger::info('Certificate download requested', [
            'research_id' => $researchId,
            'actor_id' => $actorId,
            'role' => $role,
        ]);

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($absolutePath) . '"');
        header('Content-Length: ' . (string) filesize($absolutePath));
        readfile($absolutePath);
        exit;
    }

    /**
     * Compatibility alias for the existing manager route.
     */
    public function issueCertificate(Request $request): never
    {
        $this->generate($request);
    }

    private function logAction(?int $actorId, string $action, ?string $targetType, ?int $targetId, array $details = []): void
    {
        Database::getInstance()->execute(
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

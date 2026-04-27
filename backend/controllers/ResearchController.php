<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Models\Research;
use App\Enums\ResearchStatus;
use App\Models\Review;

final class ResearchController extends Controller
{
    public function index(Request $request): never
    {
        $studentId = $request->user()->id;
        $researches = Research::where('student_id', $studentId)
            ->orderBy('created_at', 'desc')
            ->get();

        $this->ok($researches);
    }

    public function store(Request $request): never
    {
        $data = $this->validate($request, [
            'title'                  => 'required|string|max:255',
            'principal_investigator' => 'required|string|max:255',
            'co_investigators'       => 'nullable|array',
            'department'             => 'required|string|max:255',
            'faculty'                => 'required|string|max:255',
        ]);

        $research = Research::create([
            'student_id'             => $request->user()->id,
            'title'                  => $data['title'],
            'principal_investigator' => $data['principal_investigator'],
            'co_investigators'       => $data['co_investigators'] ?? [],
            'department'             => $data['department'],
            'faculty'                => $data['faculty'],
            'status'                 => ResearchStatus::DRAFT,
        ]);

        $this->created($research);
    }

    public function show(Request $request): never
    {
        $id = (int) $request->param('id');
        $studentId = $request->user()->id;
        $research = Research::with(['documents', 'payments'])->where('student_id', $studentId)->find($id);

        if (!$research) {
            $this->fail('البحث غير موجود.', 404, 'not_found');
        }

        $payload = $research->toArray();

        if (($payload['status'] ?? null) === ResearchStatus::REVISION_REQUESTED) {
            $roundRows = Database::fetchAll(
                'SELECT rv.id, rv.reviewer_id, u.name AS reviewer_name, rv.round_number, rv.previous_review_id, rv.status, rv.decision, rv.decided_at, rv.created_at
                 FROM reviews rv
                 JOIN users u ON u.id = rv.reviewer_id
                 WHERE rv.research_id = ?
                 ORDER BY rv.round_number ASC, rv.id ASC',
                [$id]
            );

            $reviewIds = array_values(array_filter(array_map(
                static fn (array $row): int => (int) ($row['id'] ?? 0),
                $roundRows
            ), static fn (int $value): bool => $value > 0));

            $commentRows = $reviewIds !== []
                ? Database::fetchAll(
                    'SELECT id, review_id, reviewer_id, comment_text, created_at
                     FROM review_comments
                     WHERE review_id IN (' . implode(',', array_fill(0, count($reviewIds), '?')) . ')
                     ORDER BY created_at ASC, id ASC',
                    $reviewIds
                )
                : [];

            $commentsByReviewId = [];
            foreach ($commentRows as $row) {
                $reviewId = (int) ($row['review_id'] ?? 0);
                if (!isset($commentsByReviewId[$reviewId])) {
                    $commentsByReviewId[$reviewId] = [];
                }
                $commentsByReviewId[$reviewId][] = $row;
            }

            $payload['review_rounds'] = array_map(static function (array $row) use ($commentsByReviewId): array {
                $reviewId = (int) ($row['id'] ?? 0);
                return [
                    'id' => $reviewId,
                    'round_number' => (int) ($row['round_number'] ?? 0),
                    'previous_review_id' => isset($row['previous_review_id']) ? (int) $row['previous_review_id'] : null,
                    'status' => $row['status'] ?? null,
                    'decision' => $row['decision'] ?? null,
                    'decided_at' => $row['decided_at'] ?? null,
                    'created_at' => $row['created_at'] ?? null,
                    'reviewer' => [
                        'id' => (int) ($row['reviewer_id'] ?? 0),
                        'name' => $row['reviewer_name'] ?? null,
                    ],
                    'comments' => $commentsByReviewId[$reviewId] ?? [],
                ];
            }, $roundRows);
        }

        $this->ok($payload);
    }

    public function update(Request $request): never
    {
        $id = (int) $request->param('id');
        $studentId = $request->user()->id;
        $research = Research::where('student_id', $studentId)->find($id);

        if (!$research) {
            $this->fail('البحث غير موجود.', 404, 'not_found');
        }

        if (!in_array($research->status, [ResearchStatus::DRAFT, ResearchStatus::REVISION_REQUESTED], true)) {
            $this->fail('يمكن تحديث البحث فقط في حالة مسودة أو طلب مراجعة.', 422, 'invalid_status');
        }

        $data = $this->validate($request, [
            'title'                  => 'string|max:255',
            'principal_investigator' => 'string|max:255',
            'co_investigators'       => 'nullable|array',
            'department'             => 'string|max:255',
            'faculty'                => 'string|max:255',
        ]);

        $research->update($data);

        if ($research->status === ResearchStatus::REVISION_REQUESTED) {
            $latest = Review::findLatestByResearch($id);
            if ($latest === false) {
                $this->fail('Cannot resubmit without an assigned reviewer.', 409, 'invalid_state');
            }

            $reviewerId = (int) ($latest['reviewer_id'] ?? 0);
            if ($reviewerId <= 0) {
                $this->fail('Cannot resubmit without an assigned reviewer.', 409, 'invalid_state');
            }

            Database::transaction(function () use ($id, $reviewerId): void {
                $created = Review::createNextRound($id, $reviewerId);
                if ($created === false) {
                    throw new \RuntimeException('Failed to create next review round.');
                }

                $updated = Database::execute(
                    'UPDATE research SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?',
                    [ResearchStatus::IN_REVIEW, $id]
                );
                if ($updated <= 0) {
                    throw new \RuntimeException('Failed to move research back to in_review.');
                }
            });

            $research = Research::where('student_id', $studentId)->find($id);
        }

        $this->ok($research);
    }
}

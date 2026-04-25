<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Models\Research;
use App\Enums\ResearchStatus;

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

    public function show(Request $request, int $id): never
    {
        $studentId = $request->user()->id;
        $research = Research::where('student_id', $studentId)->find($id);

        if (!$research) {
            $this->fail('Research not found.', 404, 'not_found');
        }

        $this->ok($research);
    }

    public function update(Request $request, int $id): never
    {
        $studentId = $request->user()->id;
        $research = Research::where('student_id', $studentId)->find($id);

        if (!$research) {
            $this->fail('Research not found.', 404, 'not_found');
        }

        if (!in_array($research->status, [ResearchStatus::DRAFT, ResearchStatus::REVISION_REQUESTED], true)) {
            $this->fail('Research can only be updated in DRAFT or REVISION_REQUESTED status.', 422, 'invalid_status');
        }

        $data = $this->validate($request, [
            'title'                  => 'string|max:255',
            'principal_investigator' => 'string|max:255',
            'co_investigators'       => 'nullable|array',
            'department'             => 'string|max:255',
            'faculty'                => 'string|max:255',
        ]);

        $research->update($data);

        $this->ok($research);
    }
}

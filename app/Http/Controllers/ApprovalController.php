<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\Project;
use App\Models\RepositoryItem;
use App\Services\SimilarityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ApprovalController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Approval::query()->with('project');

        match ($user->role) {
            'student' => $query->whereHas('project', fn ($q) => $q->where('student_id', $user->user_id)),
            'supervisor' => $query->whereHas('project', fn ($q) => $q->where('supervisor_id', $user->user_id)),
            'admin' => null,
        };

        return response()->json($query->get());
    }

    public function show(Request $request, Approval $approval)
    {
        $this->authorize('view', $approval);

        return response()->json($approval);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,project_id',
            'stage' => 'required|string|max:100',
            'status' => 'required|in:pending,approved,rejected',
            'notes' => 'nullable|string',
        ]);

        $user = $request->user();
        $project = Project::findOrFail($validated['project_id']);

        if (! $this->canDecide($user, $project)) {
            return response()->json(['message' => 'You are not authorized to approve this project.'], 403);
        }

        $approval = DB::transaction(function () use ($validated, $user, $project) {
            $approval = Approval::create([
                'project_id' => $validated['project_id'],
                'approved_by' => $user->user_id,
                'stage' => $validated['stage'],
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
                'approval_date' => $validated['status'] !== 'pending' ? now() : null,
            ]);

            $this->cascadeProjectStatus($project, $validated['stage'], $validated['status']);

            return $approval;
        });

        return response()->json($approval->load('project'), 201);
    }

    public function update(Request $request, Approval $approval)
    {
        $user = $request->user();
        $project = $approval->project;

        if (! $this->canDecide($user, $project)) {
            return response()->json(['message' => 'You are not authorized to approve this project.'], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:pending,approved,rejected',
            'notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($approval, $validated, $project, $user) {
            $approval->update([
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? $approval->notes,
                'approved_by' => $user->user_id,
                'approval_date' => $validated['status'] !== 'pending' ? now() : null,
            ]);

            $this->cascadeProjectStatus($project, $approval->stage, $validated['status']);
        });

        return response()->json($approval->load('project'));
    }

    private function canDecide($user, Project $project): bool
    {
        return match ($user->role) {
            'admin' => true,
            'supervisor' => $project->supervisor_id === $user->user_id,
            default => false,
        };
    }

    private function cascadeProjectStatus(Project $project, string $stage, string $approvalStatus): void
    {
        $newStatus = match (true) {
            $approvalStatus === 'rejected' => 'correction_required',
            $approvalStatus === 'approved' && $stage === 'final' => 'archived',
            $approvalStatus === 'approved' => 'under_review',
            default => null,
        };

        if (! $newStatus) {
            return;
        }

        $project->update(['status' => $newStatus]);

        if ($newStatus === 'archived') {
            // Find the final submission's file to extract full text from,
            // so future similarity checks can compare against this project.
            $finalSubmission = $project->submissions()
                ->where('submission_type', 'final_submission')
                ->latest('submitted_at')
                ->first();

            $fullText = '';
            $filePath = '';

            if ($finalSubmission) {
                $filePath = $finalSubmission->file_path;

                // file_path is stored as a full URL — extract the storage-relative
                // path so we can read the file directly from disk.
                $relativePath = str_replace(
                    Storage::url(''),
                    '',
                    parse_url($filePath, PHP_URL_PATH) ?? ''
                );

                $similarityService = new SimilarityService();
                $fullText = $similarityService->extractText($relativePath);
            }

            RepositoryItem::create([
                'title' => $project->title,
                'abstract' => $project->description,
                'full_text' => $fullText,
                'keywords' => [],
                'department_id' => $project->department_id,
                'session_id' => $project->session_id,
                'student_id' => $project->student_id,
                'supervisor_id' => $project->supervisor_id,
                'file_path' => $filePath,
                'archived_at' => now(),
            ]);
        }
    }
}
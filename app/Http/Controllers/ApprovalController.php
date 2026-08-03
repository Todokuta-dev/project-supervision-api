<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\Project;
use App\Models\RepositoryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        'title' => 'required|string|max:255',
        'description' => 'required|string',
        'supervisor_id' => 'nullable|exists:users,user_id',
    ]);

    $user = $request->user();

    $project = DB::transaction(function () use ($validated, $user) {
        $project = Project::create([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'student_id' => $user->user_id,
            'supervisor_id' => $validated['supervisor_id'] ?? null,
            'department_id' => $user->department_id,
            'session_id' => $user->session_id,
            'status' => 'topic_pending',
        ]);

        Approval::create([
            'project_id' => $project->project_id,
            'approved_by' => null,
            'stage' => 'topic',
            'status' => 'pending',
            'notes' => null,
            'approval_date' => null,
        ]);

        return $project;
    });

    return response()->json($project, 201);
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
            default => null, // pending — no change
        };

        if (! $newStatus) {
            return;
        }

        $project->update(['status' => $newStatus]);

        if ($newStatus === 'archived') {
            RepositoryItem::create([
                'title' => $project->title,
                'abstract' => $project->description,
                'keywords' => [],
                'department_id' => $project->department_id,
                'session_id' => $project->session_id,
                'student_id' => $project->student_id,
                'supervisor_id' => $project->supervisor_id,
                'file_path' => '', // populated later when final submission file is attached
                'archived_at' => now(),
            ]);
        }
    }
}
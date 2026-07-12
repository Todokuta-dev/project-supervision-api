<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\Project;
use Illuminate\Http\Request;

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

        $approval = Approval::create([
            'project_id' => $validated['project_id'],
            'approved_by' => $user->user_id,
            'stage' => $validated['stage'],
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
            'approval_date' => $validated['status'] !== 'pending' ? now() : null,
        ]);

        $this->cascadeProjectStatus($project, $validated['stage'], $validated['status']);

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

        $approval->update([
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? $approval->notes,
            'approval_date' => $validated['status'] !== 'pending' ? now() : null,
        ]);

        $this->cascadeProjectStatus($project, $approval->stage, $validated['status']);

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
            $approvalStatus === 'approved' && $stage === 'final' => 'approved',
            $approvalStatus === 'approved' => 'under_review',
            default => null, // pending — no change
        };

        if ($newStatus) {
            $project->update(['status' => $newStatus]);
        }
    }
}
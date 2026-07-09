<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Submission;
use Illuminate\Http\Request;

class SubmissionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Submission::query()->with('project');

        match ($user->role) {
            'student' => $query->whereHas('project', fn ($q) => $q->where('student_id', $user->user_id)),
            'supervisor' => $query->whereHas('project', fn ($q) => $q->where('supervisor_id', $user->user_id)),
            'admin' => null,
        };

        return response()->json($query->get());
    }

    public function show(Request $request, Submission $submission)
    {
        $this->authorize('view', $submission);

        return response()->json($submission);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,project_id',
            'submission_type' => 'required|in:topic_proposal,chapter_draft,final_submission',
            'chapter_label' => 'nullable|string|max:255',
            'file_name' => 'required|string|max:255',
            'file_path' => 'required|string|max:255',
        ]);

        $user = $request->user();
        $project = Project::findOrFail($validated['project_id']);

        // Only the owning student can submit against their own project
        if ($user->role !== 'student' || $project->student_id !== $user->user_id) {
            return response()->json(['message' => 'You are not authorized to submit to this project.'], 403);
        }

        $submission = Submission::create([
            ...$validated,
            'submitted_at' => now(),
        ]);

        return response()->json($submission, 201);
    }
}
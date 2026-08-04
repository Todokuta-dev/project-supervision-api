<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Storage;
use App\Models\SimilarityReport;

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
        'file' => 'required|file|mimes:pdf,doc,docx|max:20480',
    ]);

    $user = $request->user();
    $project = Project::findOrFail($validated['project_id']);

    if ($user->role !== 'student' || $project->student_id !== $user->user_id) {
        return response()->json(['message' => 'You are not authorized to submit to this project.'], 403);
    }

    if ($project->status === 'archived') {
    return response()->json(['message' => 'This project has already been completed and archived. No further submissions are accepted.'], 403);
}

    $file = $request->file('file');
    $path = $file->store('submissions', 'public');
    $fileUrl = $request->getSchemeAndHttpHost() . Storage::url($path);

    $submission = Submission::create([
        'project_id' => $validated['project_id'],
        'submission_type' => $validated['submission_type'],
        'chapter_label' => $validated['chapter_label'] ?? null,
        'file_name' => $file->getClientOriginalName(),
        'file_path' => $fileUrl,
        'submitted_at' => now(),
    ]);

    SimilarityReport::create([
        'submission_id' => $submission->submission_id,
        'score' => 0,
        'matched_sources' => [],
        'generated_at' => now(),
    ]);

    return response()->json($submission, 201);
}
}
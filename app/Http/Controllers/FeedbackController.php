<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Models\Submission;
use App\Models\RepositoryItem;
use App\Services\SimilarityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class FeedbackController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Feedback::query()->with('submission.project');

        match ($user->role) {
            'student' => $query->whereHas('submission.project', fn ($q) => $q->where('student_id', $user->user_id)),
            'supervisor' => $query->whereHas('submission.project', fn ($q) => $q->where('supervisor_id', $user->user_id)),
            'admin' => null,
        };

        return response()->json($query->get());
    }

    public function show(Request $request, Feedback $feedback)
    {
        $this->authorize('view', $feedback);

        return response()->json($feedback);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'submission_id' => 'required|exists:submissions,submission_id',
            'comments' => 'required|string',
            'decision' => 'required|in:approve,correction_required',
        ]);

        $user = $request->user();
        $submission = Submission::with('project')->findOrFail($validated['submission_id']);

        if ($user->role !== 'supervisor' || $submission->project->supervisor_id !== $user->user_id) {
            return response()->json(['message' => 'You are not authorized to give feedback on this submission.'], 403);
        }

        $feedback = DB::transaction(function () use ($validated, $user, $submission) {
            $feedback = Feedback::create([
                'submission_id' => $validated['submission_id'],
                'supervisor_id' => $user->user_id,
                'comments' => $validated['comments'],
                'decision' => $validated['decision'],
                'created_at' => now(),
            ]);

            // If this feedback approves a final submission, close out the
            // project automatically: archive it and create the repository entry.
            if (
                $validated['decision'] === 'approve'
                && $submission->submission_type === 'final_submission'
            ) {
                $this->archiveProject($submission->project, $submission);
            }

            return $feedback;
        });

        return response()->json($feedback);
    }

    private function archiveProject($project, Submission $finalSubmission): void
    {
        $project->update(['status' => 'archived']);

        $filePath = $finalSubmission->file_path;

        $relativePath = str_replace(
            Storage::url(''),
            '',
            parse_url($filePath, PHP_URL_PATH) ?? ''
        );

        $similarityService = new SimilarityService();
        $fullText = $similarityService->extractText($relativePath);

        RepositoryItem::create([
            'title' => $project->title,
            'abstract' => $project->description,
            'full_text' => $fullText,
            'keywords' => $similarityService->extractKeywords($fullText),
            'department_id' => $project->department_id,
            'session_id' => $project->session_id,
            'student_id' => $project->student_id,
            'supervisor_id' => $project->supervisor_id,
            'file_path' => $filePath,
            'archived_at' => now(),
        ]);
    }
}
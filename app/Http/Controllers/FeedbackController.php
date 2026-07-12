<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Models\Submission;
use Illuminate\Http\Request;

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

        // Only the project's assigned supervisor can leave feedback
        if ($user->role !== 'supervisor' || $submission->project->supervisor_id !== $user->user_id) {
            return response()->json(['message' => 'You are not authorized to give feedback on this submission.'], 403);
        }

        $feedback = Feedback::create([
            'submission_id' => $validated['submission_id'],
            'supervisor_id' => $user->user_id,
            'comments' => $validated['comments'],
            'decision' => $validated['decision'],
            'created_at' => now(),
        ]);

        return response()->json($feedback, 201);
    }
}
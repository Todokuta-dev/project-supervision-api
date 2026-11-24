<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Submission;
use App\Models\SimilarityReport;
use App\Models\Feedback;
use App\Services\SimilarityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
            'chapter_label' => 'required_if:submission_type,chapter_draft|nullable|string|max:255',
            'file' => 'required|file|mimes:pdf,doc,docx,txt,rtf,odt,odp,ods,ppt,pptx,xls,xlsx,png,jpg,jpeg,tif,tiff,bmp|max:20480',
        ]);

        $user = $request->user();
        $project = Project::findOrFail($validated['project_id']);

        if ($user->role !== 'student' || $project->student_id !== $user->user_id) {
            return response()->json(['message' => 'You are not authorized to submit to this project.'], 403);
        }

        if ($project->status === 'archived') {
            return response()->json(['message' => 'This project has already been completed and archived. No further submissions are accepted.'], 403);
        }

        // Block resubmission once a final submission is pending review or
        // already approved — only a "correction_required" decision on the
        // final submission allows another submission through.
        $latestSubmission = $project->submissions()->latest('submitted_at')->first();

        if ($latestSubmission && $latestSubmission->submission_type === 'final_submission') {
            $feedback = Feedback::where('submission_id', $latestSubmission->submission_id)->first();

            if (! $feedback) {
                return response()->json(['message' => 'Your final submission is awaiting supervisor review. You cannot submit again until feedback is given.'], 403);
            }

            if ($feedback->decision === 'approve') {
                return response()->json(['message' => 'Your final submission has already been approved and is awaiting final sign-off. No further submissions are needed.'], 403);
            }

            // decision === 'correction_required' — fall through, allow resubmission
        }

        $file = $request->file('file');
        $path = $file->store('submissions', 'public');
        // Build the public URL from the configured disk URL (APP_URL), not
        // the request scheme, which may appear as HTTP behind Railway's proxy.
        $fileUrl = Storage::disk('public')->url($path);

        $similarityService = new SimilarityService();
        $extractedText = $similarityService->extractText($path);

        $submission = Submission::create([
            'project_id' => $validated['project_id'],
            'submission_type' => $validated['submission_type'],
            'chapter_label' => $validated['chapter_label'] ?? null,
            'full_text' => $extractedText,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $fileUrl,
            'submitted_at' => now(),
        ]);

        // Compare against the repository and other submissions at the same stage.
        $repositoryResult = $similarityService->compareAgainstRepository($extractedText);
        $sameStageMatches = $similarityService->compareAgainstSameStage(
            $extractedText,
            $validated['submission_type'],
            $validated['chapter_label'] ?? null,
            $validated['project_id']
        );

        $allMatches = array_merge($repositoryResult['matched_sources'], $sameStageMatches);
        usort($allMatches, fn ($a, $b) => $b['matched_percentage'] <=> $a['matched_percentage']);
        $allMatches = array_slice($allMatches, 0, 5);
        $overallScore = empty($allMatches) ? 0 : $allMatches[0]['matched_percentage'];
        $analysisStatus = $similarityService->analysisStatus($extractedText);

        SimilarityReport::create([
            'submission_id' => $submission->submission_id,
            'score' => $analysisStatus === 'complete' ? $overallScore : null,
            'analysis_status' => $analysisStatus,
            'matched_sources' => $allMatches,
            'generated_at' => now(),
        ]);

        return response()->json($submission, 201);
    }
}

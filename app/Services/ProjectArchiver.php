<?php

namespace App\Services;

use App\Models\Project;
use App\Models\RepositoryItem;
use App\Models\Submission;

class ProjectArchiver
{
    public function archive(Project $project, ?Submission $finalSubmission = null): RepositoryItem
    {
        $finalSubmission ??= $project->submissions()
            ->where('submission_type', 'final_submission')
            ->latest('submitted_at')
            ->first();

        $fullText = $finalSubmission?->full_text ?? '';

        $project->update(['status' => 'archived']);

        return RepositoryItem::firstOrCreate(
            ['project_id' => $project->project_id],
            [
                'title' => $project->title,
                'abstract' => $project->description,
                'full_text' => $fullText,
                'keywords' => app(SimilarityService::class)->extractKeywords($fullText),
                'department_id' => $project->department_id,
                'session_id' => $project->session_id,
                'student_id' => $project->student_id,
                'supervisor_id' => $project->supervisor_id,
                'file_path' => $finalSubmission?->file_path ?? '',
                'archived_at' => now(),
            ]
        );
    }
}

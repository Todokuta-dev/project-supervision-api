<?php

namespace App\Policies;

use App\Models\SimilarityReport;
use App\Models\User;

class SimilarityReportPolicy
{
    public function view(User $user, SimilarityReport $report): bool
    {
        $project = $report->submission->project;

        return match ($user->role) {
            'admin' => true,
            'student' => $project->student_id === $user->user_id,
            'supervisor' => $project->supervisor_id === $user->user_id,
            default => false,
        };
    }

    public function delete(User $user, SimilarityReport $report): bool
    {
        return $user->role === 'admin';
    }
}
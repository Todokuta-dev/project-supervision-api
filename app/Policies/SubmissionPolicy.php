<?php

namespace App\Policies;

use App\Models\Submission;
use App\Models\User;

class SubmissionPolicy
{
    public function view(User $user, Submission $submission): bool
    {
        return match ($user->role) {
            'admin' => true,
            'student' => $submission->project->student_id === $user->user_id,
            'supervisor' => $submission->project->supervisor_id === $user->user_id,
            default => false,
        };
    }
}
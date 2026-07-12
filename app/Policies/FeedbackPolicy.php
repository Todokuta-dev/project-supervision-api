<?php

namespace App\Policies;

use App\Models\Feedback;
use App\Models\User;

class FeedbackPolicy
{
    public function view(User $user, Feedback $feedback): bool
    {
        $project = $feedback->submission->project;

        return match ($user->role) {
            'admin' => true,
            'student' => $project->student_id === $user->user_id,
            'supervisor' => $project->supervisor_id === $user->user_id,
            default => false,
        };
    }
}
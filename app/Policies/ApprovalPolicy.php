<?php

namespace App\Policies;

use App\Models\Approval;
use App\Models\User;

class ApprovalPolicy
{
    public function view(User $user, Approval $approval): bool
    {
        return match ($user->role) {
            'admin' => true,
            'student' => $approval->project->student_id === $user->user_id,
            'supervisor' => $approval->project->supervisor_id === $user->user_id,
            default => false,
        };
    }
}
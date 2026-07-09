<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function view(User $user, Project $project): bool
    {
        return match ($user->role) {
            'admin' => true,
            'student' => $project->student_id === $user->user_id,
            'supervisor' => $project->supervisor_id === $user->user_id,
            default => false,
        };
    }

    public function update(User $user, Project $project): bool
    {
        return match ($user->role) {
            'admin' => true,
            'supervisor' => $project->supervisor_id === $user->user_id,
            default => false,
        };
    }
}
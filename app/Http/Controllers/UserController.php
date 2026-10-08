<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Project;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'role' => 'sometimes|in:student,supervisor,admin',
        ]);

        $query = User::query()->select([
            'user_id',
            'full_name',
            'email',
            'role',
            'department_id',
            'session_id',
            'created_at',
        ]);

        if ($user->role !== 'admin') {
            if (! in_array($user->role, ['student', 'supervisor'], true)) {
                return response()->json(['message' => 'You are not authorized to view users.'], 403);
            }

            $projectIds = Project::query()
                ->where($user->role === 'student' ? 'student_id' : 'supervisor_id', $user->user_id)
                ->pluck('project_id');

            $relatedUserIds = Project::query()
                ->whereIn('project_id', $projectIds)
                ->select('student_id')
                ->union(
                    Project::query()
                        ->whereIn('project_id', $projectIds)
                        ->select('supervisor_id')
                );

            $query->where(function ($query) use ($relatedUserIds, $user) {
                $query->whereIn('user_id', $relatedUserIds)
                    ->orWhere('user_id', $user->user_id);
            });
        }

        if (isset($validated['role'])) {
            $query->where('role', $validated['role']);
        }

        return response()->json($query->get());
    }
}

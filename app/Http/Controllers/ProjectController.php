<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Approval;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
   public function index(Request $request)
{
    $user = $request->user();
    $query = Project::query();

    match ($user->role) {
        'student' => $query->where('student_id', $user->user_id),
        'supervisor' => $query->where('supervisor_id', $user->user_id),
        'admin' => null,
    };

    if ($request->filled('student_id')) {
        $query->where('student_id', $request->student_id);
    }
    if ($request->filled('supervisor_id')) {
        $query->where('supervisor_id', $request->supervisor_id);
    }
    if ($request->filled('session_id')) {
        $query->where('session_id', $request->session_id);
    }
    if ($request->filled('department_id')) {
        $query->where('department_id', $request->department_id);
    }
    if ($request->filled('status')) {
        $query->where('status', $request->status);
    }

    return response()->json($query->get());
}

    public function show(Request $request, Project $project)
    {
        $this->authorize('view', $project);

        return response()->json($project);
    }

public function store(Request $request)
{
    $validated = $request->validate([
        'title' => 'required|string|max:255',
        'description' => 'required|string',
        'supervisor_id' => 'nullable|exists:users,user_id',
    ]);

    $user = $request->user();

    $project = DB::transaction(function () use ($validated, $user) {
        $project = Project::create([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'student_id' => $user->user_id,
            'supervisor_id' => $validated['supervisor_id'] ?? null,
            'department_id' => $user->department_id,
            'session_id' => $user->session_id,
            'status' => 'topic_pending',
        ]);

        Approval::create([
            'project_id' => $project->project_id,
            'approved_by' => null,
            'stage' => 'topic',
            'status' => 'pending',
            'notes' => null,
            'approval_date' => null,
        ]);

        return $project;
    });

    return response()->json($project, 201);
}

    public function update(Request $request, Project $project)
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'status' => 'sometimes|in:topic_pending,under_review,correction_required,approved,archived',
        ]);

        $project->update($validated);

        return response()->json($project);
    }

    public function assignSupervisor(Request $request, Project $project)
{
    if ($request->user()->role !== 'admin') {
        return response()->json(['message' => 'Only admins can assign supervisors.'], 403);
    }

    $validated = $request->validate([
        'supervisor_id' => 'required|exists:users,user_id',
    ]);

    $project->update(['supervisor_id' => $validated['supervisor_id']]);

    return response()->json($project);
}
}


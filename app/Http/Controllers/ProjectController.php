<?php

namespace App\Http\Controllers;

use App\Models\Project;
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
            'admin' => null, // no filter — sees everything
        };

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
            'supervisor_id' => 'required|exists:users,user_id',
        ]);

        $user = $request->user();

        $project = Project::create([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'student_id' => $user->user_id,
            'supervisor_id' => $validated['supervisor_id'],
            'department_id' => $user->department_id,
            'session_id' => $user->session_id,
            'status' => 'topic_pending',
        ]);

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
}
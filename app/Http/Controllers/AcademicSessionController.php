<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcademicSessionController extends Controller
{
    public function index(Request $request)
{
    return response()->json(AcademicSession::orderByDesc('start_date')->get());
}

    public function active()
    {
        $session = AcademicSession::where('is_active', true)->first();

        if (! $session) {
            return response()->json(['message' => 'No active academic session found.'], 404);
        }

        return response()->json($session);
    }

    public function store(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Only admins can create sessions.'], 403);
        }

        $validated = $request->validate([
            'label' => 'required|string|min:4|max:50',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_active' => 'boolean',
        ]);

        $session = DB::transaction(function () use ($validated) {
            if (! empty($validated['is_active'])) {
                AcademicSession::where('is_active', true)->update(['is_active' => false]);
            }

            return AcademicSession::create($validated);
        });

        return response()->json($session, 201);
    }

    public function update(Request $request, AcademicSession $academicSession)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Only admins can update sessions.'], 403);
        }

        $validated = $request->validate([
            'label' => 'required|string|min:4|max:50',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_active' => 'boolean',
        ]);

        DB::transaction(function () use ($validated, $academicSession) {
            if (! empty($validated['is_active'])) {
                AcademicSession::where('is_active', true)
                    ->where('session_id', '!=', $academicSession->session_id)
                    ->update(['is_active' => false]);
            }

            $academicSession->update($validated);
        });

        return response()->json($academicSession->fresh());
    }

    public function setActive(Request $request, AcademicSession $academicSession)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Only admins can change the active session.'], 403);
        }

        DB::transaction(function () use ($academicSession) {
            AcademicSession::where('is_active', true)->update(['is_active' => false]);
            $academicSession->update(['is_active' => true]);
        });

        return response()->json($academicSession->fresh());
    }

    public function destroy(Request $request, AcademicSession $academicSession)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Only admins can delete sessions.'], 403);
        }

        if ($academicSession->is_active) {
            return response()->json(['message' => 'Cannot delete the active session.'], 422);
        }

        $academicSession->delete();

        return response()->json(['message' => 'Session deleted.']);
    }
}
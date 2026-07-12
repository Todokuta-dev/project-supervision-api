<?php

namespace App\Http\Controllers;


use App\Models\SimilarityReport;
use Illuminate\Http\Request;

class SimilarityReportController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = SimilarityReport::query()->with('submission.project');

        match ($user->role) {
            'student' => $query->whereHas('submission.project', fn ($q) => $q->where('student_id', $user->user_id)),
            'supervisor' => $query->whereHas('submission.project', fn ($q) => $q->where('supervisor_id', $user->user_id)),
            'admin' => null,
        };

        return response()->json($query->get());
    }

    public function show(Request $request, SimilarityReport $report)
    {
        $this->authorize('view', $report);

        return response()->json($report);
    }

    public function destroy(Request $request, SimilarityReport $report)
    {
        $this->authorize('delete', $report);

        $report->delete();

        return response()->json(['message' => 'Similarity report deleted.']);
    }
}
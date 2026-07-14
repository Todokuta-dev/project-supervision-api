<?php

namespace App\Http\Controllers;

use App\Models\AcademicSession;

class AcademicSessionController extends Controller
{
    public function active()
    {
        $session = AcademicSession::where('is_active', true)->first();

        if (! $session) {
            return response()->json(['message' => 'No active academic session found.'], 404);
        }

        return response()->json($session);
    }
}
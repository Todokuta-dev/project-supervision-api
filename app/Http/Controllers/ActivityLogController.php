<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Only admins can view activity logs.'], 403);
        }

        return response()->json(
            ActivityLog::query()->orderByDesc('timestamp')->get()
        );
    }
}
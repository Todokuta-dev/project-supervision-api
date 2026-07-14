<?php

namespace App\Http\Controllers;

use App\Models\RepositoryItem;
use Illuminate\Http\Request;

class RepositoryItemController extends Controller
{
    public function index(Request $request)
    {
        $query = RepositoryItem::query();

        if ($request->keyword) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'LIKE', "%{$request->keyword}%")
                  ->orWhere('abstract', 'LIKE', "%{$request->keyword}%")
                  ->orWhere('keywords', 'LIKE', "%{$request->keyword}%");
            });
        }

        if ($request->department_id) {
            $query->where('department_id', $request->department_id);
        }

        // Session filter is optional — deliberately not applied by default,
        // so repository search works across all sessions unless narrowed.
        if ($request->session_id) {
            $query->where('session_id', $request->session_id);
        }

        return response()->json($query->get());
    }

    public function show(Request $request, RepositoryItem $item)
    {
        return response()->json($item);
    }

    public function destroy(Request $request, RepositoryItem $item)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Only admins can delete repository items.'], 403);
        }

        $item->delete();

        return response()->json(['message' => 'Repository item deleted.']);
    }
}
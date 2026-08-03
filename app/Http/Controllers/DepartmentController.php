<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index()
    {
        return response()->json(Department::all());
    }

    public function store(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Only admins can create departments.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|min:3|max:255',
            'code' => 'required|string|min:2|max:20|unique:departments,code',
        ]);

        $department = Department::create([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
        ]);

        return response()->json($department, 201);
    }

    public function update(Request $request, Department $department)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Only admins can update departments.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|min:3|max:255',
            'code' => 'required|string|min:2|max:20|unique:departments,code,' . $department->department_id . ',department_id',
        ]);

        $department->update([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
        ]);

        return response()->json($department);
    }

    public function destroy(Request $request, Department $department)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Only admins can delete departments.'], 403);
        }

        $department->delete();

        return response()->json(['message' => 'Department deleted.']);
    }
}
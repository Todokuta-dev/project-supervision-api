<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repository_items', function (Blueprint $table) {
            $table->foreignUuid('project_id')
                ->nullable()
                ->constrained('projects', 'project_id');
            $table->foreignUuid('supervisor_id')->nullable()->change();
        });

        // Link legacy archive rows only when their project can be identified
        // unambiguously. Keep duplicate or ambiguous legacy rows untouched.
        $assignedProjectIds = [];
        $items = DB::table('repository_items')->whereNull('project_id')->get([
            'repo_id',
            'student_id',
            'title',
            'department_id',
            'session_id',
        ]);

        foreach ($items as $item) {
            $projectIds = DB::table('projects')
                ->where('student_id', $item->student_id)
                ->where('title', $item->title)
                ->where('department_id', $item->department_id)
                ->where('session_id', $item->session_id)
                ->pluck('project_id');

            if ($projectIds->count() !== 1 || isset($assignedProjectIds[$projectIds->first()])) {
                continue;
            }

            $projectId = $projectIds->first();
            DB::table('repository_items')
                ->where('repo_id', $item->repo_id)
                ->update(['project_id' => $projectId]);
            $assignedProjectIds[$projectId] = true;
        }

        Schema::table('repository_items', function (Blueprint $table) {
            $table->unique('project_id');
        });
    }

    public function down(): void
    {
        Schema::table('repository_items', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropUnique(['project_id']);
            $table->dropColumn('project_id');
        });
    }
};

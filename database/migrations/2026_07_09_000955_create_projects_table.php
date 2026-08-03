<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('project_id')->primary();
            $table->string('title');
            $table->text('description');
            $table->foreignUuid('student_id')->constrained('users', 'user_id');
            $table->foreignUuid('supervisor_id')->nullable()->constrained('users', 'user_id');
            $table->foreignUuid('department_id')->constrained('departments', 'department_id');
            $table->foreignUuid('session_id')->constrained('academic_sessions', 'session_id');
            $table->enum('status', ['topic_pending', 'under_review', 'correction_required', 'approved', 'archived'])->default('topic_pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
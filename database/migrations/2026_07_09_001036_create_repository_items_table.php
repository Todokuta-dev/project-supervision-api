<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repository_items', function (Blueprint $table) {
            $table->uuid('repo_id')->primary();
            $table->string('title');
            $table->text('abstract');
            $table->json('keywords');
            $table->foreignUuid('department_id')->constrained('departments', 'department_id');
            $table->foreignUuid('session_id')->constrained('academic_sessions', 'session_id');
            $table->foreignUuid('student_id')->constrained('users', 'user_id');
            $table->foreignUuid('supervisor_id')->constrained('users', 'user_id');
            $table->string('file_path');
            $table->timestamp('archived_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repository_items');
    }
};
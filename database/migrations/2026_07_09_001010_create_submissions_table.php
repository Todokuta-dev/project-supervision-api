<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->uuid('submission_id')->primary();
            $table->foreignUuid('project_id')->constrained('projects', 'project_id');
            $table->enum('submission_type', ['topic_proposal', 'chapter_draft', 'final_submission']);
            $table->string('chapter_label')->nullable();
            $table->string('file_path');
            $table->string('file_name');
            $table->timestamp('submitted_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
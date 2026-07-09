<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback', function (Blueprint $table) {
            $table->uuid('feedback_id')->primary();
            $table->foreignUuid('submission_id')->constrained('submissions', 'submission_id');
            $table->foreignUuid('supervisor_id')->constrained('users', 'user_id');
            $table->text('comments');
            $table->enum('decision', ['approve', 'correction_required']);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback');
    }
};
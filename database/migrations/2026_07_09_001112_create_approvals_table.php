<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approvals', function (Blueprint $table) {
            $table->uuid('approval_id')->primary();
            $table->foreignUuid('project_id')->constrained('projects', 'project_id');
            $table->foreignUuid('approved_by')->constrained('users', 'user_id');
            $table->string('stage');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('approval_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
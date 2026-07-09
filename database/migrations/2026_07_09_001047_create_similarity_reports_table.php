<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('similarity_reports', function (Blueprint $table) {
            $table->uuid('report_id')->primary();
            $table->foreignUuid('submission_id')->constrained('submissions', 'submission_id');
            $table->decimal('score', 5, 2);
            $table->timestamp('generated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('similarity_reports');
    }
};
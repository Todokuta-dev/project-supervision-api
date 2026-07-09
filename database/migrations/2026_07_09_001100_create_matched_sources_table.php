<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matched_sources', function (Blueprint $table) {
            $table->uuid('matched_source_id')->primary();
            $table->foreignUuid('report_id')->constrained('similarity_reports', 'report_id')->cascadeOnDelete();
            $table->string('source_title');
            $table->string('source_id');
            $table->decimal('matched_percentage', 5, 2);
            $table->text('matched_excerpt');
            $table->text('source_excerpt');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matched_sources');
    }
};
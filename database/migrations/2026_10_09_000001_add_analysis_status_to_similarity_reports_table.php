<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('similarity_reports', function (Blueprint $table) {
            $table->string('analysis_status')->default('complete');
        });

        $failedSubmissionIds = DB::table('submissions')
            ->whereNull('full_text')
            ->orWhere('full_text', '')
            ->pluck('submission_id');

        if ($failedSubmissionIds->isNotEmpty()) {
            DB::table('similarity_reports')
                ->whereIn('submission_id', $failedSubmissionIds)
                ->update(['analysis_status' => 'extraction_failed']);
        }
    }

    public function down(): void
    {
        Schema::table('similarity_reports', function (Blueprint $table) {
            $table->dropColumn('analysis_status');
        });
    }
};

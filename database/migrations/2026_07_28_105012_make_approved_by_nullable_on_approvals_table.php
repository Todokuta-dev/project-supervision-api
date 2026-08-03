<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE approvals DROP FOREIGN KEY approvals_approved_by_foreign');
        DB::statement('ALTER TABLE approvals MODIFY approved_by CHAR(36) NULL');
        DB::statement('ALTER TABLE approvals ADD CONSTRAINT approvals_approved_by_foreign FOREIGN KEY (approved_by) REFERENCES users(user_id)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE approvals DROP FOREIGN KEY approvals_approved_by_foreign');
        DB::statement('ALTER TABLE approvals MODIFY approved_by CHAR(36) NOT NULL');
        DB::statement('ALTER TABLE approvals ADD CONSTRAINT approvals_approved_by_foreign FOREIGN KEY (approved_by) REFERENCES users(user_id)');
    }
};
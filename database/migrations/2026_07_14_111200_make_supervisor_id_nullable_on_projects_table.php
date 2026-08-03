<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE projects DROP FOREIGN KEY projects_supervisor_id_foreign');
        DB::statement('ALTER TABLE projects MODIFY supervisor_id CHAR(36) NULL');
        DB::statement('ALTER TABLE projects ADD CONSTRAINT projects_supervisor_id_foreign FOREIGN KEY (supervisor_id) REFERENCES users(user_id)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE projects DROP FOREIGN KEY projects_supervisor_id_foreign');
        DB::statement('ALTER TABLE projects MODIFY supervisor_id CHAR(36) NOT NULL');
        DB::statement('ALTER TABLE projects ADD CONSTRAINT projects_supervisor_id_foreign FOREIGN KEY (supervisor_id) REFERENCES users(user_id)');
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->uuid('log_id')->primary();
            $table->foreignUuid('actor_id')->constrained('users', 'user_id');
            $table->string('actor_name');
            $table->enum('actor_role', ['student', 'supervisor', 'admin']);
            $table->string('action');
            $table->string('entity_type');
            $table->string('entity_id');
            $table->timestamp('timestamp')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
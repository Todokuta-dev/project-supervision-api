<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['supervisor_id']);
            $table->uuid('supervisor_id')->nullable()->change();
            $table->foreign('supervisor_id')->references('user_id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['supervisor_id']);
            $table->uuid('supervisor_id')->nullable(false)->change();
            $table->foreign('supervisor_id')->references('user_id')->on('users');
        });
    }
};
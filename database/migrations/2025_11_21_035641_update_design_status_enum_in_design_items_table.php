<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE design_items MODIFY COLUMN design_status ENUM('in_progress', 'revision', 'approved') NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE design_items MODIFY COLUMN design_status ENUM('revision', 'approved') NULL");
    }
};

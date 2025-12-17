<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('spk', function (Blueprint $table) {
            if (Schema::hasColumn('spk', 'assigned_to')) {
                $table->dropForeign(['assigned_to']);
                $table->dropColumn('assigned_to');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spk', function (Blueprint $table) {
            $table->foreignId('assigned_to')
                ->after('spk_date')
                ->constrained('users')
                ->cascadeOnUpdate();
        });
    }
};

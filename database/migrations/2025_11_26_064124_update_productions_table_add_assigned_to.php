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
        Schema::table('productions', function (Blueprint $table) {
            if (Schema::hasColumn('productions', 'production_type')) {
                $table->dropColumn('production_type');
            }

            if (Schema::hasColumn('productions', 'production_status')) {
                $table->dropColumn('production_status');
            }
        });

        Schema::table('productions', function (Blueprint $table) {
            $table->foreignId('assigned_to')
                ->nullable()
                ->after('order_id')
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productions', function (Blueprint $table) {
            $table->enum('production_type', ['in_house', 'vendor']);
            $table->enum('production_status', ['in_progress','completed'])->default('in_progress');

            $table->dropForeign(['assigned_to']);
            $table->dropColumn('assigned_to');
        });
    }
};

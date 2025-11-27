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
        Schema::table('vendor_details', function (Blueprint $table) {
            try {
                $table->dropForeign(['production_id']);
            } catch (\Exception $e) {
            }

            if (Schema::hasColumn('vendor_details', 'production_id')) {
                $table->dropColumn('production_id');
            }
        });

        Schema::table('vendor_details', function (Blueprint $table) {
            $table->foreignId('production_detail_id')
                ->nullable()
                ->after('id') 
                ->constrained('production_details')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_details', function (Blueprint $table) {
            $table->foreignId('production_id')
                ->nullable()
                ->constrained('productions')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        Schema::table('vendor_details', function (Blueprint $table) {
            try {
                $table->dropForeign(['production_detail_id']);
            } catch (\Exception $e) {}

            if (Schema::hasColumn('vendor_details', 'production_detail_id')) {
                $table->dropColumn('production_detail_id');
            }
        });
    }
};

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
            $table->foreignId('vendor_id')
                ->constrained('vendors')
                ->cascadeOnDelete()
                ->after('production_detail_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_details', function (Blueprint $table) {
            $table->dropColumn('vendor_id');

        });
    }
};

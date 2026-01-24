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
            if (Schema::hasColumn('vendor_details', 'vendor_name')) {
                $table->dropColumn('vendor_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_details', function (Blueprint $table) {
            $table->string('vendor_name')->after('production_detail_id');
        });
    }
};

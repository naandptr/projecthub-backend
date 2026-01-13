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
            $table->date('start_date')->nullable()->after('vendor_name');
            $table->date('deadline')->nullable()->after('start_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vendor_details', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'deadline']);
        });
    }
};

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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('cust_name');
            $table->string('cust_phone');
            $table->string('cust_address');
            $table->date('order_date');
            $table->date('order_deadline');
            $table->string('product_name');
            $table->integer('product_quantity');
            $table->decimal('product_price', 10, 2);
            $table->string('order_file')->nullable();
            $table->text('order_notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

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
            $table->string('product_name');
            $table->string('SKU');
            $table->string('qty');
            $table->string('points');
            $table->string('reference');
            $table->unsignedBigInteger('user_id')->constrained()->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users');
            $table->unsignedBigInteger('pickup_location');
            $table->foreign('pickup_location')->references('id')->on('locations');
            $table->enum('status', ['Picked up', 'Pending'])->default('Pending');
            $table->timestamps();
            $table->softDeletes();
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

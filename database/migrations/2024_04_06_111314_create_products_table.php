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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('product_name');
            $table->string('product_type');
            $table->text('description');
            $table->string('SKU');
            $table->json('tag');
            $table->unsignedBigInteger('category_id')->constrained()->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories');
            $table->integer('points');
            $table->unsignedBigInteger('location_id')->constrained()->onDelete('cascade');
            $table->foreign('location_id')->references('id')->on('locations');
            $table->json('variation');
            $table->integer('available_qty');
            $table->unsignedBigInteger('created_by')->constrained()->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users');
            $table->enum('status', ['In Stock', 'Out of Stock', 'Limited Stock'])->default('In Stock');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

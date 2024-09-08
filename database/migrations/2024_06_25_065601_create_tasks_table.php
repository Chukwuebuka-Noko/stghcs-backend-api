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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->string('start_date');
            $table->string('end_date')->nullable();
            $table->unsignedBigInteger('assigned_to')->constrained()->onDelete('cascade');
            $table->foreign('assigned_to')->references('id')->on('users');
            $table->unsignedBigInteger('created_by')->constrained()->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users');
            $table->enum('status', ['started', 'ended'])->default('started');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};

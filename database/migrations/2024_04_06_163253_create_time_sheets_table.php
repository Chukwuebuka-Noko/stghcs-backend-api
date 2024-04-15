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
        Schema::create('time_sheets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('gig_id')->constrained()->onDelete('cascade');
            $table->foreign('gig_id')->references('id')->on('gigs');
            $table->unsignedBigInteger('user_id')->constrained()->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users');
            $table->timestamp('clock_in')->nullable();
            $table->timestamp('clock_out')->nullable();
            $table->json('clock_in_coordinate')->nullable();
            $table->json('clock_out_coordinate')->nullable();
            $table->enum('clock_in_status', ['On Time', 'Came Early', 'Came Late']);
            $table->enum('clock_out_status', ['On Time', 'Left Early', 'Over Time']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_sheets');
    }
};

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
        Schema::create('assign_gigs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('gig_id')->constrained()->onDelete('cascade');
            $table->foreign('gig_id')->references('id')->on('gigs');
            $table->unsignedBigInteger('user_id')->constrained()->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users');
            $table->unsignedBigInteger('schedule_id')->constrained()->onDelete('cascade');
            $table->foreign('schedule_id')->references('id')->on('schedules');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assign_gigs');
    }
};

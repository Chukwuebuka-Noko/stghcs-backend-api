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
        Schema::create('incident_reports', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->string('timesheet_id');
            $table->string('activity_id');
            $table->string('incident_time');
            $table->string('incident_date');
            $table->string('incident_week_number');
            $table->string('incident_year');
            $table->unsignedBigInteger('gig_id')->constrained()->onDelete('cascade');
            $table->foreign('gig_id')->references('id')->on('gigs');
            $table->unsignedBigInteger('user_id')->constrained()->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incident_reports');
    }
};

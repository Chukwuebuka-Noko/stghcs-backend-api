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
        Schema::create('progress_reports', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->string('timesheet_id');
            $table->string('activity_id');
            $table->string('progress_time');
            $table->string('progress_date');
            $table->string('progress_week_number');
            $table->string('progress_year');
            $table->unsignedBigInteger('gig_id')->constrained()->onDelete('cascade');
            $table->foreign('gig_id')->references('id')->on('gigs');
            $table->unsignedBigInteger('support_worker_id')->constrained()->onDelete('cascade');
            $table->foreign('support_worker_id')->references('id')->on('users');
            $table->json('task_performed')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('progress_reports');
    }
};

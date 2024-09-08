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
        Schema::create('weekly_sign_offs', function (Blueprint $table) {
            $table->id();
            $table->string('timesheet_id');
            $table->text('support_worker_signature');
            $table->text('client_signature');
            $table->string('sign_off_time');
            $table->string('sign_off_date');
            $table->string('sign_off_day');
            $table->string('sign_off_week_number');
            $table->string('sign_off_year');
            $table->unsignedBigInteger('gig_id')->constrained()->onDelete('cascade');
            $table->foreign('gig_id')->references('id')->on('gigs');
            $table->unsignedBigInteger('support_worker_id')->constrained()->onDelete('cascade');
            $table->foreign('support_worker_id')->references('id')->on('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weekly_sign_offs');
    }
};

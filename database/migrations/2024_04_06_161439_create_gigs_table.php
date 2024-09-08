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
        Schema::create('gigs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('gig_unique_id')->unique();
            $table->text('description');
            $table->unsignedBigInteger('client_id')->constrained()->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('clients');
            $table->unsignedBigInteger('created_by');
            $table->foreign('created_by')->references('id')->on('users');
            $table->string('gig_type');
            $table->unsignedBigInteger('supervisor_id')->constrained()->onDelete('cascade');
            $table->foreign('supervisor_id')->references('id')->on('users');
            $table->string('start_date');
            $table->integer('grace_period')->default(0);
            $table->enum('status', ['accepted', 'assigned', 'pending'])->default('pending');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gigs');
    }
};

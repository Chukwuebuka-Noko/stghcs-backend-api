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
        Schema::create('plan_of_cares', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('client_id')->constrained()->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('clients');
            $table->string('needed_support');
            $table->text('anticipated_outcome');
            $table->string('services_area_frequency');
            $table->string('review_date');
            $table->enum('status', ['active', 'suspeneded', 'completed','end'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_of_cares');
    }
};

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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('other_name')->nullable();
            $table->string('email')->unique();
            $table->string('phone_number');
            $table->unsignedBigInteger('location_id'); // Assuming 'role_id' is the foreign key
            $table->foreign('location_id')->references('id')->on('locations');
            $table->string('gender')->nullable();
            $table->text('ssn')->nullable();
            $table->string('id_card')->nullable();
            $table->string('address1')->nullable();
            $table->string('address2')->nullable();
            $table->string('city')->nullable();
            $table->string('zip_code')->nullable();
            $table->string('passport')->nullable();
            $table->string('dob')->nullable();
            $table->string('employee_id')->unique();
            $table->integer('points')->default(0);
            $table->integer('verification_code')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('is_temporary_password')->default(true);
            $table->enum('status', ['active', 'inactive', 'suspended', 'pending'])->default('pending');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};

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
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('job_id');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone');
            $table->text('address')->nullable();
            $table->string('linkedin_profile')->nullable();
            $table->string('portfolio_website')->nullable();
            $table->text('cover_letter');
            $table->string('resume_path');
            $table->string('portfolio_path')->nullable();
            $table->json('additional_documents')->nullable(); // For multiple additional files
            $table->integer('years_of_experience')->default(0);
            $table->string('current_position')->nullable();
            $table->string('current_company')->nullable();
            $table->decimal('expected_salary', 10, 2)->nullable();
            $table->enum('availability', ['immediate', '2-weeks', '1-month', '2-months', 'negotiable']);
            $table->boolean('willing_to_relocate')->default(false);
            $table->enum('status', ['pending', 'reviewing', 'shortlisted', 'interview', 'rejected', 'hired'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamps();

            $table->foreign('job_id')->references('id')->on('jobs')->onDelete('cascade');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['job_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_applications');
    }
};

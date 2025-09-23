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
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description');
            $table->longText('requirements');
            $table->string('location');
            $table->enum('type', ['full-time', 'part-time', 'contract', 'internship', 'remote']);
            $table->enum('experience_level', ['entry', 'mid', 'senior', 'executive']);
            $table->string('department')->nullable();
            $table->string('salary_range')->nullable();
            $table->text('benefits')->nullable();
            $table->date('application_deadline')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->integer('applications_count')->default(0);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->index(['is_active', 'created_at']);
            $table->index(['is_featured', 'is_active']);
            $table->index('slug');
            $table->index('type');
            $table->index('experience_level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};

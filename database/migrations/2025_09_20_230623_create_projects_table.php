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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            // show_on_home
            $table->boolean('show_on_home')->default(false);
            $table->string('cover_photo')->nullable();
            $table->longText('content1')->nullable();
            $table->string('image1')->nullable();
            $table->longText('content2')->nullable();
            $table->string('image2')->nullable();
            $table->longText('content3')->nullable();
            $table->string('image3')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->index(['is_active', 'created_at']);
            $table->index('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};

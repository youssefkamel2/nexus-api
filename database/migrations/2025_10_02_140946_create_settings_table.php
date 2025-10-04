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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->text('our_mission')->nullable();
            $table->text('our_vision')->nullable();
            $table->integer('years')->nullable();
            $table->integer('projects')->nullable();
            $table->integer('clients')->nullable();
            $table->integer('engineers')->nullable();
            $table->string('image')->nullable();
            $table->string('portfolio')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};

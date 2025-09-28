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
        Schema::table('jobs', function (Blueprint $table) {
            // Change key_responsibilities and preferred_qualifications from JSON to TEXT
            $table->text('key_responsibilities')->change();
            $table->text('preferred_qualifications')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            // Revert back to JSON
            $table->json('key_responsibilities')->change();
            $table->json('preferred_qualifications')->change();
        });
    }
};

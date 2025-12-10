<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('disciplines', function (Blueprint $table) {
            $table->string('cover_photo')->nullable()->after('description');
            $table->boolean('show_on_home')->default(false)->after('cover_photo');
            $table->integer('order')->default(0)->after('show_on_home');
            $table->string('slug')->unique()->nullable()->after('title');
        });

        // Generate slugs for existing disciplines
        $disciplines = DB::table('disciplines')->get();
        foreach ($disciplines as $discipline) {
            $slug = Str::slug($discipline->title);
            DB::table('disciplines')
                ->where('id', $discipline->id)
                ->update([
                    'slug' => $slug,
                    'order' => $discipline->id // Set order to current ID
                ]);
        }

        // Make slug non-nullable after populating
        Schema::table('disciplines', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('disciplines', function (Blueprint $table) {
            $table->dropColumn(['cover_photo', 'show_on_home', 'order', 'slug']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, migrate existing data to project_sections table
        $projects = DB::table('projects')->get();
        
        foreach ($projects as $project) {
            for ($i = 1; $i <= 3; $i++) {
                $content = $project->{"content$i"};
                $image = $project->{"image$i"};
                
                // Only create section if there's content or an image
                if ($content || $image) {
                    DB::table('project_sections')->insert([
                        'project_id' => $project->id,
                        'content' => $content,
                        'image' => $image,
                        'caption' => null, // No captions in old schema
                        'order' => $i - 1, // 0-indexed
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
        
        // Then drop the old columns
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['content1', 'image1', 'content2', 'image2', 'content3', 'image3']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back the old columns
        Schema::table('projects', function (Blueprint $table) {
            $table->longText('content1')->nullable();
            $table->string('image1')->nullable();
            $table->longText('content2')->nullable();
            $table->string('image2')->nullable();
            $table->longText('content3')->nullable();
            $table->string('image3')->nullable();
        });
        
        // Migrate data back from project_sections (best effort - only first 3 sections)
        $projects = DB::table('projects')->get();
        
        foreach ($projects as $project) {
            $sections = DB::table('project_sections')
                ->where('project_id', $project->id)
                ->orderBy('order')
                ->limit(3)
                ->get();
            
            $updateData = [];
            foreach ($sections as $index => $section) {
                $num = $index + 1;
                $updateData["content$num"] = $section->content;
                $updateData["image$num"] = $section->image;
            }
            
            if (!empty($updateData)) {
                DB::table('projects')
                    ->where('id', $project->id)
                    ->update($updateData);
            }
        }
    }
};

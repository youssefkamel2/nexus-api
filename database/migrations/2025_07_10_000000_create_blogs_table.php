<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBlogsTable extends Migration
{
    public function up()
    {
        Schema::create('blogs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('cover_photo');
            $table->enum('category', ['trending', 'guides', 'insights']);
            $table->longText('content');
            $table->boolean('mark_as_hero')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('tags')->nullable();
            $table->text('headings')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('blogs');
    }
} 
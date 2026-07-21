<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('popups', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            // content_type: image, html
            $table->string('content_type')->default('image');
            $table->string('image_path')->nullable();
            $table->longText('html_content')->nullable();
            // position: center, top-left, top-right, bottom-left, bottom-right
            $table->string('position')->default('center');
            $table->integer('width')->default(400);
            $table->integer('height')->default(300);
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('popups');
    }
};

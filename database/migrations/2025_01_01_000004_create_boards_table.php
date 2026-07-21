<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boards', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('skin')->default('default');
            // layout: list, gallery
            $table->string('layout')->default('list');
            $table->boolean('use_editor')->default(true);
            $table->boolean('allow_comment')->default(true);
            $table->boolean('allow_reply')->default(true);
            $table->boolean('allow_file')->default(true);
            $table->boolean('allow_anonymous')->default(false);
            $table->boolean('allow_image_upload')->default(true);
            $table->boolean('use_captcha')->default(false);
            $table->tinyInteger('min_read_level')->default(1);
            $table->tinyInteger('min_write_level')->default(2);
            $table->tinyInteger('min_comment_level')->default(1);
            $table->tinyInteger('files_per_post')->default(5);
            $table->tinyInteger('per_page')->default(15);
            // order_by: latest, views
            $table->string('order_by')->default('latest');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boards');
    }
};

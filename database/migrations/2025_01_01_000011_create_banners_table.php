<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('group_key');
            $table->string('title');
            $table->string('image_path');
            $table->string('link_url')->nullable();
            // link_target: _self, _blank
            $table->string('link_target')->default('_blank');
            $table->string('alt_text')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->integer('click_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('group_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};

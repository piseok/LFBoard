<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('menus')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->nullable();
            // type: url, board, page, none
            $table->string('type')->default('url');
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('url')->nullable();
            // target: _self, _blank
            $table->string('target')->default('_self');
            $table->tinyInteger('min_level')->default(1);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};

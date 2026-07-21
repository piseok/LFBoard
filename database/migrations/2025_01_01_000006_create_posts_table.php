<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_id')->constrained('boards')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('board_categories')->nullOnDelete();
            $table->string('title');
            $table->longText('content');
            $table->string('author_name', 50)->nullable();
            $table->string('author_password')->nullable();
            $table->string('ip', 45)->nullable();
            $table->integer('views')->default(0);
            $table->boolean('is_global_notice')->default(false);
            $table->boolean('is_notice')->default(false);
            $table->boolean('is_secret')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['board_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};

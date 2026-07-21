<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // type: general, quick, footer
            $table->string('type')->default('general');
            $table->string('category')->nullable();
            $table->string('name', 50);
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('author_password')->nullable();
            $table->string('title');
            $table->longText('content');
            $table->string('file_path')->nullable();
            // status: pending, processing, done
            $table->string('status')->default('pending');
            $table->longText('admin_reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inquiries');
    }
};

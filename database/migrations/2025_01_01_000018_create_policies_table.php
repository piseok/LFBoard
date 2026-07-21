<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policies', function (Blueprint $table) {
            $table->id();
            // type: terms(이용약관), privacy(개인정보처리방침), marketing(마케팅 수신동의)
            $table->string('type')->unique();
            $table->string('title');
            $table->longText('content');
            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('version')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policies');
    }
};

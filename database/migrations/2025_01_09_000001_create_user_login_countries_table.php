<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_login_countries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('country_code', 2);
            $table->string('country_name', 100)->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamps();

            $table->unique(['user_id', 'country_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_login_countries');
    }
};

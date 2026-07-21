<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('policies', function (Blueprint $table) {
            // content_type / pending_content_type: editor, html_file (PageResource와 동일한 규약)
            $table->string('content_type')->default('editor')->after('content');
            $table->string('html_file_path')->nullable()->after('content_type');
            $table->string('pending_content_type')->default('editor')->after('pending_content');
            $table->string('pending_html_file_path')->nullable()->after('pending_content_type');
        });
    }

    public function down(): void
    {
        Schema::table('policies', function (Blueprint $table) {
            $table->dropColumn(['content_type', 'html_file_path', 'pending_content_type', 'pending_html_file_path']);
        });
    }
};

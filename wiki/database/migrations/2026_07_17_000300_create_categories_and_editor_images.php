<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('slug', 120)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('article_category', function (Blueprint $table) {
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['article_id', 'category_id']);
        });

        Schema::create('editor_images', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('web_id')->constrained()->cascadeOnDelete();
            $table->foreignId('article_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('path', 255)->unique();
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });

        if (DB::getDriverName() === 'mysql') {
            Schema::table('articles', function (Blueprint $table) {
                $table->fullText(['title', 'content'], 'articles_title_content_fulltext');
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            Schema::table('articles', function (Blueprint $table) {
                $table->dropFullText('articles_title_content_fulltext');
            });
        }

        Schema::dropIfExists('editor_images');
        Schema::dropIfExists('article_category');
        Schema::dropIfExists('categories');
    }
};

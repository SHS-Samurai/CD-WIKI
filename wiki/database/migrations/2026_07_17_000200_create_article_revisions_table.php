<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->unsignedInteger('current_revision')->default(0)->after('content');
            $table->string('change_note', 255)->nullable()->after('current_revision');
        });

        Schema::create('article_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            $table->string('title', 180);
            $table->longText('content');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author_name', 255)->nullable();
            $table->string('change_note', 255)->nullable();
            $table->timestamp('created_at');
            $table->unique(['article_id', 'revision_number']);
        });

        DB::table('articles')->orderBy('id')->each(function (object $article): void {
            DB::table('article_revisions')->insert([
                'article_id' => $article->id,
                'revision_number' => 1,
                'title' => $article->title,
                'content' => $article->content,
                'user_id' => $article->user_id,
                'created_at' => $article->updated_at ?? now(),
            ]);
            DB::table('articles')->where('id', $article->id)->update(['current_revision' => 1]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_revisions');

        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['current_revision', 'change_note']);
        });
    }
};

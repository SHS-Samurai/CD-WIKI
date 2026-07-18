<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->string('title_key', 191)->default('')->after('title');
            $table->index(['web_id', 'title_key']);
        });

        DB::table('articles')->orderBy('id')->chunkById(500, function ($articles): void {
            foreach ($articles as $article) {
                DB::table('articles')->where('id', $article->id)->update([
                    'title_key' => mb_strtolower(trim($article->title)),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropIndex(['web_id', 'title_key']);
            $table->dropColumn('title_key');
        });
    }
};

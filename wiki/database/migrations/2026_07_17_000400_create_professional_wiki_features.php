<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author_name', 255);
            $table->text('body');
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
            $table->index(['article_id', 'deleted_at', 'created_at']);
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->string('original_name', 255);
            $table->string('storage_name', 255);
            $table->string('path', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('current_revision')->default(1);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['article_id', 'storage_name']);
        });

        Schema::create('attachment_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attachment_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision_number');
            $table->string('path', 255)->unique();
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->string('sha256', 64);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at');
            $table->unique(['attachment_id', 'revision_number']);
        });

        Schema::create('theme_settings', function (Blueprint $table) {
            $table->id();
            $table->string('wiki_title', 120)->default('CD Wiki');
            $table->string('primary_color', 7)->default('#176b87');
            $table->string('background_color', 7)->default('#f6f7f9');
            $table->string('surface_color', 7)->default('#ffffff');
            $table->string('text_color', 7)->default('#1f2933');
            $table->string('muted_color', 7)->default('#617184');
            $table->string('font_family', 20)->default('system');
            $table->boolean('left_sidebar_enabled')->default(true);
            $table->boolean('right_sidebar_enabled')->default(true);
            $table->unsignedSmallInteger('page_max_width')->default(1280);
            $table->timestamps();
        });

        DB::table('theme_settings')->insert([
            'wiki_title' => config('app.name', 'CD Wiki'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action', 80)->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name', 255)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->unsignedBigInteger('web_id')->nullable()->index();
            $table->unsignedBigInteger('article_id')->nullable()->index();
            $table->string('target_type', 120)->nullable();
            $table->string('target_id', 120)->nullable();
            $table->unsignedInteger('old_revision')->nullable();
            $table->unsignedInteger('new_revision')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('theme_settings');
        Schema::dropIfExists('attachment_revisions');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('comments');
    }
};

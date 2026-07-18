<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('password');
        });

        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160)->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('group_user', function (Blueprint $table) {
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['group_id', 'user_id']);
        });

        Schema::create('webs', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->string('visibility', 20)->default('private');
            $table->boolean('is_admin_web')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('web_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('web_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type', 20);
            $table->string('subject_key', 191);
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('can_view')->default(false);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_edit')->default(false);
            $table->boolean('can_comment')->default(false);
            $table->boolean('can_upload')->default(false);
            $table->boolean('can_manage')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->timestamps();
            $table->unique(['web_id', 'subject_key']);
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('web_id')->constrained()->cascadeOnDelete();
            $table->string('title', 180);
            $table->string('slug', 120);
            $table->longText('content');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['web_id', 'slug']);
            $table->index('title');
        });

        DB::table('webs')->insert([
            'slug' => 'admin',
            'title' => 'Administration',
            'description' => 'Zentrale Verwaltung des Wikis',
            'visibility' => 'private',
            'is_admin_web' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
        Schema::dropIfExists('web_permissions');
        Schema::dropIfExists('webs');
        Schema::dropIfExists('group_user');
        Schema::dropIfExists('groups');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};

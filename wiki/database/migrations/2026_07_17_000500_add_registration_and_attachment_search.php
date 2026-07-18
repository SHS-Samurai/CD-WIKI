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
            $table->timestamp('approved_at')->nullable()->after('email_verified_at');
        });
        DB::table('users')->update(['approved_at' => now()]);

        Schema::table('attachments', function (Blueprint $table) {
            $table->longText('search_text')->nullable()->after('mime_type');
        });

        if (DB::getDriverName() === 'mysql') {
            Schema::table('attachments', function (Blueprint $table) {
                $table->fullText(['original_name', 'search_text'], 'attachments_search_fulltext');
            });
        }

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('registration_mode', 20)->default('closed');
            $table->timestamps();
        });
        DB::table('system_settings')->insert([
            'registration_mode' => 'closed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        if (DB::getDriverName() === 'mysql') {
            Schema::table('attachments', fn (Blueprint $table) => $table->dropFullText('attachments_search_fulltext'));
        }
        Schema::table('attachments', fn (Blueprint $table) => $table->dropColumn('search_text'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('approved_at'));
    }
};

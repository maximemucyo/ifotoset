<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            // Null = draft, which clients can't open. Defaults to now so galleries created by code
            // that predates drafts (including the old release during a deploy) stay shared.
            $table->timestamp('published_at')->nullable()->useCurrent()->after('expires_at');
            $table->foreignId('client_id')->nullable()->after('client_name')->constrained('clients')->nullOnDelete();
        });

        DB::table('galleries')->update(['published_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
            $table->dropColumn('published_at');
        });
    }
};

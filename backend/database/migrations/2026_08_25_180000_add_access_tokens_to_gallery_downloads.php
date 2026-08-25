<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('gallery_downloads', function (Blueprint $table) {
            $table->string('download_token_hash', 64)->nullable()->after('email');
            $table->timestamp('expired_at')->nullable()->after('completed_at');
            
            // Add indexes
            $table->index('download_token_hash');
            $table->index(['status', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('gallery_downloads', function (Blueprint $table) {
            $table->dropIndex(['status', 'completed_at']);
            $table->dropIndex(['download_token_hash']);
            $table->dropColumn(['download_token_hash', 'expired_at']);
        });
    }
};

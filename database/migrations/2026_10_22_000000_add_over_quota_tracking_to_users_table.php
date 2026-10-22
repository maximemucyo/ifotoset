<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Set when a Free-plan account is first found above its storage limit; drives the grace/restrict/archive/delete timeline
            $table->timestamp('over_quota_since')->nullable()->index();
            // Last over-quota email sent, so each notice goes out once
            $table->string('over_quota_notice', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['over_quota_since']);
            $table->dropColumn(['over_quota_since', 'over_quota_notice']);
        });
    }
};

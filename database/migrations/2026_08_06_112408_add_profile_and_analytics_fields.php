<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add profile and preference fields to users table
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->unique()->nullable()->after('email');
            $table->string('phone')->nullable()->after('role');
            $table->string('location')->nullable()->after('phone');
            $table->string('website')->nullable()->after('location');
            $table->text('bio')->nullable()->after('website');
            $table->string('avatar_path', 512)->nullable()->after('bio');
            $table->json('notification_preferences')->nullable()->after('avatar_path');
        });

        // Add attribution / visitor session fields to activity_logs table
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('visitor_session_id', 64)->nullable()->index()->after('gallery_id');
            $table->string('source', 100)->nullable()->after('visitor_session_id');
            $table->string('referrer', 512)->nullable()->after('source');
            $table->string('campaign', 255)->nullable()->after('referrer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['visitor_session_id']);
            $table->dropColumn(['visitor_session_id', 'source', 'referrer', 'campaign']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn([
                'username',
                'phone',
                'location',
                'website',
                'bio',
                'avatar_path',
                'notification_preferences'
            ]);
        });
    }
};


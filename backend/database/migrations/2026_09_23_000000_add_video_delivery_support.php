<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Photos table enhancements for video media
        Schema::table('photos', function (Blueprint $table) {
            $table->string('media_type', 20)->default('photo')->after('disk_id')->index();
            $table->unsignedInteger('duration_seconds')->nullable()->after('height');
            $table->string('original_path', 512)->nullable()->after('stored_filename');
            $table->string('delivery_path', 512)->nullable()->after('original_path');
            $table->string('poster_path', 512)->nullable()->after('delivery_path');
            $table->json('video_metadata')->nullable()->after('poster_path');
            $table->string('processing_error', 255)->nullable()->after('status');
            $table->timestamp('processed_at')->nullable()->after('processing_error');
        });

        // 2. Upload sessions reservation tracking
        Schema::table('upload_sessions', function (Blueprint $table) {
            $table->unsignedInteger('reserved_duration_seconds')->default(0)->after('expected_size');
        });

        // 3. Users video quota counters
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('video_seconds_used')->default(0)->after('storage_reserved_bytes');
            $table->unsignedBigInteger('video_seconds_reserved')->default(0)->after('video_seconds_used');
        });

        // 4. Plans table video limit and boundaries
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedBigInteger('video_limit_seconds')->default(0)->after('video_limit');
            $table->unsignedBigInteger('max_video_size_bytes')->default(0)->after('video_limit_seconds');
            $table->unsignedInteger('max_single_video_duration_seconds')->default(0)->after('max_video_size_bytes');
        });

        // 5. Seed / update plans with deterministic limits
        DB::table('plans')->where('slug', 'free')->update([
            'video_limit' => 0,
            'video_limit_seconds' => 0,
            'max_video_size_bytes' => 0,
            'max_single_video_duration_seconds' => 0,
        ]);

        DB::table('plans')->where('slug', 'basic')->update([
            'video_limit' => 1800,
            'video_limit_seconds' => 1800, // 30 minutes
            'max_video_size_bytes' => 524288000, // 500 MB
            'max_single_video_duration_seconds' => 900, // 15 minutes max single video
        ]);

        DB::table('plans')->where('slug', 'pro')->orWhere('slug', 'professional')->update([
            'video_limit' => 18000,
            'video_limit_seconds' => 18000, // 5 hours
            'max_video_size_bytes' => 2147483648, // 2 GB
            'max_single_video_duration_seconds' => 3600, // 1 hour max single video
        ]);

        DB::table('plans')->where('slug', 'business')->update([
            'video_limit' => 54000,
            'video_limit_seconds' => 54000, // 15 hours
            'max_video_size_bytes' => 4294967296, // 4 GB
            'max_single_video_duration_seconds' => 7200, // 2 hours max single video
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn([
                'video_limit_seconds',
                'max_video_size_bytes',
                'max_single_video_duration_seconds',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'video_seconds_used',
                'video_seconds_reserved',
            ]);
        });

        Schema::table('upload_sessions', function (Blueprint $table) {
            $table->dropColumn(['reserved_duration_seconds']);
        });

        Schema::table('photos', function (Blueprint $table) {
            $table->dropIndex(['media_type']);
            $table->dropColumn([
                'media_type',
                'duration_seconds',
                'original_path',
                'delivery_path',
                'poster_path',
                'video_metadata',
                'processing_error',
                'processed_at',
            ]);
        });
    }
};

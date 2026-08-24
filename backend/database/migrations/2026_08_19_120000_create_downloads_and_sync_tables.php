<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Add permissions to galleries table
        Schema::table('galleries', function (Blueprint $table) {
            $table->boolean('allow_photo_downloads')->default(true)->after('visibility');
            $table->boolean('allow_gallery_downloads')->default(true)->after('allow_photo_downloads');
            $table->boolean('allow_google_photos')->default(true)->after('allow_gallery_downloads');
        });

        // 2. Persistent versioned ZIP downloads table
        Schema::create('gallery_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gallery_id')->constrained('galleries')->onDelete('cascade');
            $table->string('status', 50)->default('pending'); // pending, processing, ready, failed
            $table->timestamp('generated_at')->nullable();
            $table->string('storage_path')->nullable(); // e.g. galleries/{uuid}/downloads/photos-{snapshot_hash}.zip
            $table->bigInteger('size')->nullable();
            $table->string('photo_snapshot_hash', 32)->nullable();
            $table->timestamps();

            $table->index(['gallery_id', 'photo_snapshot_hash']);
        });

        // 3. Short-lived OAuth authorizations mapping table (Single-use)
        Schema::create('google_photo_authorizations', function (Blueprint $table) {
            $table->id();
            $table->binary('uuid', 16)->unique();
            $table->foreignId('gallery_id')->constrained('galleries')->onDelete('cascade');
            $table->string('state', 64)->unique();
            $table->json('photo_uuids')->nullable(); // Specific photos selected
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        // 4. Persistent Google Photos synchronization jobs tracker
        Schema::create('google_photo_syncs', function (Blueprint $table) {
            $table->id();
            $table->binary('uuid', 16)->unique();
            $table->foreignId('gallery_id')->constrained('galleries')->onDelete('cascade');
            $table->string('status', 50)->default('pending'); // pending, processing, completed, completed_with_errors, failed
            $table->integer('total_photos')->default(0);
            $table->integer('processed_photos')->default(0);
            $table->integer('failed_photos')->default(0);
            $table->string('album_id')->nullable();
            $table->text('album_url')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // 5. Encrypted credentials table (stores tokens securely out of queue jobs)
        Schema::create('google_photo_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sync_id')->constrained('google_photo_syncs')->onDelete('cascade');
            $table->text('access_token'); // Encrypted
            $table->text('refresh_token')->nullable(); // Encrypted
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_photo_credentials');
        Schema::dropIfExists('google_photo_syncs');
        Schema::dropIfExists('google_photo_authorizations');
        Schema::dropIfExists('gallery_downloads');
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropColumn(['allow_photo_downloads', 'allow_gallery_downloads', 'allow_google_photos']);
        });
    }
};

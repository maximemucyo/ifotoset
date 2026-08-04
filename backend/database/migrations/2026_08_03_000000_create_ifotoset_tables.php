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
        // 1. Storage Disks Abstraction
        Schema::create('storage_disks', function (Blueprint $table) {
            $table->id();
            $table->binary('uuid', 16)->unique();
            $table->string('driver', 50);
            $table->string('bucket', 255);
            $table->string('region', 50);
            $table->string('cdn_domain', 255);
            $table->timestamps();
        });

        // 2. Plans & Features
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->binary('uuid', 16)->unique();
            $table->string('slug', 50)->unique();
            $table->string('name', 100);
            $table->decimal('monthly_price', 10, 2)->default(0.00);
            $table->decimal('annual_price', 10, 2)->default(0.00);
            $table->string('currency', 10)->default('RWF');
            $table->bigInteger('storage_limit');
            $table->bigInteger('video_limit');
            $table->integer('gallery_limit');
            $table->integer('team_limit');
            $table->timestamps();
        });

        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->timestamps();
        });

        Schema::create('plan_features', function (Blueprint $table) {
            $table->foreignId('plan_id')->constrained('plans')->onDelete('cascade');
            $table->foreignId('feature_id')->constrained('features')->onDelete('cascade');
            $table->primary(['plan_id', 'feature_id']);
        });

        // 3. Users & Subscriptions
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->binary('uuid', 16)->unique();
            $table->foreignId('plan_id')->constrained('plans')->onDelete('restrict');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role', 50)->default('photographer');
            $table->bigInteger('storage_used_bytes')->default(0);
            $table->timestamp('email_verified_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->binary('uuid', 16)->unique();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('plan_id')->constrained('plans')->onDelete('restrict');
            $table->string('provider', 50);
            $table->string('provider_subscription_id', 255)->nullable();
            $table->string('status', 50); // SubscriptionStatus
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('cancels_at')->nullable();
            $table->timestamps();
        });

        // 4. Galleries, Albums & Materialized Stats
        Schema::create('galleries', function (Blueprint $table) {
            $table->id();
            $table->binary('uuid', 16)->unique();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->bigInteger('cover_photo_id')->unsigned()->nullable(); // Handled later
            $table->string('title');
            $table->string('slug');
            $table->string('client_name')->nullable();
            $table->date('event_date')->nullable();
            $table->string('visibility', 50)->default('public'); // Visibility Enum
            $table->string('password_hash', 255)->nullable();
            $table->string('password_hint', 255)->nullable();
            $table->string('invite_token', 64)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->integer('version')->default(1); // Optimistic locking
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'slug'], 'uq_user_slug');
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('gallery_stats', function (Blueprint $table) {
            $table->foreignId('gallery_id')->primary()->constrained('galleries')->onDelete('cascade');
            $table->integer('photo_count')->default(0);
            $table->integer('video_count')->default(0);
            $table->integer('downloads_count')->default(0);
            $table->integer('favorites_count')->default(0);
            $table->bigInteger('total_bytes')->default(0);
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('albums', function (Blueprint $table) {
            $table->id();
            $table->binary('uuid', 16)->unique();
            $table->foreignId('gallery_id')->constrained('galleries')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->bigInteger('cover_photo_id')->unsigned()->nullable(); // Handled later
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        // 5. Photos & Metadata (BIGINT)
        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->binary('uuid', 16)->unique();
            $table->foreignId('gallery_id')->constrained('galleries')->onDelete('cascade');
            $table->foreignId('album_id')->nullable()->constrained('albums')->onDelete('set null');
            $table->foreignId('disk_id')->constrained('storage_disks')->onDelete('restrict');
            $table->string('path', 512);
            $table->string('filename', 255);
            $table->string('mime_type', 100);
            $table->bigInteger('size');
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->string('checksum', 64);
            $table->string('blurhash', 255)->nullable();
            $table->timestamp('taken_at')->nullable();
            $table->integer('sort_order')->default(0);
            $table->string('status', 50)->default('pending'); // PhotoStatus Enum
            $table->timestamps();
            $table->softDeletes();

            $table->index(['gallery_id', 'sort_order']);
            $table->index('status');
            $table->index(['gallery_id', 'created_at']);
        });

        Schema::create('photo_metadata', function (Blueprint $table) {
            $table->foreignId('photo_id')->primary()->constrained('photos')->onDelete('cascade');
            $table->string('camera', 255)->nullable();
            $table->string('lens', 255)->nullable();
            $table->integer('iso')->nullable();
            $table->string('shutter_speed', 50)->nullable();
            $table->string('aperture', 50)->nullable();
            $table->string('focal_length', 50)->nullable();
            $table->string('flash', 50)->nullable();
            $table->integer('orientation')->default(1);
            $table->decimal('gps_latitude', 10, 8)->nullable();
            $table->decimal('gps_longitude', 11, 8)->nullable();
        });

        // 6. Search Tags
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
        });

        Schema::create('photo_tags', function (Blueprint $table) {
            $table->foreignId('photo_id')->constrained('photos')->onDelete('cascade');
            $table->foreignId('tag_id')->constrained('tags')->onDelete('cascade');
            $table->primary(['photo_id', 'tag_id']);
        });

        // 7. Activity Logs
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('gallery_id')->nullable()->constrained('galleries')->onDelete('set null');
            $table->string('event', 100);
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'event']);
        });

        // 8. Upload Sessions & Media Jobs
        Schema::create('upload_sessions', function (Blueprint $table) {
            $table->id();
            $table->binary('uuid', 16)->unique();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('gallery_id')->constrained('galleries')->onDelete('cascade');
            $table->string('idempotency_key', 128);
            $table->string('object_key', 512);
            $table->bigInteger('expected_size');
            $table->string('expected_sha256', 64);
            $table->string('status', 50)->default('requested'); // UploadStatus
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['user_id', 'idempotency_key'], 'uq_user_idempotency');
            $table->index(['status', 'expires_at']);
        });

        Schema::create('media_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('photo_id')->constrained('photos')->onDelete('cascade');
            $table->string('job_name', 100);
            $table->string('status', 50)->default('queued'); // MediaJobStatus
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->integer('duration_ms')->nullable();
        });

        // 9. Payments, Webhook Payload & Subscription Events
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->binary('uuid', 16)->unique();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('plan_id')->constrained('plans')->onDelete('restrict');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 10);
            $table->string('phone_number', 30);
            $table->string('provider', 50);
            $table->string('idempotency_key', 128);
            $table->string('pawapay_deposit_id', 36)->unique();
            $table->string('status', 50)->default('created'); // PaymentStatus Enum
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'idempotency_key'], 'uq_payment_idempotency');
        });

        Schema::create('payment_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50);
            $table->string('event_id', 255)->unique();
            $table->json('headers');
            $table->json('payload');
            $table->timestamp('received_at')->useCurrent();
        });

        Schema::create('subscription_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->onDelete('cascade');
            $table->string('event_type', 50); // SubscriptionEvent
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_events');
        Schema::dropIfExists('payment_webhooks');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('media_jobs');
        Schema::dropIfExists('upload_sessions');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('photo_tags');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('photo_metadata');
        Schema::dropIfExists('photos');
        Schema::dropIfExists('albums');
        Schema::dropIfExists('gallery_stats');
        Schema::dropIfExists('galleries');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('features');
        Schema::dropIfExists('plans');
        Schema::dropIfExists('storage_disks');
    }
};
?>

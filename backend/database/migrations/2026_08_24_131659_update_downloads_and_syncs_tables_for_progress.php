<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('gallery_downloads', function (Blueprint $table) {
            $table->unsignedInteger('total_photos')->default(0)->after('photo_snapshot_hash');
            $table->unsignedInteger('processed_photos')->default(0)->after('total_photos');
            $table->unsignedInteger('failed_photos')->default(0)->after('processed_photos');
            $table->string('email')->nullable()->after('failed_photos');
            $table->boolean('notify_when_ready')->default(false)->after('email');
            $table->text('error')->nullable()->after('notify_when_ready');
            $table->timestamp('started_at')->nullable()->after('error');
            $table->timestamp('completed_at')->nullable()->after('started_at');
            $table->timestamp('notification_sent_at')->nullable()->after('completed_at');
            
            $table->index(['status', 'created_at']);
        });

        Schema::table('google_photo_authorizations', function (Blueprint $table) {
            $table->string('email')->nullable()->after('photo_uuids');
            $table->boolean('notify_when_ready')->default(false)->after('email');
        });

        Schema::table('google_photo_syncs', function (Blueprint $table) {
            $table->string('email')->nullable()->after('album_url');
            $table->boolean('notify_when_ready')->default(false)->after('email');
            $table->timestamp('notification_sent_at')->nullable()->after('completed_at');

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('google_photo_syncs', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropColumn(['email', 'notify_when_ready', 'notification_sent_at']);
        });

        Schema::table('google_photo_authorizations', function (Blueprint $table) {
            $table->dropColumn(['email', 'notify_when_ready']);
        });

        Schema::table('gallery_downloads', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropColumn([
                'total_photos',
                'processed_photos',
                'failed_photos',
                'email',
                'notify_when_ready',
                'error',
                'started_at',
                'completed_at',
                'notification_sent_at'
            ]);
        });
    }
};

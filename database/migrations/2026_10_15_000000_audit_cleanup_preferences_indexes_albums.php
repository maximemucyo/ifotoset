<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Rename legacy notification preference keys to App\Enums\NotificationPreference keys
        DB::table('users')->whereNotNull('notification_preferences')->orderBy('id')
            ->chunkById(500, function ($users) {
                foreach ($users as $user) {
                    $prefs = json_decode($user->notification_preferences, true);
                    if (!is_array($prefs)) {
                        continue;
                    }

                    $changed = false;
                    if (array_key_exists('booking_created', $prefs)) {
                        $prefs['new_bookings'] ??= (bool) $prefs['booking_created'];
                        unset($prefs['booking_created']);
                        $changed = true;
                    }
                    if (array_key_exists('photo_downloaded', $prefs) || array_key_exists('photo_favorited', $prefs)) {
                        $prefs['gallery_activity'] ??= !empty($prefs['photo_downloaded']) || !empty($prefs['photo_favorited']);
                        unset($prefs['photo_downloaded'], $prefs['photo_favorited']);
                        $changed = true;
                    }

                    if ($changed) {
                        DB::table('users')->where('id', $user->id)
                            ->update(['notification_preferences' => json_encode($prefs)]);
                    }
                }
            });

        // 2. Indexes for the scheduled expiry and reconcile scans
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index(['status', 'ends_at'], 'subscriptions_status_ends_at_index');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'payments_status_created_at_index');
        });

        // 3. Drop the orphan albums table (no model uses it). Skip if it ever got data.
        if (Schema::hasTable('albums') && DB::table('albums')->count() === 0) {
            if (Schema::hasColumn('photos', 'album_id')) {
                Schema::table('photos', function (Blueprint $table) {
                    $table->dropForeign(['album_id']);
                    $table->dropColumn('album_id');
                });
            }
            Schema::drop('albums');
        }
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex('subscriptions_status_ends_at_index');
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_status_created_at_index');
        });

        if (!Schema::hasTable('albums')) {
            Schema::create('albums', function (Blueprint $table) {
                $table->id();
                $table->foreignId('gallery_id')->constrained('galleries')->onDelete('cascade');
                $table->string('title');
                $table->timestamps();
                $table->softDeletes();
            });
            Schema::table('photos', function (Blueprint $table) {
                $table->foreignId('album_id')->nullable()->after('gallery_id')->constrained('albums')->onDelete('set null');
            });
        }
    }
};

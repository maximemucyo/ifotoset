<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Availability default settings
        Schema::create('availability_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->unsignedTinyInteger('day_of_week'); // 0 (Sunday) to 6 (Saturday)
            $table->time('start_time')->default('09:00:00');
            $table->time('end_time')->default('17:00:00');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'day_of_week']);
        });

        // 2. Availability date exceptions
        Schema::create('availability_exceptions', function (Blueprint $table) {
            $table->id();
            $table->binary('uuid', 16)->unique();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->date('date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'date']);
        });

        // 3. Blocked slots / blackouts
        Schema::create('blocked_slots', function (Blueprint $table) {
            $table->id();
            $table->binary('uuid', 16)->unique();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason')->nullable();
            $table->string('source', 30)->default('manual');
            $table->timestamps();

            $table->index(['user_id', 'starts_at', 'ends_at']);
        });

        // 4. Config columns on packages
        Schema::table('packages', function (Blueprint $table) {
            $table->unsignedInteger('buffer_before_minutes')->default(0)->after('duration_minutes');
            $table->unsignedInteger('buffer_after_minutes')->default(0)->after('buffer_before_minutes');
        });

        // 5. Config columns on users
        Schema::table('users', function (Blueprint $table) {
            $table->string('timezone', 100)->default('Africa/Kigali')->after('location');
            $table->unsignedInteger('slot_interval_minutes')->default(30)->after('timezone');
        });

        // 6. Overlap index on bookings
        Schema::table('bookings', function (Blueprint $table) {
            $table->index(['user_id', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'starts_at', 'ends_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['timezone', 'slot_interval_minutes']);
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['buffer_before_minutes', 'buffer_after_minutes']);
        });

        Schema::dropIfExists('blocked_slots');
        Schema::dropIfExists('availability_exceptions');
        Schema::dropIfExists('availability_settings');
    }
};

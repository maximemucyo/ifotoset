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
        Schema::table('photos', function (Blueprint $table) {
            $table->string('original_filename', 255)->nullable()->after('filename');
            $table->string('stored_filename', 255)->nullable()->after('original_filename');
        });

        Schema::table('upload_sessions', function (Blueprint $table) {
            $table->string('original_filename', 255)->nullable()->after('object_key');
        });

        Schema::table('media_jobs', function (Blueprint $table) {
            $table->string('job_uuid', 64)->nullable()->after('photo_id');
            $table->string('job_type', 100)->nullable()->after('job_uuid');
            $table->string('queue', 50)->nullable()->after('job_type');
            $table->integer('attempts')->default(0)->after('status');
            $table->integer('max_attempts')->nullable()->after('attempts');
            $table->string('progress', 50)->nullable()->after('max_attempts');
            $table->timestamp('failed_at')->nullable()->after('completed_at');
            $table->text('error_message')->nullable()->after('error');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media_jobs', function (Blueprint $table) {
            $table->dropColumn([
                'job_uuid', 'job_type', 'queue', 'attempts', 'max_attempts', 'progress', 'failed_at', 'error_message', 'created_at', 'updated_at'
            ]);
        });

        Schema::table('upload_sessions', function (Blueprint $table) {
            $table->dropColumn(['original_filename']);
        });

        Schema::table('photos', function (Blueprint $table) {
            $table->dropColumn(['original_filename', 'stored_filename']);
        });
    }
};

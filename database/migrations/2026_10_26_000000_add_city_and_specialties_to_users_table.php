<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Slugs from config/directory.php, for the future photographer directory
            $table->string('city', 64)->nullable()->index()->after('location');
            $table->json('specialties')->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['city']);
            $table->dropColumn(['city', 'specialties']);
        });
    }
};

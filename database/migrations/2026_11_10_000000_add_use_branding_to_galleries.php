<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The studio's logo, colour and title font on the client gallery. Off, so no gallery changes until the photographer turns it on.
        Schema::table('galleries', function (Blueprint $table) {
            $table->boolean('use_branding')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropColumn('use_branding');
        });
    }
};

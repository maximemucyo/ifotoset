<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // How the client gallery looks. The defaults are the look every gallery had before, so none changes.
        Schema::table('galleries', function (Blueprint $table) {
            $table->string('template', 20)->default('full');
            $table->string('grid_style', 20)->default('masonry');
            $table->string('color_mode', 10)->default('light');
        });
    }

    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropColumn(['template', 'grid_style', 'color_mode']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Seed non-sensitive SMTP config options
        DB::table('system_settings')->insert([
            ['key' => 'smtp_host', 'value' => 'smtp.gmail.com', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'smtp_port', 'value' => '587', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'smtp_username', 'value' => 'ifotoset1@gmail.com', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'smtp_encryption', 'value' => 'tls', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'smtp_from_address', 'value' => 'ifotoset1@gmail.com', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'smtp_from_name', 'value' => 'ifotoset', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};

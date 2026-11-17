<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            // Photo icons on the lobby: [{src, thumb, hall: hall slug}] (the caption is the hall's name)
            $table->json('lobby_photos')->nullable()->after('films');
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropColumn('lobby_photos');
        });
    }
};

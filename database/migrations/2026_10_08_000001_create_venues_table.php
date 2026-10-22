<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('custom_domain')->nullable()->unique();
            $table->text('description')->nullable();
            $table->json('translations')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('email')->nullable();
            $table->string('timezone')->default('Asia/Jakarta');
            $table->string('currency', 3)->default('IDR');
            $table->string('default_locale', 2)->default('id');
            $table->time('event_start_time')->default('08:00');
            $table->time('event_end_time')->default('22:00');
            $table->unsignedTinyInteger('weekday_discount_percent')->default(0);
            $table->unsignedTinyInteger('deposit_percent')->default(30);
            $table->string('logo_path')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('public_status')->default('draft');
            $table->softDeletes();
            $table->timestamps();
        });
    }
};

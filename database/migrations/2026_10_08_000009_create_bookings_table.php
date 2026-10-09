<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->nullable()->unique();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hall_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('client_name');
            $table->string('client_email')->nullable();
            $table->string('client_phone')->nullable();
            $table->string('contact_type', 20)->nullable(); // whatsapp | phone | email
            $table->string('locale', 2)->nullable(); // language the client wrote in
            $table->date('event_date');
            $table->string('event_type', 20)->default('akad_reception'); // akad | reception | akad_reception | engagement
            $table->unsignedSmallInteger('guest_count');
            $table->unsignedTinyInteger('extra_hours')->default(0);
            $table->decimal('total_price', 12, 2);
            $table->decimal('deposit_amount', 12, 2)->default(0);
            $table->string('status')->default('pending'); // pending | confirmed | cancelled
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }
};

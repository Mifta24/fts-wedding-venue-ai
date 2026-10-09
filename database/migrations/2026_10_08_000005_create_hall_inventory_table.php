<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hall_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hall_id')->constrained()->cascadeOnDelete();
            $table->date('event_date');
            $table->unsignedSmallInteger('total_slots')->default(1);
            $table->unsignedSmallInteger('booked_slots')->default(0);
            $table->decimal('price', 12, 2);
            $table->timestamps();

            $table->unique(['hall_id', 'event_date']);
        });
    }
};

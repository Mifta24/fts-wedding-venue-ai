<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_token')->unique();
            $table->string('client_name')->nullable();
            $table->string('client_email')->nullable();
            $table->string('locale', 2)->default('id');
            $table->string('status')->default('active'); // active | handed_over | closed
            $table->string('current_scene', 20)->default('reception');
            $table->foreignId('selected_hall_id')->nullable()->constrained('halls')->nullOnDelete();
            $table->foreignId('selected_service_id')->nullable()->constrained('venue_knowledge_items')->nullOnDelete();
            $table->json('reservation_state')->nullable();
            $table->text('handover_summary')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });
    }
};

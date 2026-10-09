<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('halls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->json('translations')->nullable();
            $table->unsignedSmallInteger('size_sqm')->nullable();
            $table->string('setting', 20)->default('indoor'); // indoor | outdoor | semi_outdoor
            $table->unsignedSmallInteger('min_guests')->default(50);
            $table->unsignedSmallInteger('max_guests')->default(300);
            $table->json('seating_styles')->nullable();
            $table->string('view_type')->nullable();
            $table->boolean('catering_included')->default(false);
            $table->boolean('extra_hour_available')->default(false);
            $table->decimal('extra_hour_price', 12, 2)->nullable();
            $table->decimal('base_price', 12, 2);
            $table->json('amenities')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['venue_id', 'slug']);
        });
    }
};

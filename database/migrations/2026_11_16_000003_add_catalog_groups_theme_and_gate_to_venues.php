<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            // 'hall' = a classic wedding hall; 'plan' = packages sold per location (e.g. photo weddings)
            $table->string('catalog_mode', 20)->default('hall')->after('video_url');
            // Own visual identity, a key into the theme-* CSS classes. Null = the default look.
            $table->string('theme', 40)->nullable()->after('catalog_mode');
            // Ordered groups the halls are sorted into, e.g. areas: [{key, names: {locale: label}}]
            $table->json('hall_groups')->nullable()->after('theme');
            // Shared copy for every plan of the venue (inclusions, extras, early-bird...), per locale
            $table->json('plan_info')->nullable()->after('hall_groups');
            // When set, visitors must enter this code before any public page opens
            $table->string('access_code_hash')->nullable()->after('plan_info');
        });

        Schema::table('halls', function (Blueprint $table) {
            $table->string('group_key', 40)->nullable()->after('slug');
            // Price options of a plan: [{key, price}], priced in the venue currency
            $table->json('price_options')->nullable()->after('base_price');
        });
    }

    public function down(): void
    {
        Schema::table('halls', fn (Blueprint $table) => $table->dropColumn(['group_key', 'price_options']));
        Schema::table('venues', fn (Blueprint $table) => $table->dropColumn(['catalog_mode', 'theme', 'hall_groups', 'plan_info', 'access_code_hash']));
    }
};

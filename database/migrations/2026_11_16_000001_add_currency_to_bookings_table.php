<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A booking is billed in the currency of the language the client used.
     * `total_price` and `deposit_amount` are in that currency; old rows keep
     * a null currency and read as the venue's own.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('currency', 3)->nullable()->after('deposit_amount');
            $table->decimal('exchange_rate', 18, 10)->nullable()->after('currency'); // venue currency -> booking currency
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['currency', 'exchange_rate']);
        });
    }
};

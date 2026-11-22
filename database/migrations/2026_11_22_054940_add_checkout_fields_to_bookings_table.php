<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Nullable so bookings created before accounts existed stay valid.
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('payment_method', 20)->default('cash_on_service')->after('status');
            // Price snapshot: later changes to services.price must not rewrite history.
            $table->decimal('base_price', 10, 2)->default(0)->after('payment_method');
            $table->decimal('service_fee', 10, 2)->default(0)->after('base_price');
            $table->decimal('total_amount', 10, 2)->default(0)->after('service_fee');

            $table->index(['user_id', 'scheduled_at']);
        });

        DB::table('bookings')->update([
            'base_price' => DB::raw('(select price from services where services.id = bookings.service_id)'),
            'total_amount' => DB::raw('(select price from services where services.id = bookings.service_id)'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'scheduled_at']);
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['payment_method', 'base_price', 'service_fee', 'total_amount']);
        });
    }
};

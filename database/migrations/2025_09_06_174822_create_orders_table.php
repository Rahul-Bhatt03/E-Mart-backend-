<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
      Schema::table('orders', function (Blueprint $table) {
            $table->string('stripe_payment_intent_id')->nullable()->after('shipping_address');
            $table->string('payment_status')->default('pending')->after('stripe_payment_intent_id');
            // Options: pending, processing, succeeded, failed, canceled
            $table->decimal('tax_amount', 10, 2)->default(0)->after('total_amount');
            $table->decimal('shipping_amount', 10, 2)->default(0)->after('tax_amount');
            $table->json('payment_metadata')->nullable()->after('payment_status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
       Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_payment_intent_id', 
                'payment_status', 
                'tax_amount', 
                'shipping_amount',
                'payment_metadata'
            ]);
        });
    }
};

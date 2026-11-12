<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStripeSessionIdToPurchasesTable extends Migration
{
    /**
     * カード払いの購入がどの Stripe Checkout セッションで確定したかを記録する
     */
    public function up()
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->string('stripe_session_id')->nullable()->unique()->after('payment_method');
        });
    }

    public function down()
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropUnique(['stripe_session_id']);
            $table->dropColumn('stripe_session_id');
        });
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->string('payment_method')
                ->default('mobile_money_API')
                ->comment('Supported types: mobile_money_API (default ), bank_transfer, cash_payment')
                ->index();
                
            $table->string('reference_number')->nullable();
        });
    }
        
    

    

      public function down(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'reference_number']);
        });
    }
};
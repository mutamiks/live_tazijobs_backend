<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('amount');
            $table->string('transaction_reference')->nullable()->index()->after('status');
            $table->text('status_message')->nullable()->after('transaction_reference');
            $table->unsignedSmallInteger('processing_attempts')->default(0)->after('status_message');
            $table->timestamp('last_checked_at')->nullable()->after('processing_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'transaction_reference',
                'status_message',
                'processing_attempts',
                'last_checked_at',
            ]);
        });
    }
};

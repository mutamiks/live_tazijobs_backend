<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('phone', 30);
            $table->text('description')->nullable();
            $table->string('transaction_reference')->nullable()->index();
            $table->string('status')->default('pending')->index();
            $table->text('status_message')->nullable();
            $table->boolean('distributed')->default(false);
            $table->timestamps();
        });

        Schema::create('sms_topups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sms_payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('added_by')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('sms_credits');
            $table->decimal('rate', 10, 2);
            $table->decimal('amount', 12, 2);
            $table->string('provider_status')->nullable();
            $table->text('provider_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_topups');
        Schema::dropIfExists('sms_payments');
    }
};

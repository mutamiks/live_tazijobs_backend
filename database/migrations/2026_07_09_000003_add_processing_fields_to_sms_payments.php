<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_payments', function (Blueprint $table) {
            $table->unsignedSmallInteger('processing_attempts')->default(0)->after('distributed');
            $table->timestamp('last_checked_at')->nullable()->after('processing_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('sms_payments', function (Blueprint $table) {
            $table->dropColumn(['processing_attempts', 'last_checked_at']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->foreignId('job_id')->nullable()->after('job_seeker_subscription_id')->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->after('job_id')->constrained('users')->nullOnDelete();
            $table->string('invoice_number')->nullable()->unique()->after('created_by');
            $table->text('description')->nullable()->after('phone');
            $table->text('admin_notes')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('job_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropUnique(['invoice_number']);
            $table->dropColumn(['invoice_number', 'description', 'admin_notes']);
        });
    }
};

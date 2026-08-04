<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('worker_orders', function (Blueprint $table) {
            $table->dropForeign(['employer_id']);
            $table->foreignId('employer_id')->nullable()->change();
            $table->foreign('employer_id')->references('id')->on('users')->nullOnDelete();
            $table->string('contact_name')->nullable()->after('job_seeker_profile_id');
            $table->string('business_name')->nullable()->after('contact_name');
            $table->string('contact_phone')->nullable()->after('business_name');
        });
    }

    public function down(): void
    {
        Schema::table('worker_orders', function (Blueprint $table) {
            $table->dropColumn(['contact_name', 'business_name', 'contact_phone']);
            $table->dropForeign(['employer_id']);
            $table->foreignId('employer_id')->nullable(false)->change();
            $table->foreign('employer_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};

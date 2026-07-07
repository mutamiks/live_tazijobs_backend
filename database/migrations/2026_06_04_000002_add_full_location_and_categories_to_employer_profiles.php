<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employer_profiles', function (Blueprint $table) {
            $table->string('county')->nullable()->after('district');
            $table->string('parish')->nullable()->after('subcounty');
            $table->string('village')->nullable()->after('parish');
            $table->json('preferred_job_categories')->nullable()->after('preferred_worker_type');
        });
    }

    public function down(): void
    {
        Schema::table('employer_profiles', function (Blueprint $table) {
            $table->dropColumn(['county', 'parish', 'village', 'preferred_job_categories']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('religions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('uganda_locations', function (Blueprint $table) {
            $table->id();
            $table->string('district')->index();
            $table->string('county')->index();
            $table->string('subcounty')->index();
            $table->string('parish')->index();
            $table->string('village')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['district', 'county', 'subcounty', 'parish', 'village'], 'uganda_locations_unique_path');
        });

        Schema::table('job_seeker_profiles', function (Blueprint $table) {
            $table->string('county')->nullable()->after('district');
            $table->string('parish')->nullable()->after('subcounty');
            $table->string('village')->nullable()->after('parish');
            $table->string('id_document_front_file')->nullable()->after('id_document_file');
            $table->string('id_document_back_file')->nullable()->after('id_document_front_file');
        });

        Schema::table('jobs', function (Blueprint $table) {
            $table->foreignId('job_category_id')->nullable()->after('employer_id')->constrained()->nullOnDelete();
            $table->string('district')->nullable()->after('location');
            $table->string('county')->nullable()->after('district');
            $table->string('subcounty')->nullable()->after('county');
            $table->string('parish')->nullable()->after('subcounty');
            $table->string('village')->nullable()->after('parish');
        });
    }

    public function down(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('job_category_id');
            $table->dropColumn(['district', 'county', 'subcounty', 'parish', 'village']);
        });

        Schema::table('job_seeker_profiles', function (Blueprint $table) {
            $table->dropColumn(['county', 'parish', 'village', 'id_document_front_file', 'id_document_back_file']);
        });

        Schema::dropIfExists('uganda_locations');
        Schema::dropIfExists('religions');
        Schema::dropIfExists('languages');
        Schema::dropIfExists('job_categories');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_seeker_profiles', function (Blueprint $table) {
            $table->string('district')->nullable()->after('location');
            $table->string('subcounty')->nullable()->after('district');
            $table->json('languages')->nullable()->after('subcounty');
            $table->string('religion')->nullable()->after('languages');
            $table->text('work_experience')->nullable()->after('bio');
            $table->json('preferred_job_categories')->nullable()->after('work_experience');
            $table->string('lc1_letter_file')->nullable()->after('cv_file');
            $table->string('id_document_file')->nullable()->after('lc1_letter_file');
            $table->boolean('terms_accepted')->default(false)->after('profile_photo');
            $table->boolean('is_available')->default(true)->after('terms_accepted');
        });

        Schema::table('employer_profiles', function (Blueprint $table) {
            $table->string('district')->nullable()->after('company_location');
            $table->string('subcounty')->nullable()->after('district');
            $table->string('preferred_worker_type')->nullable()->after('company_description');
            $table->string('business_document_file')->nullable()->after('company_logo');
        });

        Schema::create('worker_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('job_seeker_profile_id')->constrained()->cascadeOnDelete();
            $table->decimal('salary_offered', 12, 2);
            $table->string('job_location');
            $table->text('working_terms');
            $table->text('allowances')->nullable();
            $table->text('job_description');
            $table->date('start_date');
            $table->string('status')->default('pending')->index();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_orders');

        Schema::table('employer_profiles', function (Blueprint $table) {
            $table->dropColumn(['district', 'subcounty', 'preferred_worker_type', 'business_document_file']);
        });

        Schema::table('job_seeker_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'district',
                'subcounty',
                'languages',
                'religion',
                'work_experience',
                'preferred_job_categories',
                'lc1_letter_file',
                'id_document_file',
                'terms_accepted',
                'is_available',
            ]);
        });
    }
};

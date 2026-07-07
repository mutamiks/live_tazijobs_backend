<?php

namespace Database\Seeders;

use App\Models\ApprovalHistory;
use App\Models\EmployerProfile;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\JobCategory;
use App\Models\JobSeekerSubscription;
use App\Models\JobSeekerProfile;
use App\Models\Language;
use App\Models\Notification;
use App\Models\Religion;
use App\Models\SubscriptionPackage;
use App\Models\SubscriptionPayment;
use App\Models\UgandaLocation;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::query()
            ->whereIn('email', ['admin@tazijobapp.local', 'admin@jobking.local'])
            ->first();

        $admin = User::query()->updateOrCreate(
            ['id' => $admin?->id],
            [
                'name' => 'taziJobApp Admin',
                'email' => 'admin@tazijobapp.local',
                'phone' => '+256700000000',
                'role' => 'admin',
                'status' => 'approved',
                'password' => 'password',
            ]
        );

        $packages = collect([
            ['name' => 'Starter', 'description' => 'Entry package for a few job chances.', 'price' => 10000, 'job_chance_limit' => 2, 'priority_level' => 1, 'is_active' => true],
            ['name' => 'Standard', 'description' => 'More chances and stronger placement priority.', 'price' => 25000, 'job_chance_limit' => 5, 'priority_level' => 2, 'is_active' => true],
            ['name' => 'Premium', 'description' => 'Highest chances and top matching priority.', 'price' => 50000, 'job_chance_limit' => 12, 'priority_level' => 3, 'is_active' => true],
        ])->mapWithKeys(function (array $item) {
            $package = SubscriptionPackage::query()->updateOrCreate(['name' => $item['name']], $item);

            return [$package->name => $package];
        });

        $categories = collect(['Technology', 'Customer support', 'Sales', 'Accounting', 'Domestic work', 'Factory work'])
            ->mapWithKeys(fn (string $name) => [$name => JobCategory::query()->updateOrCreate(['name' => $name], ['is_active' => true])]);

        foreach (['English', 'Luganda', 'Lusoga', 'Runyankole', 'Swahili'] as $name) {
            Language::query()->updateOrCreate(['name' => $name], ['is_active' => true]);
        }

        foreach (['Christian', 'Muslim', 'Other'] as $name) {
            Religion::query()->updateOrCreate(['name' => $name], ['is_active' => true]);
        }

        foreach ([
            ['district' => 'Kampala', 'county' => 'Kampala Central', 'subcounty' => 'Central', 'parish' => 'Nakasero', 'village' => 'Nakasero I'],
            ['district' => 'Kampala', 'county' => 'Rubaga', 'subcounty' => 'Rubaga', 'parish' => 'Nateete', 'village' => 'Nateete Central'],
            ['district' => 'Wakiso', 'county' => 'Kyadondo', 'subcounty' => 'Kira', 'parish' => 'Kireka', 'village' => 'Kireka A'],
            ['district' => 'Jinja', 'county' => 'Jinja City', 'subcounty' => 'Walukuba', 'parish' => 'Walukuba East', 'village' => 'Walukuba Central'],
            ['district' => 'Entebbe', 'county' => 'Entebbe Municipality', 'subcounty' => 'Kitoro', 'parish' => 'Kitoro Parish', 'village' => 'Kitoro Central'],
        ] as $location) {
            UgandaLocation::query()->updateOrCreate($location, ['is_active' => true]);
        }

        $jobSeekers = [
            [
                'user' => ['name' => 'Amina Namara', 'email' => 'amina.jobseeker@example.com', 'phone' => '+256701111111', 'status' => 'approved'],
                'profile' => ['full_name' => 'Amina Namara', 'gender' => 'female', 'date_of_birth' => '1998-04-18', 'location' => 'Kampala, Kampala Central', 'district' => 'Kampala', 'county' => 'Kampala Central', 'subcounty' => 'Central', 'parish' => 'Nakasero', 'village' => 'Nakasero I', 'languages' => ['English', 'Luganda'], 'religion' => 'Christian', 'phone' => '+256701111111', 'education_level' => 'Bachelor of Information Technology', 'skills' => ['Laravel', 'React', 'Customer support'], 'experience_years' => 3, 'bio' => 'API developer and support specialist.', 'work_experience' => 'Three years building APIs and supporting customers.', 'preferred_job_categories' => ['Technology', 'Customer support'], 'terms_accepted' => true, 'is_available' => true, 'status' => 'approved'],
            ],
            [
                'user' => ['name' => 'Brian Okello', 'email' => 'brian.jobseeker@example.com', 'phone' => '+256702222222', 'status' => 'pending'],
                'profile' => ['full_name' => 'Brian Okello', 'gender' => 'male', 'date_of_birth' => '1996-09-02', 'location' => 'Entebbe, Entebbe Municipality', 'district' => 'Entebbe', 'county' => 'Entebbe Municipality', 'subcounty' => 'Kitoro', 'parish' => 'Kitoro Parish', 'village' => 'Kitoro Central', 'languages' => ['English'], 'religion' => 'Muslim', 'phone' => '+256702222222', 'education_level' => 'Diploma in Business Administration', 'skills' => ['Sales', 'Data entry'], 'experience_years' => 2, 'bio' => 'Sales assistant with data entry experience.', 'work_experience' => 'Two years in retail sales.', 'preferred_job_categories' => ['Sales'], 'terms_accepted' => true, 'is_available' => true, 'status' => 'pending'],
            ],
            [
                'user' => ['name' => 'Clare Akello', 'email' => 'clare.jobseeker@example.com', 'phone' => '+256703333333', 'status' => 'rejected'],
                'profile' => ['full_name' => 'Clare Akello', 'gender' => 'female', 'date_of_birth' => '2000-01-12', 'location' => 'Jinja, Jinja City', 'district' => 'Jinja', 'county' => 'Jinja City', 'subcounty' => 'Walukuba', 'parish' => 'Walukuba East', 'village' => 'Walukuba Central', 'languages' => ['English', 'Lusoga'], 'religion' => 'Christian', 'phone' => '+256703333333', 'education_level' => 'Certificate in Accounting', 'skills' => ['Bookkeeping'], 'experience_years' => 1, 'bio' => 'Entry-level accounts assistant.', 'work_experience' => 'One year supporting small business accounts.', 'preferred_job_categories' => ['Accounting'], 'terms_accepted' => true, 'is_available' => false, 'status' => 'rejected', 'rejection_reason' => 'Please upload a clearer CV.'],
            ],
        ];

        $jobSeekerUsers = [];

        foreach ($jobSeekers as $item) {
            $user = User::query()->updateOrCreate(
                ['email' => $item['user']['email']],
                $item['user'] + ['role' => 'job_seeker', 'password' => 'password']
            );

            $profile = JobSeekerProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                $item['profile'] + [
                    'approved_by' => $item['profile']['status'] === 'pending' ? null : $admin->id,
                    'approved_at' => $item['profile']['status'] === 'pending' ? null : now(),
                ]
            );

            if ($profile->status !== 'pending') {
                $this->history($profile, $admin, 'pending', $profile->status, $profile->rejection_reason);
            }

            $jobSeekerUsers[$user->email] = $user;
        }

        $employers = [
            [
                'user' => ['name' => 'Bright Works HR', 'email' => 'hr@brightworks.test', 'phone' => '+256704444444', 'status' => 'approved'],
                'profile' => ['company_name' => 'Bright Works Ltd', 'company_email' => 'hr@brightworks.test', 'company_phone' => '+256704444444', 'company_location' => 'Kampala, Central', 'district' => 'Kampala', 'subcounty' => 'Central', 'company_registration_number' => 'BW-2026-001', 'company_description' => 'Technology and business process outsourcing company.', 'preferred_worker_type' => 'Technology workers', 'website' => 'https://brightworks.test', 'status' => 'approved'],
            ],
            [
                'user' => ['name' => 'Nile Foods HR', 'email' => 'jobs@nilefoods.test', 'phone' => '+256705555555', 'status' => 'pending'],
                'profile' => ['company_name' => 'Nile Foods', 'company_email' => 'jobs@nilefoods.test', 'company_phone' => '+256705555555', 'company_location' => 'Jinja, Walukuba', 'district' => 'Jinja', 'subcounty' => 'Walukuba', 'company_registration_number' => 'NF-7788', 'company_description' => 'Food production and logistics employer.', 'preferred_worker_type' => 'Factory workers', 'status' => 'pending'],
            ],
            [
                'user' => ['name' => 'Quick Hire HR', 'email' => 'team@quickhire.test', 'phone' => '+256706666666', 'status' => 'rejected'],
                'profile' => ['company_name' => 'Quick Hire Agency', 'company_email' => 'team@quickhire.test', 'company_phone' => '+256706666666', 'company_location' => 'Mukono, Goma', 'district' => 'Mukono', 'subcounty' => 'Goma', 'company_registration_number' => 'QH-1100', 'company_description' => 'Recruitment agency profile awaiting correction.', 'preferred_worker_type' => 'Domestic workers', 'status' => 'rejected', 'rejection_reason' => 'Company registration number could not be verified.'],
            ],
        ];

        $employerUsers = [];

        foreach ($employers as $item) {
            $user = User::query()->updateOrCreate(
                ['email' => $item['user']['email']],
                $item['user'] + ['role' => 'employer', 'password' => 'password']
            );

            $profile = EmployerProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                $item['profile'] + [
                    'approved_by' => $item['profile']['status'] === 'pending' ? null : $admin->id,
                    'approved_at' => $item['profile']['status'] === 'pending' ? null : now(),
                ]
            );

            if ($profile->status !== 'pending') {
                $this->history($profile, $admin, 'pending', $profile->status, $profile->rejection_reason);
            }

            $employerUsers[$user->email] = $user;
        }

        $approvedEmployer = $employerUsers['hr@brightworks.test'];
        $jobs = [
            ['job_category_id' => $categories['Technology']->id, 'title' => 'Laravel Developer', 'description' => 'Build and maintain taziJobApp-style APIs.', 'requirements' => 'Laravel, MySQL, REST APIs', 'responsibilities' => 'Develop endpoints, write tests, review code.', 'location' => 'Kampala, Kampala Central', 'district' => 'Kampala', 'county' => 'Kampala Central', 'subcounty' => 'Central', 'parish' => 'Nakasero', 'village' => 'Nakasero I', 'job_type' => 'remote', 'salary_min' => 1200, 'salary_max' => 2500, 'deadline' => now()->addDays(21)->toDateString(), 'status' => 'approved'],
            ['job_category_id' => $categories['Customer support']->id, 'title' => 'Customer Support Agent', 'description' => 'Support platform users via chat and phone.', 'requirements' => 'Communication skills, English, basic computer skills', 'responsibilities' => 'Resolve tickets and escalate issues.', 'location' => 'Kampala, Kampala Central', 'district' => 'Kampala', 'county' => 'Kampala Central', 'subcounty' => 'Central', 'parish' => 'Nakasero', 'village' => 'Nakasero I', 'job_type' => 'full_time', 'salary_min' => 500, 'salary_max' => 900, 'deadline' => now()->addDays(14)->toDateString(), 'status' => 'pending'],
            ['job_category_id' => $categories['Sales']->id, 'title' => 'Data Entry Clerk', 'description' => 'Clean and enter business records.', 'requirements' => 'Attention to detail', 'responsibilities' => 'Data validation and reporting.', 'location' => 'Entebbe, Entebbe Municipality', 'district' => 'Entebbe', 'county' => 'Entebbe Municipality', 'subcounty' => 'Kitoro', 'parish' => 'Kitoro Parish', 'village' => 'Kitoro Central', 'job_type' => 'contract', 'salary_min' => 300, 'salary_max' => 650, 'deadline' => now()->addDays(7)->toDateString(), 'status' => 'rejected', 'rejection_reason' => 'Job description needs more detail.'],
        ];

        $createdJobs = [];

        foreach ($jobs as $item) {
            $job = Job::query()->updateOrCreate(
                ['employer_id' => $approvedEmployer->id, 'title' => $item['title']],
                $item + [
                    'approved_by' => $item['status'] === 'pending' ? null : $admin->id,
                    'approved_at' => $item['status'] === 'pending' ? null : now(),
                ]
            );

            if ($job->status !== 'pending') {
                $this->history($job, $admin, 'pending', $job->status, $job->rejection_reason);
            }

            $createdJobs[$job->title] = $job;
        }

        JobApplication::query()->updateOrCreate(
            ['job_id' => $createdJobs['Laravel Developer']->id, 'job_seeker_id' => $jobSeekerUsers['amina.jobseeker@example.com']->id],
            [
                'cover_letter' => 'I have Laravel and React experience and would love to join.',
                'status' => 'shortlisted',
                'approval_status' => 'approved',
                'approved_by' => $admin->id,
                'approved_at' => now(),
                'employer_notes' => 'Strong profile.',
            ]
        );

        $subscription = JobSeekerSubscription::query()->updateOrCreate(
            ['user_id' => $jobSeekerUsers['amina.jobseeker@example.com']->id, 'status' => 'active'],
            [
                'subscription_package_id' => $packages['Standard']->id,
                'amount_paid' => $packages['Standard']->price,
                'job_chance_limit' => $packages['Standard']->job_chance_limit,
                'job_chances_used' => 1,
                'priority_level' => $packages['Standard']->priority_level,
                'started_at' => now(),
            ]
        );

        SubscriptionPayment::query()->updateOrCreate(
            ['job_seeker_subscription_id' => $subscription->id, 'type' => 'initial'],
            [
                'user_id' => $jobSeekerUsers['amina.jobseeker@example.com']->id,
                'subscription_package_id' => $packages['Standard']->id,
                'amount' => $packages['Standard']->price,
                'status' => 'confirmed',
            ]
        );

        Notification::query()->updateOrCreate(
            ['user_id' => $jobSeekerUsers['amina.jobseeker@example.com']->id, 'type' => 'application_status', 'title' => 'Application status updated'],
            ['message' => 'Your application for Laravel Developer is now shortlisted.', 'is_read' => false]
        );

        Notification::query()->updateOrCreate(
            ['user_id' => $approvedEmployer->id, 'type' => 'job_application', 'title' => 'New job application'],
            ['message' => 'Amina Namara applied for Laravel Developer.', 'is_read' => false]
        );
    }

    private function history($approvable, User $admin, ?string $from, string $to, ?string $reason = null): void
    {
        ApprovalHistory::query()->updateOrCreate(
            [
                'approvable_type' => $approvable::class,
                'approvable_id' => $approvable->id,
                'to_status' => $to,
            ],
            [
                'admin_id' => $admin->id,
                'from_status' => $from,
                'rejection_reason' => $reason,
            ]
        );
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApprovalDecisionRequest;
use App\Http\Requests\StoreSubscriptionPackageRequest;
use App\Models\EmployerProfile;
use App\Models\AdminRole;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\JobSeekerProfile;
use App\Models\JobSeekerSubscription;
use App\Models\SubscriptionPackage;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Models\WorkerOrder;
use App\Support\NotifiesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Services\SmsService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    use NotifiesUsers;

    private const EDUCATION_LEVELS = [
        'Primary Level',
        'Secondary O Level',
        'Secondary A Level',
        'Tertiary Level',
        'University Level',
    ];

    public function stats()
    {
        return response()->json([
            'data' => [
                'users' => User::query()->count(),
                'total_job_seekers' => User::query()->where('role', 'job_seeker')->count(),
                'job_seekers_pending' => JobSeekerProfile::query()->where('status', 'pending')->count(),
                'job_seekers_approved' => JobSeekerProfile::query()->where('status', 'approved')->count(),
                'total_employers' => User::query()->where('role', 'employer')->count(),
                'employers_pending' => EmployerProfile::query()->where('status', 'pending')->count(),
                'employers_approved' => EmployerProfile::query()->where('status', 'approved')->count(),
                'jobs_pending' => Job::query()->where('status', 'pending')->count(),
                'approved_jobs' => Job::query()->where('status', 'approved')->count(),
                'applications' => JobApplication::query()->count(),
                'applications_pending' => JobApplication::query()->where('approval_status', 'pending')->count(),
                'worker_orders_pending' => WorkerOrder::query()->where('status', 'pending')->count(),
                'subscription_packages' => SubscriptionPackage::query()->count(),
                'job_categories' => \App\Models\JobCategory::query()->count(),
            ],
        ]);
    }

    public function users(Request $request)
    {
        $users = User::query()
            ->with(['jobSeekerProfile', 'employerProfile', 'adminRole', 'activeJobSeekerSubscription.package'])
            ->when($request->query('search'), function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($request->query('role'), fn ($query, string $role) => $query->where('role', $role))
            ->when($request->query('status'), fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate(20);

        return response()->json(['data' => $users]);
    }

    public function roles()
    {
        return response()->json([
            'data' => [
                'roles' => AdminRole::query()->withCount('users')->latest()->get(),
                'permissions' => $this->availableStaffPermissions(),
                'system_roles' => collect(config('permissions.roles'))->map(fn (array $permissions, string $role) => [
                    'role' => $role,
                    'label' => Str::headline(str_replace('_', ' ', $role)),
                    'description' => match ($role) {
                        'admin' => 'Built-in full administrator account type. Full admins without a custom staff role keep all permissions.',
                        'job_seeker' => 'Built-in account type created when someone signs up to look for jobs.',
                        'employer' => 'Built-in account type created when someone signs up to post jobs or hire workers.',
                        default => 'Built-in system account type.',
                    },
                    'permissions' => $permissions,
                ])->values(),
            ],
        ]);
    }

    public function storeRole(Request $request)
    {
        $allowed = array_keys($this->availableStaffPermissions());

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:admin_roles,name'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['required', 'string', 'in:'.implode(',', $allowed)],
        ]);

        $permissions = array_values(array_unique($data['permissions']));
        if (! in_array('access_admin', $permissions, true)) {
            $permissions[] = 'access_admin';
        }

        $role = AdminRole::query()->create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.str()->lower(str()->random(5)),
            'description' => $data['description'] ?? null,
            'permissions' => $permissions,
        ]);

        return response()->json(['message' => 'Role created.', 'data' => $role], 201);
    }

    public function updateRole(Request $request, AdminRole $role)
    {
        $allowed = array_keys($this->availableStaffPermissions());

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:admin_roles,name,'.$role->id],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['required', 'string', 'in:'.implode(',', $allowed)],
        ]);

        $permissions = array_values(array_unique($data['permissions']));
        if (! in_array('access_admin', $permissions, true)) {
            $permissions[] = 'access_admin';
        }

        $role->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'permissions' => $permissions,
        ]);

        return response()->json(['message' => 'Role updated.', 'data' => $role->fresh()->loadCount('users')]);
    }

    public function storeUser(Request $request)
    {
        if (filled($request->input('phone'))) {
            $request->merge(['phone' => '+'.app(SmsService::class)->normalizePhone($request->input('phone'))]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'regex:/^(?:\+256|256|0)?7\d{8}$/', 'unique:users,phone'],
            'role' => ['required', 'in:admin,job_seeker,employer'],
            'admin_role_id' => ['nullable', 'exists:admin_roles,id'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        if (filled($data['phone'] ?? null)) {
            $data['phone_verified_at'] = now();
        }

        if (($data['role'] ?? null) !== 'admin') {
            $data['admin_role_id'] = null;
        }

        $user = User::query()->create($data + ['status' => 'approved']);

        $this->notifyUser(
            $user,
            'account_created_by_admin',
            'Account created',
            'Your TaziJobs account has been created by an administrator.'
        );

        return response()->json(['message' => 'User account created.', 'data' => $user->load('adminRole')], 201);
    }

    public function updateUser(Request $request, User $user)
    {
        if (filled($request->input('phone'))) {
            $request->merge(['phone' => '+'.app(SmsService::class)->normalizePhone($request->input('phone'))]);
        }
        if ($request->has('email') && blank($request->input('email'))) {
            $request->merge(['email' => null]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['required', 'regex:/^(?:\+256|256|0)?7\d{8}$/', Rule::unique('users', 'phone')->ignore($user->id)],
            'role' => ['required', 'in:admin,job_seeker,employer'],
            'status' => ['required', 'in:pending,approved,rejected,suspended'],
            'admin_role_id' => ['nullable', 'exists:admin_roles,id'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'profile' => ['nullable', 'array'],
            'profile.full_name' => ['nullable', 'string', 'max:255'],
            'profile.job_title' => ['nullable', 'string', 'max:150'],
            'profile.gender' => ['nullable', Rule::in(['male', 'female'])],
            'profile.date_of_birth' => ['nullable', 'date'],
            'profile.location' => ['nullable', 'string', 'max:255'],
            'profile.phone' => ['nullable', 'regex:/^(?:\+256|256|0)?7\d{8}$/'],
            'profile.district' => ['nullable', 'string', 'max:255'],
            'profile.county' => ['nullable', 'string', 'max:255'],
            'profile.subcounty' => ['nullable', 'string', 'max:255'],
            'profile.parish' => ['nullable', 'string', 'max:255'],
            'profile.village' => ['nullable', 'string', 'max:255'],
            'profile.languages' => ['nullable', 'array'],
            'profile.languages.*' => ['string', 'max:100'],
            'profile.religion' => ['nullable', 'string', 'max:100'],
            'profile.education_level' => ['nullable', Rule::in(self::EDUCATION_LEVELS)],
            'profile.skills' => ['nullable', 'array'],
            'profile.skills.*' => ['string', 'max:100'],
            'profile.experience_years' => ['nullable', 'integer', 'min:0', 'max:80'],
            'profile.bio' => ['nullable', 'string'],
            'profile.work_experience' => ['nullable', 'string'],
            'profile.preferred_job_categories' => ['nullable', 'array'],
            'profile.preferred_job_categories.*' => ['string', 'max:100'],
            'profile.is_available' => ['nullable', 'boolean'],
            'profile.employer_type' => ['nullable', Rule::in(['company', 'individual'])],
            'profile.company_name' => ['nullable', 'string', 'max:255'],
            'profile.company_email' => ['nullable', 'email', 'max:255'],
            'profile.company_phone' => ['nullable', 'regex:/^(?:\+256|256|0)?7\d{8}$/'],
            'profile.company_location' => ['nullable', 'string', 'max:255'],
            'profile.company_registration_number' => ['nullable', 'string', 'max:255'],
            'profile.company_description' => ['nullable', 'string'],
            'profile.preferred_worker_type' => ['nullable', 'string', 'max:255'],
            'profile.website' => ['nullable', 'url', 'max:255'],
        ]);

        if ($request->user()->is($user) && (
            $data['role'] !== 'admin'
            || $data['status'] !== 'approved'
            || (int) ($data['admin_role_id'] ?? 0) !== (int) ($user->admin_role_id ?? 0)
        )) {
            throw ValidationException::withMessages([
                'user' => ['You cannot change your own admin role, account role, or approval status.'],
            ]);
        }

        if (($data['role'] ?? null) !== 'admin') {
            $data['admin_role_id'] = null;
        }

        if (($data['phone'] ?? null) !== $user->phone) {
            $data['phone_verified_at'] = filled($data['phone'] ?? null) ? now() : null;
        }

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        unset($data['password_confirmation']);

        $profileData = $data['profile'] ?? [];
        unset($data['profile']);

        $user->update($data);

        $this->updateEditableProfile($user->fresh(), $profileData, $data['status']);

        return response()->json(['message' => 'User account updated.', 'data' => $user->fresh()->load(['adminRole', 'jobSeekerProfile', 'employerProfile'])]);
    }

    private function updateEditableProfile(User $user, array $profileData, string $status): void
    {
        if ($profileData === [] || $user->role === 'admin') {
            return;
        }

        $profileData = array_filter($profileData, fn ($value) => ! is_null($value));
        $profileData['status'] = $status;

        if ($user->role === 'job_seeker') {
            if (filled($profileData['phone'] ?? null)) {
                $profileData['phone'] = '+'.app(SmsService::class)->normalizePhone($profileData['phone']);
            }

            $allowed = [
                'full_name',
                'job_title',
                'phone',
                'district',
                'county',
                'subcounty',
                'parish',
                'village',
                'education_level',
                'experience_years',
                'bio',
                'status',
            ];

            $user->jobSeekerProfile()->updateOrCreate(
                ['user_id' => $user->id],
                collect($profileData)->only($allowed)->toArray() + ['full_name' => $user->name]
            );
        }

        if ($user->role === 'employer') {
            if (filled($profileData['company_phone'] ?? null)) {
                $profileData['company_phone'] = '+'.app(SmsService::class)->normalizePhone($profileData['company_phone']);
            }

            $allowed = [
                'employer_type',
                'company_name',
                'company_email',
                'company_phone',
                'company_location',
                'district',
                'county',
                'subcounty',
                'parish',
                'village',
                'company_registration_number',
                'website',
                'status',
            ];

            $user->employerProfile()->updateOrCreate(
                ['user_id' => $user->id],
                collect($profileData)->only($allowed)->toArray() + ['company_name' => $user->name]
            );
        }
    }

    private function availableStaffPermissions(): array
    {
        return [
            'access_admin' => 'Access admin dashboard',

            'view_dashboard' => 'View dashboard',

            'approve_employers' => 'Approve employers',
            'approve_job_seekers' => 'Approve job seekers',
            'approve_jobs' => 'Approve jobs',
            'approve_applications' => 'Approve job applications',
            'approve_worker_orders' => 'Approve worker orders',

            'upload_jobs' => 'Upload jobs',
            'search_jobs' => 'Search jobs',
            'view_pending_jobs' => 'View pending jobs',

            'manage_sms' => 'Manage SMS',
            'top_up_sms' => 'Top up SMS',
            'check_sms_balance' => 'Check SMS balance',
            'see_sms_payments' => 'See SMS payments',
            'see_sms_topups' => 'See SMS top-ups',
            'process_sms_payments' => 'Refresh/distribute SMS payments',

            'manage_catalogs' => 'Manage catalogs',
            'view_catalogs' => 'View catalogs',
            'create_catalogs' => 'Create catalog entries',

            'manage_subscription_packages' => 'Manage subscription packages',
            'view_subscription_packages_admin' => 'View subscription packages',
            'create_subscription_packages' => 'Create subscription packages',
            'edit_subscription_packages' => 'Edit subscription packages',

            'manage_users' => 'Manage users',
            'view_users' => 'View users',
            'create_users' => 'Create staff users',
            'edit_users' => 'Edit users',
            'suspend_users' => 'Suspend users',

            'manage_roles' => 'Manage roles and permissions',
            'view_roles' => 'View roles and permissions',
            'create_roles' => 'Create roles',
            'edit_roles' => 'Edit roles',

            'view_notifications' => 'View notifications',
        ];
    }

    public function suspendUser(User $user)
    {
        $user->forceFill(['status' => 'suspended'])->save();

        $this->notifyUser($user, 'account_suspended', 'Account suspended', 'Your taziJobApp account has been suspended by an administrator.');

        return response()->json(['message' => 'User account suspended.', 'data' => $user]);
    }

    public function assignSubscription(Request $request, User $user)
    {
        $data = $request->validate([
            'subscription_package_id' => ['required', 'integer', 'exists:subscription_packages,id'],
        ]);

        if ($user->role !== 'job_seeker') {
            throw ValidationException::withMessages([
                'user' => ['Subscription packages can only be assigned to job seekers.'],
            ]);
        }

        if ($user->activeJobSeekerSubscription()->exists()) {
            throw ValidationException::withMessages([
                'subscription' => ['This job seeker already has an active subscription.'],
            ]);
        }

        $package = SubscriptionPackage::query()
            ->where('is_active', true)
            ->findOrFail($data['subscription_package_id']);

        $subscription = JobSeekerSubscription::query()->create([
            'user_id' => $user->id,
            'subscription_package_id' => $package->id,
            'amount_paid' => $package->price,
            'job_chance_limit' => $package->job_chance_limit,
            'priority_level' => $package->priority_level,
            'status' => 'active',
            'started_at' => now(),
        ]);

        SubscriptionPayment::query()->create([
            'user_id' => $user->id,
            'subscription_package_id' => $package->id,
            'job_seeker_subscription_id' => $subscription->id,
            'amount' => $package->price,
            'type' => 'admin_assignment',
            'status' => 'confirmed',
        ]);

        return response()->json([
            'message' => 'Subscription package assigned.',
            'data' => $subscription->load('package'),
        ], 201);
    }

    public function showJobSeeker(JobSeekerProfile $profile)
    {
        return response()->json(['data' => $profile->load(['user', 'approver', 'approvalHistories.admin'])]);
    }

    public function pendingJobSeekers(Request $request)
    {
        return response()->json([
            'data' => JobSeekerProfile::query()
                ->with('user')
                ->where('status', 'pending')
                ->when($request->query('search'), function ($query, string $search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('full_name', 'like', "%{$search}%")
                            ->orWhere('job_title', 'like', "%{$search}%")
                            ->orWhere('location', 'like', "%{$search}%")
                            ->orWhere('district', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('preferred_job_categories', 'like', "%{$search}%")
                            ->orWhereHas('user', fn ($query) => $query->where('email', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"));
                    });
                })
                ->latest()
                ->paginate(15),
        ]);
    }

    public function decideJobSeeker(ApprovalDecisionRequest $request, JobSeekerProfile $profile)
    {
        return $this->decide($request, $profile, $profile->user, 'job_seeker_profile', 'Job seeker profile');
    }

    public function pendingEmployers(Request $request)
    {
        return response()->json([
            'data' => EmployerProfile::query()
                ->with('user')
                ->where('status', 'pending')
                ->when($request->query('search'), function ($query, string $search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('company_name', 'like', "%{$search}%")
                            ->orWhere('company_email', 'like', "%{$search}%")
                            ->orWhere('company_location', 'like', "%{$search}%");
                    });
                })
                ->latest()
                ->paginate(15),
        ]);
    }

    public function decideEmployer(ApprovalDecisionRequest $request, EmployerProfile $profile)
    {
        return $this->decide($request, $profile, $profile->user, 'employer_profile', 'Employer profile');
    }

    public function showEmployer(EmployerProfile $profile)
    {
        return response()->json(['data' => $profile->load(['user', 'approver', 'approvalHistories.admin'])]);
    }

    public function pendingJobs(Request $request)
    {
        return response()->json([
            'data' => Job::query()
                ->with(['category', 'employer.employerProfile'])
                ->where('status', 'pending')
                ->when($request->query('search'), function ($query, string $search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('title', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%")
                            ->orWhere('job_type', 'like', "%{$search}%");
                    });
                })
                ->latest()
                ->paginate(15),
        ]);
    }

    public function jobs(Request $request)
    {
        return response()->json([
            'data' => Job::query()
                ->with(['category', 'employer.employerProfile'])
                ->where('status', 'approved')
                ->when($request->query('search'), function ($query, string $search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('title', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%")
                            ->orWhere('job_type', 'like', "%{$search}%")
                            ->orWhere('location', 'like', "%{$search}%")
                            ->orWhere('district', 'like', "%{$search}%")
                            ->orWhere('county', 'like', "%{$search}%")
                            ->orWhereHas('category', fn ($query) => $query->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('employer', fn ($query) => $query->where('name', 'like', "%{$search}%")
                                ->orWhereHas('employerProfile', fn ($query) => $query->where('company_name', 'like', "%{$search}%")));
                    });
                })
                ->latest()
                ->paginate(10),
        ]);
    }

    public function decideJob(ApprovalDecisionRequest $request, Job $job)
    {
        return $this->decide($request, $job, $job->employer, 'job_approval', 'Job');
    }

    public function showJob(Job $job)
    {
        return response()->json(['data' => $job->load(['category', 'employer.employerProfile', 'approver', 'approvalHistories.admin'])]);
    }

    public function file(string $type, int $id, string $field)
    {
        $model = match ($type) {
            'job-seeker-profile' => JobSeekerProfile::query()->findOrFail($id),
            'employer-profile' => EmployerProfile::query()->findOrFail($id),
            default => abort(404),
        };

        $allowedFields = match ($type) {
            'job-seeker-profile' => ['cv_file', 'profile_photo', 'lc1_letter_file', 'id_document_file', 'id_document_front_file', 'id_document_back_file'],
            'employer-profile' => ['company_logo', 'business_document_file'],
            default => [],
        };

        abort_unless(in_array($field, $allowedFields, true), 404);

        $path = $model->{$field};
        abort_unless($path && Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path);
    }

    public function pendingApplications()
    {
        return response()->json([
            'data' => JobApplication::query()
                ->with(['job.employer.employerProfile', 'jobSeeker.jobSeekerProfile'])
                ->where('approval_status', 'pending')
                ->latest()
                ->paginate(15),
        ]);
    }

    public function decideApplication(ApprovalDecisionRequest $request, JobApplication $application)
    {
        $data = $request->validated();
        $approved = $data['status'] === 'approved';

        $application->forceFill([
            'approval_status' => $data['status'],
            'rejection_reason' => $approved ? null : $data['rejection_reason'],
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ])->save();

        $this->notifyUser(
            $application->jobSeeker,
            'job_application_approval',
            "Job application {$data['status']}",
            $approved
                ? "Your application for {$application->job->title} has been approved and sent to the employer."
                : "Your application for {$application->job->title} was rejected: {$application->rejection_reason}"
        );

        if ($approved) {
            $this->notifyUser(
                $application->job->employer,
                'job_application',
                'New approved job application',
                "{$application->jobSeeker->name} applied for {$application->job->title}."
            );
        }

        return response()->json(['message' => "Application {$data['status']}.", 'data' => $application->fresh(['job', 'jobSeeker'])]);
    }

    public function pendingWorkerOrders()
    {
        return response()->json([
            'data' => WorkerOrder::query()->with(['employer.employerProfile', 'worker.user'])->where('status', 'pending')->latest()->paginate(15),
        ]);
    }

    public function decideWorkerOrder(ApprovalDecisionRequest $request, WorkerOrder $order)
    {
        $data = $request->validated();
        $approved = $data['status'] === 'approved';

        $order->forceFill([
            'status' => $data['status'],
            'rejection_reason' => $approved ? null : $data['rejection_reason'],
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ])->save();

        $this->notifyUser(
            $order->employer,
            'worker_order',
            "Worker request {$data['status']}",
            $approved ? 'Your worker request has been approved.' : "Your worker request was rejected: {$order->rejection_reason}"
        );

        $this->notifyUser(
            $order->worker->user,
            'worker_order',
            "Worker request {$data['status']}",
            $approved ? 'A worker request involving your profile has been approved.' : 'A worker request involving your profile was rejected.'
        );

        return response()->json(['message' => "Worker request {$data['status']}.", 'data' => $order->fresh(['employer', 'worker.user'])]);
    }

    public function subscriptionPackages()
    {
        return response()->json([
            'data' => SubscriptionPackage::query()->orderBy('price')->paginate(20),
        ]);
    }

    public function storeSubscriptionPackage(StoreSubscriptionPackageRequest $request)
    {
        $package = SubscriptionPackage::query()->create($request->validated());

        return response()->json(['message' => 'Subscription package created.', 'data' => $package], 201);
    }

    public function updateSubscriptionPackage(StoreSubscriptionPackageRequest $request, SubscriptionPackage $package)
    {
        $package->update($request->validated());

        return response()->json(['message' => 'Subscription package updated.', 'data' => $package->fresh()]);
    }

    private function decide(ApprovalDecisionRequest $request, mixed $model, User $owner, string $type, string $label)
    {
        $data = $request->validated();
        $approved = $data['status'] === 'approved';
        $fromStatus = $model->status;

        $model->forceFill([
            'status' => $data['status'],
            'rejection_reason' => $approved ? null : $data['rejection_reason'],
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ])->save();

        $model->approvalHistories()->create([
            'admin_id' => auth()->id(),
            'from_status' => $fromStatus,
            'to_status' => $data['status'],
            'rejection_reason' => $approved ? null : $data['rejection_reason'],
        ]);

        if ($model instanceof JobSeekerProfile || $model instanceof EmployerProfile) {
            $owner->forceFill(['status' => $data['status']])->save();
        }

        $this->notifyUser(
            $owner,
            $type,
            "{$label} {$data['status']}",
            $approved ? "Your {$label} has been approved." : "Your {$label} was rejected: {$model->rejection_reason}"
        );

        if (($model instanceof JobSeekerProfile || $model instanceof EmployerProfile) && filled($owner->phone)) {
            $sms = $approved
                ? "TaziJobs: Your {$label} has been approved."
                : "TaziJobs: Your {$label} was rejected. Reason: {$model->rejection_reason}";
            try {
                app(SmsService::class)->send($owner->phone, Str::limit($sms, 159, ''));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return response()->json(['message' => "{$label} {$data['status']}.", 'data' => $model->fresh()]);
    }
}

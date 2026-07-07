<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApprovalDecisionRequest;
use App\Http\Requests\StoreSubscriptionPackageRequest;
use App\Models\EmployerProfile;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\JobSeekerProfile;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Models\WorkerOrder;
use App\Support\NotifiesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    use NotifiesUsers;

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
            ->with(['jobSeekerProfile', 'employerProfile'])
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

    public function suspendUser(User $user)
    {
        $user->forceFill(['status' => 'suspended'])->save();

        $this->notifyUser($user, 'account_suspended', 'Account suspended', 'Your taziJobApp account has been suspended by an administrator.');

        return response()->json(['message' => 'User account suspended.', 'data' => $user]);
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
                            ->orWhere('location', 'like', "%{$search}%")
                            ->orWhereHas('user', fn ($query) => $query->where('email', 'like', "%{$search}%"));
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

        return response()->json(['message' => "{$label} {$data['status']}.", 'data' => $model->fresh()]);
    }
}

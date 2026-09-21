<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJobRequest;
use App\Models\Job;
use App\Models\JobSeekerProfile;
use App\Models\User;
use App\Services\AdminApprovalNotifier;
use App\Services\JobSeekerJobNotifier;
use Illuminate\Http\Request;

class JobController extends Controller
{
    public function __construct(private readonly AdminApprovalNotifier $adminApprovalNotifier) {}

    public function index(Request $request)
    {
        $perPage = (int) $request->integer('per_page', 50);
        $perPage = max(1, min($perPage, 100));

        $jobs = Job::query()
            ->with(['category', 'employer.employerProfile'])
            ->publiclyVisible()
            ->when($request->user()?->role === 'job_seeker', function ($query) use ($request) {
                $profile = $request->user()->jobSeekerProfile;
                if ($profile?->status === 'approved') {
                    $query->orderByRaw(
                        'CASE WHEN EXISTS (SELECT 1 FROM job_categories WHERE job_categories.id = jobs.job_category_id AND job_categories.skill_level = ?) THEN 0 ELSE 1 END',
                        [$profile->skill_classification],
                    );
                }
            })
            ->when($request->query('job_category_id'), fn ($query, string $category) => $query->where('job_category_id', $category))
            ->when($request->query('title'), fn ($query, string $title) => $query->where('title', 'like', "%{$title}%"))
            ->when($request->query('search'), function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhere('district', 'like', "%{$search}%")
                        ->orWhere('county', 'like', "%{$search}%")
                        ->orWhereHas('category', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->query('district'), function ($query, string $district) {
                $query->where(function ($query) use ($district) {
                    $query->where('district', 'like', "%{$district}%")
                        ->orWhere('location', 'like', "%{$district}%");
                });
            })
            ->when($request->query('county'), function ($query, string $county) {
                $query->where(function ($query) use ($county) {
                    $query->where('county', 'like', "%{$county}%")
                        ->orWhere('location', 'like', "%{$county}%");
                });
            })
            ->when($request->query('location'), fn ($query, string $location) => $query->where('location', 'like', "%{$location}%"))
            ->when($request->query('job_type'), fn ($query, string $type) => $query->where('job_type', $type))
            ->when($request->query('salary_min'), fn ($query, string $salary) => $query->where(function ($query) use ($salary) {
                $query->whereNull('salary_max')->orWhere('salary_max', '>=', $salary);
            }))
            ->when($request->query('salary_max'), fn ($query, string $salary) => $query->where(function ($query) use ($salary) {
                $query->whereNull('salary_min')->orWhere('salary_min', '<=', $salary);
            }))
            ->when($request->query('deadline_from'), fn ($query, string $date) => $query->whereDate('deadline', '>=', $date))
            ->when($request->query('deadline_to'), fn ($query, string $date) => $query->whereDate('deadline', '<=', $date))
            ->latest()
            ->paginate($perPage);

        $jobs->getCollection()->transform(fn (Job $job) => $this->publicJobPayload($job,$request));

        return response()->json(['data' => $jobs]);
    }

    public function store(StoreJobRequest $request)
    {
        if (! $request->user()->employerProfile()->where('status', 'approved')->exists()) {
            return response()->json(['message' => 'Employer profile must be approved before posting jobs.'], 422);
        }

        $data = collect($request->validated())->except('employer_id')->all();
        $data['location'] = $data['location'] ?? $this->formatLocation($data);

        $job = $request->user()->jobPosts()->create($data + ['status' => 'pending']);

        $this->adminApprovalNotifier->notifyAdmins(
            'New job awaiting approval',
            "A new job titled {$job->title} is awaiting administrative approval."
        );

        return response()->json(['message' => 'Job submitted for approval.', 'data' => $job], 201);
    }

    public function adminStore(StoreJobRequest $request, JobSeekerJobNotifier $jobNotifier)
    {
        $data = $request->validated();
        $employer = User::query()->where('role', 'employer')->findOrFail($data['employer_id'] ?? null);
        $data['location'] = $data['location'] ?? $this->formatLocation($data);

        $job = Job::query()->create($data + [
            'employer_id' => $employer->id,
            'status' => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);
        $jobNotifier->notifyForApprovedJob($job);

        return response()->json(['message' => 'Job uploaded and approved by admin.', 'data' => $job->load(['category', 'employer.employerProfile'])], 201);
    }

    public function adminUpdate(StoreJobRequest $request, Job $job)
    {
        $data = $request->validated();

        if (array_key_exists('employer_id', $data)) {
            User::query()->where('role', 'employer')->findOrFail($data['employer_id']);
        }

        $data['location'] = $data['location'] ?? $this->formatLocation($data);
        $job->update($data);

        return response()->json([
            'message' => 'Job updated.',
            'data' => $job->fresh(['category', 'employer.employerProfile', 'approver', 'approvalHistories.admin']),
        ]);
    }

    public function matching(Request $request)
    {
        $perPage = (int) $request->integer('per_page', 50);
        $perPage = max(1, min($perPage, 100));
        $profile = $request->user()->jobSeekerProfile;

        if (! $profile || $profile->status !== 'approved') {
            return response()->json(['message' => 'Job seeker profile must be approved before viewing matching jobs.'], 422);
        }

        $preferredCategories = $profile->preferred_job_categories ?? [];

        $jobs = Job::query()
            ->with(['category', 'employer.employerProfile'])
            ->publiclyVisible()
            ->where(function ($query) use ($profile, $preferredCategories) {
                $query->whereHas('category', fn ($query) => $query->whereIn('name', $preferredCategories))
                    ->orWhere('district', $profile->district);
            })
            ->orderByRaw(
                'CASE WHEN EXISTS (SELECT 1 FROM job_categories WHERE job_categories.id = jobs.job_category_id AND job_categories.skill_level = ?) THEN 0 ELSE 1 END',
                [$profile->skill_classification],
            )
            ->latest()
            ->paginate($perPage);

        $jobs->getCollection()->transform(fn (Job $job) => $this->publicJobPayload($job, $request));

        return response()->json(['data' => $jobs]);
    }

    public function show(Request $request, Job $job)
    {
        if (! $job->isPubliclyVisible()) {
            return response()->json(['message' => 'Job not found.'], 404);
        }

        return response()->json(['data' => $this->publicJobPayload($job->load(['category', 'employer.employerProfile']), $request)]);
    }

    public function employerJobs(Request $request)
    {
        $jobs = Job::query()
            ->where('employer_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return response()->json(['data' => $jobs]);
    }

    public function update(StoreJobRequest $request, Job $job)
    {
        if ($job->employer_id !== $request->user()->id) {
            return response()->json(['message' => 'Job not found.'], 404);
        }

        if (! $request->user()->employerProfile()->where('status', 'approved')->exists()) {
            return response()->json(['message' => 'Employer profile must be approved before editing jobs.'], 422);
        }

        $data = collect($request->validated())->except('employer_id')->all();
        $data['location'] = $data['location'] ?? $this->formatLocation($data);

        $job->update($data + [
            'status' => 'pending',
            'rejection_reason' => null,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        return response()->json([
            'message' => 'Job changes submitted for approval.',
            'data' => $job->fresh(),
        ]);
    }

    public function getCategorizedWorkers(Request $request)
    {
        $profiles = JobSeekerProfile::query()
            ->with('user')
            ->publiclyVisible()
            ->get();

        $classified = $profiles->map(function (JobSeekerProfile $profile) {
            return array_merge($profile->toArray(), [
                'classification' => $profile->skill_classification,
            ]);
        });

        $skilled = $classified->where('classification', 'skilled')->values();
        $unskilled = $classified->where('classification', 'unskilled')->values();

        return response()->json([
            'skilled_workers' => $skilled,
            'unskilled_workers' => $unskilled,
        ]);
    }


    private function publicJobPayload(Job $job, Request $request): array
{
    $payload = $job->toArray();

    // Check if the logged-in user is an admin or employer
    $showPhone = $request->user() && $request->user()->canSeeContactDetails();

    if (!$showPhone) {
        unset($payload['contact_phone']); // Hide job phone
        
        // Hide company profile phone if nested in the response
            if (isset($payload['employer']['employer_profile'])) {
                unset(
                    $payload['employer']['employer_profile']['company_phone'],
                    $payload['employer']['employer_profile']['subcounty']
                );
            }
        }

        return $payload;
    }

    private function formatLocation(array $data): ?string
    {
        $parts = array_filter([
            $data['district'] ?? null,
            $data['county'] ?? null,
        ]);

        return $parts === [] ? null : implode(', ', $parts);
    }
}

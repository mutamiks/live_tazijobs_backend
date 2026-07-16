<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJobRequest;
use App\Models\Job;
use App\Models\User;
use Illuminate\Http\Request;

class JobController extends Controller
{
    public function index(Request $request)
    {
        if ($response = $this->requireActiveSubscription($request)) {
            return $response;
        }

        $jobs = Job::query()
            ->with(['category', 'employer.employerProfile'])
            ->publiclyVisible()
            ->when($request->query('job_category_id'), fn ($query, string $category) => $query->where('job_category_id', $category))
            ->when($request->query('search'), function ($query, string $search) {
                $query->where('title', 'like', "%{$search}%");
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
            ->paginate(15);

        $jobs->getCollection()->transform(fn (Job $job) => $this->publicJobPayload($job));

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

        return response()->json(['message' => 'Job submitted for approval.', 'data' => $job], 201);
    }

    public function adminStore(StoreJobRequest $request)
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

        return response()->json(['message' => 'Job uploaded and approved by admin.', 'data' => $job->load(['category', 'employer.employerProfile'])], 201);
    }

    public function matching(Request $request)
    {
        if ($response = $this->requireActiveSubscription($request)) {
            return $response;
        }

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
            ->latest()
            ->paginate(15);

        $jobs->getCollection()->transform(fn (Job $job) => $this->publicJobPayload($job));

        return response()->json(['data' => $jobs]);
    }

    public function show(Request $request, Job $job)
    {
        if ($response = $this->requireActiveSubscription($request)) {
            return $response;
        }

        if (! $job->isPubliclyVisible()) {
            return response()->json(['message' => 'Job not found.'], 404);
        }

        return response()->json(['data' => $this->publicJobPayload($job->load(['category', 'employer.employerProfile']))]);
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
    private function requireActiveSubscription(Request $request)
    {
        if (! $request->user()->activeJobSeekerSubscription()->exists()) {
            return response()->json([
                'message' => 'Subscribe to a package before searching for jobs.',
            ], 402);
        }

        return null;
    }

    private function publicJobPayload(Job $job): array
    {
        $payload = $job->toArray();
        unset($payload['subcounty'], $payload['parish'], $payload['village']);

        if (isset($payload['employer'])) {
            unset($payload['employer']['phone']);

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

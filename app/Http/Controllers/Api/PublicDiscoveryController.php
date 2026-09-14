<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\JobSeekerProfile;
use App\Models\WorkerOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PublicDiscoveryController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'title' => ['nullable', 'string', 'max:80'],
            'job_category_id' => ['nullable', 'integer', 'exists:job_categories,id'],
            'district' => ['nullable', 'string', 'max:80'],
            'county' => ['nullable', 'string', 'max:80'],
            'salary_min' => ['nullable', 'numeric', 'min:0'],
            'salary_max' => ['nullable', 'numeric', 'min:0'],
            'worker_title' => ['nullable', 'string', 'max:80'],
            'worker_category' => ['nullable', 'string', 'max:80'],
            'worker_district' => ['nullable', 'string', 'max:80'],
            'worker_skill' => ['nullable', 'string', 'max:80'],
            'experience_years' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $search = trim($data['search'] ?? '');
        $limit = (int) ($data['limit'] ?? 6);

        $payload = [
            'jobs' => $this->jobs($search, $limit, $data),
            'job_seekers' => $this->jobSeekers($search, $limit, $data),
        ];

        return response()
            ->json(['data' => $payload])
            ->header('Cache-Control', 'no-store');
    }

    public function thumbnail(JobSeekerProfile $profile)
    {
        abort_unless(
            $profile->isPubliclyVisible()
            && ($profile->profile_photo_thumbnail || $profile->profile_photo),
            404,
        );

        $path = collect([$profile->profile_photo_thumbnail, $profile->profile_photo])
            ->first(fn (?string $path) => $path && Storage::disk('public')->exists($path));

        abort_unless($path, 404);

        return Storage::disk('public')->response(
            $path,
            null,
            ['Cache-Control' => 'public, max-age=86400'],
        );
    }

    public function photo(JobSeekerProfile $profile)
    {
        abort_unless(
            $profile->isPubliclyVisible()
            && ($profile->profile_photo || $profile->profile_photo_thumbnail),
            404,
        );

        $path = collect([$profile->profile_photo, $profile->profile_photo_thumbnail])
            ->first(fn (?string $path) => $path && Storage::disk('public')->exists($path));

        abort_unless($path, 404);

        return Storage::disk('public')->response(
            $path,
            null,
            ['Cache-Control' => 'public, max-age=86400'],
        );
    }

    public function job(int $job)
    {
        $job = Job::query()
            ->with('category:id,name')
            ->publiclyVisible()
            ->whereKey($job)
            ->where(fn ($query) => $query->whereNull('deadline')->orWhereDate('deadline', '>=', today()))
            ->firstOrFail();

        return response()->json(['data' => $job->only([
            'id', 'title', 'positions', 'description', 'requirements', 'responsibilities',
            'location', 'district', 'county', 'subcounty', 'parish', 'village',
            'job_type', 'salary_min', 'salary_max', 'allowances', 'deadline', 'created_at',
        ]) + ['category' => $job->category]]);
    }

    public function storeWorkerContact(Request $request)
    {
        $data = $request->validate([
            'job_seeker_profile_id' => ['required', 'exists:job_seeker_profiles,id'],
            'contact_name' => ['required', 'string', 'max:255'],
            'business_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['required', 'string', 'max:50'],
        ]);

        $worker = JobSeekerProfile::query()
            ->publiclyVisible()
            ->findOrFail($data['job_seeker_profile_id']);

        $order = WorkerOrder::query()->create([
            'job_seeker_profile_id' => $worker->id,
            'contact_name' => $data['contact_name'],
            'business_name' => $data['business_name'] ?? null,
            'contact_phone' => $data['contact_phone'],
            'salary_offered' => 0,
            'job_location' => 'Not provided',
            'working_terms' => 'Public website booking request. Admin should contact this person for full details.',
            'job_description' => 'Public booking request from '.$data['contact_name'].($data['business_name'] ? ' at '.$data['business_name'] : '').'.',
            'start_date' => today(),
            'status' => 'pending',
        ]);

        return response()->json(['message' => 'Booking request submitted. TaziJobs will contact you.', 'data' => $order], 201);
    }

    private function jobs(string $search, int $limit, array $filters = [])
    {
        return Job::query()
            ->select([
                'id', 'job_category_id', 'title', 'location', 'district', 'county',
                'job_type', 'salary_min', 'salary_max', 'deadline', 'created_at',
            ])
            ->with('category:id,name')
            ->publiclyVisible()
            ->where(fn ($query) => $query->whereNull('deadline')->orWhereDate('deadline', '>=', today()))
            ->when($filters['title'] ?? null, fn ($query, string $title) => $query->where('title', 'like', "%{$title}%"))
            ->when($filters['job_category_id'] ?? null, fn ($query, string|int $category) => $query->where('job_category_id', $category))
            ->when($filters['district'] ?? null, function ($query, string $district) {
                $query->where(function ($query) use ($district) {
                    $query->where('district', 'like', "%{$district}%")
                        ->orWhere('location', 'like', "%{$district}%");
                });
            })
            ->when($filters['county'] ?? null, function ($query, string $county) {
                $query->where(function ($query) use ($county) {
                    $query->where('county', 'like', "%{$county}%")
                        ->orWhere('location', 'like', "%{$county}%");
                });
            })
            ->when($filters['salary_min'] ?? null, fn ($query, string $salary) => $query->where(function ($query) use ($salary) {
                $query->whereNull('salary_max')->orWhere('salary_max', '>=', $salary);
            }))
            ->when($filters['salary_max'] ?? null, fn ($query, string $salary) => $query->where(function ($query) use ($salary) {
                $query->whereNull('salary_min')->orWhere('salary_min', '<=', $salary);
            }))
            ->when($search, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhere('district', 'like', "%{$search}%")
                        ->orWhere('county', 'like', "%{$search}%")
                        ->orWhereHas('category', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->limit($limit)
            ->get();
    }

    private function jobSeekers(string $search, int $limit, array $filters = [])
    {
        return JobSeekerProfile::query()
            ->select([
                'id', 'full_name', 'job_title', 'district', 'skills',
                'experience_years', 'profile_photo', 'profile_photo_thumbnail', 'created_at',
            ])
            ->publiclyVisible()
            ->when($filters['worker_title'] ?? null, fn ($query, string $title) => $query->where('job_title', 'like', "%{$title}%"))
            ->when($filters['worker_category'] ?? null, fn ($query, string $category) => $query->where('preferred_job_categories', 'like', "%{$category}%"))
            ->when($filters['worker_district'] ?? null, fn ($query, string $district) => $query->where('district', 'like', "%{$district}%"))
            ->when($filters['worker_skill'] ?? null, fn ($query, string $skill) => $query->where('skills', 'like', "%{$skill}%"))
            ->when($filters['experience_years'] ?? null, fn ($query, int $years) => $query->where('experience_years', '>=', $years))
            ->when($search, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('job_title', 'like', "%{$search}%")
                        ->orWhere('district', 'like', "%{$search}%")
                        ->orWhere('skills', 'like', "%{$search}%")
                        ->orWhere('preferred_job_categories', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (JobSeekerProfile $profile) => [
                'id' => $profile->id,
                'display_name' => $this->maskName($profile->full_name),
                'job_title' => $profile->job_title,
                'district' => $profile->district,
                'skills' => array_slice($profile->skills ?? [], 0, 3),
                'experience_years' => $profile->experience_years,
                'thumbnail_url' => ($profile->profile_photo_thumbnail || $profile->profile_photo)
                    ? route('public.job-seeker-thumbnail', $profile)
                    : null,
                'photo_url' => ($profile->profile_photo || $profile->profile_photo_thumbnail)
                    ? route('public.job-seeker-photo', $profile)
                    : null,
            ]);
    }

    private function maskName(?string $name): string
    {
        $parts = preg_split('/\s+/', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY);

        return $parts
            ? collect($parts)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)).'.')->join(' ')
            : 'Verified job seeker';
    }
}

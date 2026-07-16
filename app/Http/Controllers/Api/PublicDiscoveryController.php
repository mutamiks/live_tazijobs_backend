<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\JobSeekerProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PublicDiscoveryController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
        ]);
        $search = trim($data['search'] ?? '');

        $payload = [
            'jobs' => $this->jobs($search),
            'job_seekers' => $this->jobSeekers($search),
        ];

        return response()
            ->json(['data' => $payload])
            ->header('Cache-Control', 'no-store');
    }

    public function thumbnail(JobSeekerProfile $profile)
    {
        abort_unless(
            $profile->isPubliclyVisible()
            && $profile->profile_photo_thumbnail
            && Storage::disk('public')->exists($profile->profile_photo_thumbnail),
            404,
        );

        return Storage::disk('public')->response(
            $profile->profile_photo_thumbnail,
            null,
            ['Cache-Control' => 'public, max-age=86400'],
        );
    }

    private function jobs(string $search)
    {
        return Job::query()
            ->select([
                'id', 'job_category_id', 'title', 'location', 'district',
                'job_type', 'salary_min', 'salary_max', 'deadline', 'created_at',
            ])
            ->with('category:id,name')
            ->publiclyVisible()
            ->where(fn ($query) => $query->whereNull('deadline')->orWhereDate('deadline', '>=', today()))
            ->when($search, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhere('district', 'like', "%{$search}%")
                        ->orWhereHas('category', fn ($query) => $query->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->limit(6)
            ->get();
    }

    private function jobSeekers(string $search)
    {
        return JobSeekerProfile::query()
            ->select([
                'id', 'full_name', 'job_title', 'district', 'skills',
                'experience_years', 'profile_photo_thumbnail', 'created_at',
            ])
            ->publiclyVisible()
            ->when($search, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('job_title', 'like', "%{$search}%")
                        ->orWhere('district', 'like', "%{$search}%")
                        ->orWhere('skills', 'like', "%{$search}%")
                        ->orWhere('preferred_job_categories', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (JobSeekerProfile $profile) => [
                'id' => $profile->id,
                'display_name' => $this->maskName($profile->full_name),
                'job_title' => $profile->job_title,
                'district' => $profile->district,
                'skills' => array_slice($profile->skills ?? [], 0, 3),
                'experience_years' => $profile->experience_years,
                'thumbnail_url' => $profile->profile_photo_thumbnail
                    ? route('public.job-seeker-thumbnail', $profile)
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

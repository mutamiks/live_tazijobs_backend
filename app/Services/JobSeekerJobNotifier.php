<?php

namespace App\Services;

use App\Models\Job;
use App\Models\JobSeekerProfile;
use App\Models\Notification;
use Illuminate\Support\Str;
use Throwable;

class JobSeekerJobNotifier
{
    public function __construct(private readonly SmsService $sms) {}

    public function notifyForApprovedJob(Job $job): int
    {
        $job->loadMissing('category');

        $category = $job->category?->name;
        if (blank($category)) {
            return 0;
        }

        $message = trim("New {$category} job on TaziJobs: {$job->title}".($job->district ? " in {$job->district}" : '').'. Log in to Book / Apply.');
        $smsMessage = 'A new job has been posted. Log in and go to Alerts to view the details.';
        $count = 0;

        JobSeekerProfile::query()
            ->with('user')
            ->where('status', 'approved')
            ->whereHas('user', fn ($query) => $query->where('role', 'job_seeker')->where('status', 'approved'))
            ->orderBy('id')
            ->chunkById(100, function ($profiles) use ($category, $job, $message, $smsMessage, &$count) {
                foreach ($profiles as $profile) {
                    if (! in_array($category, $profile->preferred_job_categories ?? [], true)) {
                        continue;
                    }

                    Notification::query()->create([
                        'user_id' => $profile->user_id,
                        'title' => 'New job in your category',
                        'message' => $message,
                        'type' => 'new_category_job',
                    ]);

                    $phone = $profile->user?->phone ?: $profile->phone;
                    if (filled($phone)) {
                        try {
                            $this->sms->send($phone, $smsMessage);
                        } catch (Throwable $exception) {
                            report($exception);
                        }
                    }

                    $count++;
                }
            });

        return $count;
    }
}

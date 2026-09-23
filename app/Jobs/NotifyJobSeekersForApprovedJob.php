<?php

namespace App\Jobs;

use App\Models\Job;
use App\Services\JobSeekerJobNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyJobSeekersForApprovedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $jobId) {}

    public function handle(JobSeekerJobNotifier $notifier): void
    {
        $job = Job::query()->findOrFail($this->jobId);

        $notifier->notifyForApprovedJob($job);
    }
}

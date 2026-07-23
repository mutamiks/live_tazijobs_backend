<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJobApplicationRequest;
use App\Http\Requests\UpdateApplicationStatusRequest;
use App\Models\Job;
use App\Models\JobApplication;
use App\Support\NotifiesUsers;
use Illuminate\Http\Request;

class JobApplicationController extends Controller
{
    use NotifiesUsers;

    public function apply(StoreJobApplicationRequest $request, Job $job)
    {
        if (! $job->isPubliclyVisible()) {
            return response()->json(['message' => 'This job is not open for applications.'], 422);
        }

        if (! $request->user()->jobSeekerProfile()->where('status', 'approved')->exists()) {
            return response()->json(['message' => 'Job seeker profile must be approved before applying.'], 422);
        }

        $data = $request->validated();
        $data['cv_file'] = $request->file('cv_file')?->store('application-cvs', 'public') ?? ($data['cv_file'] ?? null);

        $application = JobApplication::query()->create($data + [
            'job_id' => $job->id,
            'job_seeker_id' => $request->user()->id,
            'status' => 'submitted',
            'approval_status' => 'pending',
        ]);

        $this->notifyUser($request->user(), 'job_application_pending', 'Application submitted for review', "Your application for {$job->title} is pending admin approval.");

        return response()->json(['message' => 'Application submitted for admin approval.', 'data' => $application], 201);
    }

    public function employerApplications(Request $request)
    {
        $applications = JobApplication::query()
            ->with(['job', 'jobSeeker.jobSeekerProfile'])
            ->where('approval_status', 'approved')
            ->whereHas('job', fn ($query) => $query->where('employer_id', $request->user()->id))
            ->latest()
            ->paginate(15);

        return response()->json(['data' => $applications]);
    }

    public function myApplications(Request $request)
    {
        $applications = JobApplication::query()
            ->with('job.employer.employerProfile')
            ->where('job_seeker_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return response()->json(['data' => $applications]);
    }

    public function updateStatus(UpdateApplicationStatusRequest $request, JobApplication $application)
    {
        if ($application->job->employer_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($application->approval_status !== 'approved') {
            return response()->json(['message' => 'Application must be approved by admin first.'], 422);
        }

        $application->update($request->validated());

        if ($application->status === 'hired') {
            $application->jobSeeker
                ->activeJobSeekerSubscription()
                ->update(['status' => 'completed', 'completed_at' => now()]);
        }

        $this->notifyUser(
            $application->jobSeeker,
            'application_status',
            'Application status updated',
            "Your application for {$application->job->title} is now {$application->status}."
        );

        return response()->json(['message' => 'Application status updated.', 'data' => $application->fresh(['job', 'jobSeeker'])]);
    }
}

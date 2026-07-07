<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkerOrderRequest;
use App\Models\JobSeekerProfile;
use App\Models\WorkerOrder;
use App\Support\NotifiesUsers;
use Illuminate\Http\Request;

class WorkerController extends Controller
{
    use NotifiesUsers;

    public function index(Request $request)
    {
        $workers = JobSeekerProfile::query()
            ->with('user')
            ->where('status', 'approved')
            ->where('is_available', true)
            ->when($request->query('search'), function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('full_name', 'like', "%{$search}%")
                        ->orWhere('bio', 'like', "%{$search}%")
                        ->orWhere('work_experience', 'like', "%{$search}%")
                        ->orWhere('cv_file', 'like', "%{$search}%")
                        ->orWhere('skills', 'like', "%{$search}%");
                });
            })
            ->when($request->query('district'), fn ($query, string $district) => $query->where('district', 'like', "%{$district}%"))
            ->when($request->query('county'), fn ($query, string $county) => $query->where('county', 'like', "%{$county}%"))
            ->when($request->query('subcounty'), fn ($query, string $subcounty) => $query->where('subcounty', 'like', "%{$subcounty}%"))
            ->when($request->query('parish'), fn ($query, string $parish) => $query->where('parish', 'like', "%{$parish}%"))
            ->when($request->query('village'), fn ($query, string $village) => $query->where('village', 'like', "%{$village}%"))
            ->when($request->query('religion'), fn ($query, string $religion) => $query->where('religion', 'like', "%{$religion}%"))
            ->when($request->query('language'), fn ($query, string $language) => $query->where('languages', 'like', "%{$language}%"))
            ->when($request->query('job_category'), fn ($query, string $category) => $query->where('preferred_job_categories', 'like', "%{$category}%"))
            ->when($request->query('experience_years'), fn ($query, string $years) => $query->where('experience_years', '>=', $years))
            ->latest()
            ->paginate(20);

        return response()->json(['data' => $workers]);
    }

    public function show(JobSeekerProfile $worker)
    {
        if ($worker->status !== 'approved' || ! $worker->is_available) {
            return response()->json(['message' => 'Worker not found.'], 404);
        }

        return response()->json(['data' => $worker->load('user')]);
    }

    public function storeOrder(StoreWorkerOrderRequest $request)
    {
        $worker = JobSeekerProfile::query()
            ->where('status', 'approved')
            ->where('is_available', true)
            ->findOrFail($request->validated('job_seeker_profile_id'));

        $order = WorkerOrder::query()->create($request->validated() + [
            'employer_id' => $request->user()->id,
            'status' => 'pending',
        ]);

        $this->notifyUser(
            $worker->user,
            'worker_request',
            'New worker request submitted',
            "{$request->user()->name} requested to match with you. Admin review is pending."
        );

        return response()->json(['message' => 'Worker request submitted for admin review.', 'data' => $order], 201);
    }

    public function orders(Request $request)
    {
        $orders = WorkerOrder::query()
            ->with('worker.user')
            ->where('employer_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json(['data' => $orders]);
    }
}

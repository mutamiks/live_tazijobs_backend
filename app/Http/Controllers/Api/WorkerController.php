<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkerOrderRequest;
use App\Models\JobSeekerProfile;
use App\Models\WorkerOrder;
use App\Services\AdminApprovalNotifier;
use App\Support\NotifiesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkerController extends Controller
{
    use NotifiesUsers;

    public function __construct(private readonly AdminApprovalNotifier $adminApprovalNotifier) {}

    public function index(Request $request)
    {
        $workers = JobSeekerProfile::query()
            ->with('user')
            ->publiclyVisible()
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
        if (! $worker->isPubliclyVisible()) {
            return response()->json(['message' => 'Worker not found.'], 404);
        }

        return response()->json(['data' => $worker->load('user')]);
    }

    public function storeOrder(StoreWorkerOrderRequest $request)
    {
        $data = $request->validated();
        $workerIds = $data['job_seeker_profile_ids'] ?? [$data['job_seeker_profile_id']];
        $workers = JobSeekerProfile::query()->with('user')->publiclyVisible()->whereIn('id', $workerIds)->get();

        if ($workers->count() !== count($workerIds)) {
            return response()->json(['message' => 'One or more selected workers are no longer available.'], 422);
        }

        $duplicate = WorkerOrder::query()
            ->where('employer_id', $request->user()->id)
            ->whereIn('job_seeker_profile_id', $workerIds)
            ->where('status', 'pending')
            ->exists();
        if ($duplicate) {
            return response()->json(['message' => 'One or more selected workers already have a pending request.'], 422);
        }

        $orders = DB::transaction(function () use ($data, $workerIds, $request) {
            return collect($workerIds)->map(fn ($workerId) => WorkerOrder::query()->create([
                'employer_id' => $request->user()->id,
                'job_seeker_profile_id' => $workerId,
                'salary_offered' => $data['salary_offered'],
                'job_location' => $data['job_location'],
                'working_terms' => $data['working_terms'],
                'allowances' => $data['allowances'] ?? null,
                'job_description' => $data['job_description'],
                'start_date' => $data['start_date'],
                'status' => 'pending',
            ]));
        });

        foreach ($workers as $worker) {
            $this->notifyUser($worker->user, 'worker_request', 'New worker request submitted', "{$request->user()->name} requested to match with you. Admin review is pending.");
        }

        $this->adminApprovalNotifier->notifyAdmins(
            'New worker order awaiting approval',
            "A worker order request from {$request->user()->name} is awaiting administrative approval."
        );

        return response()->json(['message' => count($workerIds) === 1 ? 'Worker request submitted for admin review.' : count($workerIds).' worker requests submitted for admin review.', 'data' => $orders], 201);
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

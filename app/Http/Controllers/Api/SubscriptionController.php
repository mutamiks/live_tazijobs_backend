<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubscribeRequest;
use App\Models\JobSeekerSubscription;
use App\Models\SubscriptionPackage;
use App\Models\SubscriptionPayment;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function packages()
    {
        $packages = SubscriptionPackage::query()
            ->where('is_active', true)
            ->orderBy('price')
            ->get();

        return response()->json(['data' => $packages]);
    }

    public function current(Request $request)
    {
        return response()->json([
            'data' => $request->user()
                ->activeJobSeekerSubscription()
                ->with('package')
                ->first(),
        ]);
    }

    public function subscribe(SubscribeRequest $request)
    {
        $package = SubscriptionPackage::query()
            ->where('is_active', true)
            ->findOrFail($request->validated('subscription_package_id'));

        $activeSubscription = $request->user()->activeJobSeekerSubscription()->first();

        if ($activeSubscription) {
            return response()->json(['message' => 'You already have an active subscription. Upgrade it by topping up instead.'], 422);
        }

        $subscription = JobSeekerSubscription::query()->create([
            'user_id' => $request->user()->id,
            'subscription_package_id' => $package->id,
            'amount_paid' => $package->price,
            'job_chance_limit' => $package->job_chance_limit,
            'priority_level' => $package->priority_level,
            'status' => 'active',
            'started_at' => now(),
        ]);

        SubscriptionPayment::query()->create([
            'user_id' => $request->user()->id,
            'subscription_package_id' => $package->id,
            'job_seeker_subscription_id' => $subscription->id,
            'amount' => $package->price,
            'type' => 'initial',
            'status' => 'confirmed',
        ]);

        return response()->json([
            'message' => 'Subscription package selected.',
            'data' => $subscription->load('package'),
        ], 201);
    }

    public function upgrade(SubscribeRequest $request)
    {
        $subscription = $request->user()->activeJobSeekerSubscription()->first();

        if (! $subscription) {
            return response()->json(['message' => 'Subscribe to a package before upgrading.'], 422);
        }

        $package = SubscriptionPackage::query()
            ->where('is_active', true)
            ->findOrFail($request->validated('subscription_package_id'));

        if ($package->price <= $subscription->amount_paid) {
            return response()->json(['message' => 'Choose a higher package to upgrade.'], 422);
        }

        $topUpAmount = $package->price - $subscription->amount_paid;

        $subscription->forceFill([
            'subscription_package_id' => $package->id,
            'amount_paid' => $package->price,
            'job_chance_limit' => max($subscription->job_chance_limit, $package->job_chance_limit),
            'priority_level' => max($subscription->priority_level, $package->priority_level),
        ])->save();

        SubscriptionPayment::query()->create([
            'user_id' => $request->user()->id,
            'subscription_package_id' => $package->id,
            'job_seeker_subscription_id' => $subscription->id,
            'amount' => $topUpAmount,
            'type' => 'top_up',
            'status' => 'confirmed',
        ]);

        return response()->json([
            'message' => 'Subscription upgraded.',
            'data' => $subscription->fresh('package'),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubscribeRequest;
use App\Models\JobSeekerSubscription;
use App\Models\SubscriptionPackage;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\SmsService;
use App\Services\SubscriptionPaymentProcessor;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SmsService $sms,
        private readonly SubscriptionPaymentProcessor $processor,
    ) {}

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

    public function invoices(Request $request)
    {
        $invoices = SubscriptionPayment::query()
            ->with(['package', 'job.employer.employerProfile'])
            ->where('user_id', $request->user()->id)
            ->whereNotNull('invoice_number')
            ->latest()
            ->paginate(20);

        return response()->json(['data' => $invoices]);
    }

    public function subscribe(SubscribeRequest $request)
    {
        $data = $request->validated();
        $package = SubscriptionPackage::query()
            ->where('is_active', true)
            ->findOrFail($data['subscription_package_id']);

        $activeSubscription = $request->user()->activeJobSeekerSubscription()->first();

        if ($activeSubscription) {
            return response()->json(['message' => 'You already have an active subscription. Upgrade it by topping up instead.'], 422);
        }

        return $this->requestPayment($request, $package, (float) $package->price, 'initial');
    }

    public function upgrade(SubscribeRequest $request)
    {
        $data = $request->validated();
        $subscription = $request->user()->activeJobSeekerSubscription()->first();

        if (! $subscription) {
            return response()->json(['message' => 'Subscribe to a package before upgrading.'], 422);
        }

        $package = SubscriptionPackage::query()
            ->where('is_active', true)
            ->findOrFail($data['subscription_package_id']);

        if ($package->price <= $subscription->amount_paid) {
            return response()->json(['message' => 'Choose a higher package to upgrade.'], 422);
        }

        $topUpAmount = $package->price - $subscription->amount_paid;

        return $this->requestPayment($request, $package, (float) $topUpAmount, 'top_up', $subscription);
    }

    public function refreshPayment(Request $request, SubscriptionPayment $payment)
    {
        abort_if($payment->user_id !== $request->user()->id, 403);
        abort_if(blank($payment->transaction_reference), 422, 'This payment has no provider reference.');

        try {
            $payment = $this->processor->refresh($payment);
            $message = $payment->status === 'confirmed'
                ? 'Subscription payment confirmed.'
                : 'Payment status refreshed.';

            return response()->json(['message' => $message, 'data' => $payment]);
        } catch (Throwable $exception) {
            $payment->refresh();
            if ($payment->processing_attempts > 3) {
                $payment->update(['status' => 'failed', 'status_message' => $exception->getMessage()]);
            }

            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function payInvoice(Request $request, SubscriptionPayment $payment)
    {
        abort_if($payment->user_id !== $request->user()->id, 403);
        abort_if(blank($payment->invoice_number), 404, 'Invoice not found.');

        $data = $request->validate([
            'phone' => ['required', 'regex:/^(?:\+?256|0)?7\d{8}$/'],
        ]);

        if ($payment->status === 'confirmed') {
            return response()->json(['message' => 'This invoice is already paid.', 'data' => $payment->load(['package', 'job'])]);
        }

        $phone = $this->sms->normalizePhone($data['phone']);

        try {
            $provider = $this->sms->requestPayment((float) $payment->amount, $phone, 'Invoice Payment');
            if (strtoupper($provider['Status'] ?? '') === 'ERROR') {
                throw ValidationException::withMessages(['payment' => $this->sms->providerMessage($provider)]);
            }

            $payment->update([
                'phone' => $phone,
                'status' => 'pending',
                'transaction_reference' => $provider['TransactionReference'] ?? null,
                'status_message' => $provider['StatusMessage'] ?? 'Awaiting confirmation on the phone.',
                'processing_attempts' => 0,
                'last_checked_at' => null,
            ]);

            return response()->json([
                'message' => 'Payment request sent. Confirm it on the phone.',
                'data' => $payment->fresh(['package', 'job']),
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    private function requestPayment(
        Request $request,
        SubscriptionPackage $package,
        float $amount,
        string $type,
        ?JobSeekerSubscription $subscription = null,
    ) {
        $phone = $this->sms->normalizePhone($request->input('phone'));

        try {
            $provider = $this->sms->requestPayment($amount, $phone, 'Subscription Payment');
            if (strtoupper($provider['Status'] ?? '') === 'ERROR') {
                throw ValidationException::withMessages(['payment' => $this->sms->providerMessage($provider)]);
            }

            $payment = SubscriptionPayment::query()->create([
                'user_id' => $request->user()->id,
                'subscription_package_id' => $package->id,
                'job_seeker_subscription_id' => $subscription?->id,
                'amount' => $amount,
                'phone' => $phone,
                'type' => $type,
                'status' => 'pending',
                'transaction_reference' => $provider['TransactionReference'] ?? null,
                'status_message' => $provider['StatusMessage'] ?? 'Awaiting confirmation on the phone.',
            ]);

            return response()->json([
                'message' => 'Payment request sent. Confirm it on the phone.',
                'data' => $payment->load(['package', 'subscription.package']),
            ], 201);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
     /**
     * Display a listing of all subscription payment records for Admin panels.
     */
    public function adminIndex(Request $request)
    {
        // 🔒 Security Guard Check: Enforce that only authentic admins can log or view records
        if (!$request->user() || $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $perPage = (int) $request->integer('per_page', 50);
        $perPage = max(1, min($perPage, 100));

        $payments = SubscriptionPayment::query()
            ->with(['package', 'user'])
            ->latest()
            ->paginate($perPage);

        return response()->json(['data' => $payments]);
    }

    /**
     * Process manual inputs from the Admin Panel to log Cash or Bank transfer transaction items.
     */
    public function storeManualPayment(Request $request)
    {
        //  Security Guard Check: Enforce that only authentic admins can run this task
        if (!$request->user() || $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'subscription_package_id' => ['required', 'exists:subscription_packages,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'payment_method' => ['required', 'in:bank_transfer,cash_payment'], 
            'payment_date' => ['required', 'date'],
            'payment_reason' => ['required', 'string', 'max:1000'],
        ]);

        // Step A: Create the verified subscription transaction log row instance
        $payment = SubscriptionPayment::query()->create($validated + [
            'transaction_reference' => 'OFF-'.str()->upper(str()->random(12)),
            'type' => 'initial', // Maps workflow type flags
            'status' => 'confirmed', // Offline cash/bank deposits are instantly verified by the admin
            'status_message' => 'Manually recorded and approved by admin panel audit.',
        ]);

        //  Step B: Invoke the workflow engine loop to activate user subscription days immediately
        $this->activateOfflineSubscription($payment);

        return response()->json([
            'message' => 'Offline entry logged successfully, package access is now live.',
            'data' => $payment->load(['package', 'user'])
        ], 201);
    }

    public function storeAdminMobileMoneyPayment(Request $request)
    {
        if (!$request->user() || $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'subscription_package_id' => ['required', 'exists:subscription_packages,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'phone' => ['required', 'regex:/^(?:\+?256|0)?7\d{8}$/'],
        ]);

        $user = User::query()->where('role', 'job_seeker')->findOrFail($validated['user_id']);
        $package = SubscriptionPackage::query()->where('is_active', true)->findOrFail($validated['subscription_package_id']);
        $phone = $this->sms->normalizePhone($validated['phone']);

        try {
            $provider = $this->sms->requestPayment((float) $validated['amount'], $phone, 'Subscription Payment');
            if (strtoupper($provider['Status'] ?? '') === 'ERROR') {
                throw ValidationException::withMessages(['payment' => $this->sms->providerMessage($provider)]);
            }

            $payment = SubscriptionPayment::query()->create([
                'user_id' => $user->id,
                'subscription_package_id' => $package->id,
                'amount' => $validated['amount'],
                'phone' => $phone,
                'payment_method' => 'mobile_money_API',
                'payment_date' => now()->toDateString(),
                'type' => 'initial',
                'status' => 'pending',
                'transaction_reference' => $provider['TransactionReference'] ?? null,
                'status_message' => $provider['StatusMessage'] ?? 'Awaiting confirmation on the phone.',
            ]);

            return response()->json([
                'message' => 'Mobile money payment request sent. Confirm it on the phone.',
                'data' => $payment->load(['package', 'user']),
            ], 201);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    /**
     * Private internal workflow engine logic that mirrors the mobile money processor behavior
     * to immediately provisions user profiles with subscription package access limits.
     */
    private function activateOfflineSubscription(SubscriptionPayment $payment): void
    {
        $package = $payment->package;
        $user = $payment->user;

        // Fetch duration days fallback parameters from package configuration definitions 
        $durationDays = $package->duration_days ?? 30;

        // Create or renew a package subscription record tracking duration ranges
        $subscription = JobSeekerSubscription::query()->create([
            'user_id' => $user->id,
            'subscription_package_id' => $package->id,
            'amount_paid' => $payment->amount,
            'status' => 'active',
            'starts_at' => now(),
            'expires_at' => now()->addDays($durationDays),
        ]);

        // Update the transaction model tracking association constraints
        $payment->update([
            'job_seeker_subscription_id' => $subscription->id
        ]);
    }
}

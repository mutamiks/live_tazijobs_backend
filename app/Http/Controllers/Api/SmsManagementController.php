<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SmsPayment;
use App\Models\SmsTopup;
use App\Services\SmsService;
use App\Services\SmsPaymentProcessor;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class SmsManagementController extends Controller
{
    public function __construct(
        private readonly SmsService $sms,
        private readonly SmsPaymentProcessor $processor,
    ) {}

    public function balance()
    {
        try {
            return response()->json(['data' => ['balance' => $this->sms->balance()]]);
        } catch (Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function rate()
    {
        return response()->json(['data' => ['rate' => (float) config('sms.rate'), 'enabled' => (bool) config('sms.enabled')]]);
    }

    public function storePayment(Request $request)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1000'],
            'phone' => ['required', 'regex:/^2567\d{8}$/'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $provider = $this->sms->requestPayment((float) $data['amount'], $data['phone']);
            if (strtoupper($provider['Status'] ?? '') === 'ERROR') {
                throw ValidationException::withMessages(['payment' => $provider['StatusMessage'] ?? 'Payment request failed.']);
            }
            $payment = SmsPayment::query()->create([
                ...$data,
                'user_id' => $request->user()->id,
                'transaction_reference' => $provider['TransactionReference'] ?? null,
                'status' => 'pending',
                'status_message' => $provider['StatusMessage'] ?? 'Awaiting confirmation on the phone.',
            ]);

            return response()->json(['message' => 'Payment request sent. Confirm it on the phone.', 'data' => $payment], 201);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function payments(Request $request)
    {
        $query = SmsPayment::query()->with(['user:id,name,email', 'topup'])->latest();
        $this->dates($request, $query);
        return response()->json(['data' => $query->paginate(25)]);
    }

    public function refreshPayment(SmsPayment $payment)
    {
        abort_if(blank($payment->transaction_reference), 422, 'This payment has no provider reference.');
        try {
            $payment = $this->processor->refresh($payment);
            return response()->json(['message' => 'Payment status refreshed.', 'data' => $payment->load(['user', 'topup'])]);
        } catch (Throwable $exception) {
            $payment->refresh();
            if ($payment->processing_attempts > 3) {
                $payment->update(['status' => 'failed', 'status_message' => $exception->getMessage()]);
            }
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function distribute(Request $request, SmsPayment $payment)
    {
        try {
            $topup = $this->processor->distribute($payment, $request->user()->id);
            return response()->json(['message' => "{$topup->sms_credits} SMS credits added.", 'data' => $topup]);
        } catch (Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function topups(Request $request)
    {
        $query = SmsTopup::query()->with(['user:id,name,email', 'payment:id,phone,transaction_reference'])->latest();
        $this->dates($request, $query);
        return response()->json(['data' => $query->paginate(25)]);
    }

    private function dates(Request $request, $query): void
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $query->when($request->date('from'), fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($request->date('to'), fn ($query, $date) => $query->whereDate('created_at', '<=', $date));
    }
}

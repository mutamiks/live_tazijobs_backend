<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Services\SmsVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private readonly SmsVerificationService $verification) {}

    public function register(RegisterRequest $request)
    {
        $data = collect($request->validated())->except('terms_accepted')->all();
        $user = User::query()->create($data + ['status' => 'pending']);
        try {
            $this->verification->send($user->phone, 'phone_verification');
        } catch (\Throwable $exception) {
            report($exception);
            $user->delete();

            throw ValidationException::withMessages([
                'phone' => 'We could not send the verification code. Check the phone number and try again.',
            ]);
        }

        return response()->json([
            'message' => 'Verification code sent. Verify your phone number to complete signup.',
            'data' => [
                'user' => $user,
                'token' => $user->createToken('tazijobapp-api')->plainTextToken,
            ],
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        $login = $request->validated('login') ?? $request->validated('email');
        $phoneFormats = [];
        if (preg_match('/^[+0-9\s().-]+$/', (string) $login)) {
            $normalizedPhone = app(\App\Services\SmsService::class)->normalizePhone($login);
            $phoneFormats = array_values(array_unique([
                $login,
                $normalizedPhone,
                '+'.$normalizedPhone,
                '0'.substr($normalizedPhone, 3),
            ]));
        }

        $user = User::query()
            ->where(function ($query) use ($login, $phoneFormats) {
                $query->where('email', $login);
                if ($phoneFormats !== []) {
                    $query->orWhereIn('phone', $phoneFormats);
                }
            })
            ->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages(['email' => 'Invalid credentials.']);
        }

        if ($user->status === 'suspended') {
            return response()->json(['message' => 'This account has been suspended.'], 403);
        }

        return response()->json([
            'message' => 'Login successful.',
            'data' => [
                'user' => $user->load(['jobSeekerProfile', 'employerProfile', 'activeJobSeekerSubscription.package']),
                'token' => $user->createToken('tazijobapp-api')->plainTextToken,
            ],
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'data' => $request->user()->load(['jobSeekerProfile', 'employerProfile', 'activeJobSeekerSubscription.package']),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function forgotPassword(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink($data);

        return response()->json(['message' => 'If that email is registered, a password reset link has been sent.']);
    }

    public function sendPhoneVerification(Request $request)
    {
        abort_if($request->user()->phone_verified_at, 422, 'Phone number is already verified.');
        $this->verification->send($request->user()->phone, 'phone_verification');
        return response()->json(['message' => 'A verification code has been sent by SMS.']);
    }

    public function verifyPhone(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $this->verification->verify($request->user()->phone, 'phone_verification', $data['code']);
        $request->user()->forceFill(['phone_verified_at' => now()])->save();
        return response()->json(['message' => 'Phone number verified.', 'data' => $request->user()->fresh()]);
    }

    public function forgotPasswordSms(Request $request)
    {
        $data = $request->validate(['phone' => ['required', 'regex:/^(?:\+256|256|0)?7\d{8}$/']]);
        $normalized = app(\App\Services\SmsService::class)->normalizePhone($data['phone']);
        $local = '0'.substr($normalized, 3);
        $user = User::query()->whereIn('phone', [$data['phone'], $normalized, '+'.$normalized, $local])->first();
        if ($user) {
            $this->verification->send($user->phone, 'password_reset');
        }
        return response()->json(['message' => 'If that number is registered, a reset code has been sent.']);
    }

    public function resetPasswordSms(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'regex:/^(?:\+256|256|0)?7\d{8}$/'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $normalized = app(\App\Services\SmsService::class)->normalizePhone($data['phone']);
        $local = '0'.substr($normalized, 3);
        $user = User::query()->whereIn('phone', [$data['phone'], $normalized, '+'.$normalized, $local])->first();
        if (! $user) {
            throw ValidationException::withMessages(['phone' => 'The phone number or code is invalid.']);
        }
        $this->verification->verify($user->phone, 'password_reset', $data['code']);
        $user->forceFill(['password' => Hash::make($data['password']), 'remember_token' => Str::random(60)])->save();
        $user->tokens()->delete();
        return response()->json(['message' => 'Password reset successfully. You can now sign in.']);
    }

    public function sendReactivationCode(Request $request)
    {
        $data = $request->validate(['phone' => ['required', 'regex:/^(?:\+256|256|0)?7\d{8}$/']]);
        $normalized = app(\App\Services\SmsService::class)->normalizePhone($data['phone']);
        $local = '0'.substr($normalized, 3);
        $user = User::query()->whereIn('phone', [$data['phone'], $normalized, '+'.$normalized, $local])->first();

        if ($user && $user->status === 'suspended') {
            $this->verification->send($user->phone, 'account_reactivation');
        }

        return response()->json(['message' => 'If that suspended account exists, a reactivation code has been sent.']);
    }

    public function reactivateAccount(Request $request)
    {
        $data = $request->validate([
            'phone' => ['required', 'regex:/^(?:\+256|256|0)?7\d{8}$/'],
            'code' => ['required', 'digits:6'],
        ]);
        $normalized = app(\App\Services\SmsService::class)->normalizePhone($data['phone']);
        $local = '0'.substr($normalized, 3);
        $user = User::query()->whereIn('phone', [$data['phone'], $normalized, '+'.$normalized, $local])->first();

        if (! $user || $user->status !== 'suspended') {
            throw ValidationException::withMessages(['phone' => 'The phone number or code is invalid.']);
        }

        $this->verification->verify($user->phone, 'account_reactivation', $data['code']);
        $user->forceFill(['status' => 'approved'])->save();

        return response()->json(['message' => 'Account reactivated. You can now sign in.']);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();
            $user->tokens()->delete();
        });

        if ($status !== Password::PasswordReset) {
            throw ValidationException::withMessages(['email' => __($status)]);
        }

        return response()->json(['message' => 'Your password has been reset. You can now sign in.']);
    }
}

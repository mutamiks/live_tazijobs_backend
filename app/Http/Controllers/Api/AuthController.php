<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        $data = collect($request->validated())->except('terms_accepted')->all();
        $user = User::query()->create($data + ['status' => 'pending']);

        return response()->json([
            'message' => 'Registration successful.',
            'data' => [
                'user' => $user,
                'token' => $user->createToken('tazijobapp-api')->plainTextToken,
            ],
        ], 201);
    }

    public function login(LoginRequest $request)
    {
        $login = $request->validated('login') ?? $request->validated('email');
        $user = User::query()
            ->where('email', $login)
            ->orWhere('phone', $login)
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
}

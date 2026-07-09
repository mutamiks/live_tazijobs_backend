<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePhoneVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->phone_verified_at) {
            return response()->json(['message' => 'Verify your phone number before continuing.'], 403);
        }

        return $next($request);
    }
}

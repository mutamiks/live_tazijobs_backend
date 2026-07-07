<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmployerApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->employerProfile()->where('status', 'approved')->exists()) {
            return response()->json([
                'message' => 'Employer profile must be approved before accessing worker information.',
            ], 403);
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureRequestIsIdempotent
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('X-Idempotency-Key');

        if (!$key) {
            return $this->reject($request, __('Idempotency key is required.'));
        }

        $cacheKey = "idempotency_key:{$key}";

        if (!Cache::add($cacheKey, 'processing', now()->addMinutes(5))) {

            return $this->reject($request, __('Request already processed or processing.'));
        }

        try {
            $response = $next($request);

            if ($response->isSuccessful() || $response->isRedirection()) {
                Cache::put($cacheKey, 'completed', now()->addMinutes(5));
            } else {
                Cache::forget($cacheKey);
            }

            return $response;
        } catch (\Throwable $exception) {
            Cache::forget($cacheKey);

            throw $exception;
        }
    }

    private function reject(Request $request, string $message): Response
    {
        if ($request->inertia()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

            return back();
        }

        return response()->json(['error' => $message], 422);
    }
}

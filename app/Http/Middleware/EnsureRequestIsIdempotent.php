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

        if (! $key) {
            return $this->reject($request, __('Idempotency key is required.'));
        }

        //  key validation is uuidv4
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $key)) {
            return $this->reject($request, __('Invalid idempotency key.'));
        }

        $cacheKey = "idempotency_key:{$key}";

        if (! Cache::add($cacheKey, 'processing', now()->addMinutes(5))) {
            $cached = Cache::get($cacheKey);

            if ($cached === 'processing') {
                return $this->reject($request, __('Request already processing.'), Response::HTTP_CONFLICT); // 409 przy jednoczesnym przetwarzaniu
            }

            if (\is_array($cached) && isset($cached['request_body_hash'])) {
                $body_hash = $this->hashRequestBody($request);

                // Zabezpieczenie czasowe przed timing attacks na hashu (dobry nawyk)
                if (hash_equals($cached['request_body_hash'], $body_hash)) {
                    return response($cached['response_body'], $cached['status_code'], $cached['response_headers']);
                }

                // Wykryto konflikt payloadu dla tego samego klucza
                return $this->reject($request, __('Idempotency key conflict.'), Response::HTTP_CONFLICT); // 409 Conflict
            }

            return $this->reject($request, __('Request already processed.'));
        }

        try {
            $response = $next($request);

            if ($response->isSuccessful() || $response->isRedirection()) {
                $cached = [
                    'request_body_hash' => $this->hashRequestBody($request),
                    'status_code' => $response->getStatusCode(),
                    'response_body' => $response->getContent(),
                    'response_headers' => $response->headers->all(),
                ];
                Cache::put($cacheKey, $cached, now()->addMinutes(5));
            } else {
                Cache::forget($cacheKey);
            }

            return $response;
        } catch (\Throwable $exception) {
            Cache::forget($cacheKey);

            throw $exception;
        }
    }

    private function hashRequestBody(Request $request): string
    {
        return hash('sha256', is_string($request->all()) ? $request->all() : json_encode($request->all()));
    }

    private function reject(Request $request, string $message, int $status = Response::HTTP_UNPROCESSABLE_ENTITY): Response
    {
        if ($request->inertia()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $message]);

            return back();
        }

        return response()->json(['error' => $message], $status);
    }
}

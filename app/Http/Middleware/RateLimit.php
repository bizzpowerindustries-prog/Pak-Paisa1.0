// app/Http/Middleware/RateLimit.php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;

class RateLimit
{
    public function handle($request, Closure $next, $maxAttempts = 10, $decayMinutes = 10)
    {
        $key = 'rate_limit:' . $request->ip() . ':' . $request->route()->getName();
        $attempts = Cache::get($key, 0);
        
        if ($attempts >= $maxAttempts) {
            return response()->json([
                'status' => 'error',
                'message' => 'Too many requests. Please try again later.'
            ], 429);
        }
        
        Cache::increment($key);
        Cache::expire($key, $decayMinutes * 60);
        
        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Check for locale in request (query parameter or header)
        $locale = $request->input('locale')
                  ?? $request->header('X-Locale')
                  ?? $request->header('Accept-Language');

        // Extract the primary language code
        if ($locale) {
            // Handle Accept-Language format (e.g., "en-US,en;q=0.9")
            $locale = substr($locale, 0, 2);
        }

        // Set locale if valid, otherwise use default
        if (in_array($locale, ['en', 'ar'])) {
            App::setLocale($locale);
        } else {
            App::setLocale(config('app.locale', 'en'));
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Services\JsonResponseAPI;
use Closure;
use Illuminate\Http\Request;

class ValidateRequestHeaders
{

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if (! $request->hasHeader('accept') || $request->header('accept') !== 'application/json')
            return JsonResponseAPI::errorResponse(
                'Include Accept header and set the value to application/json.',
                JsonResponseAPI::$FORBIDDEN
            );

        return $next($request);
    }
}

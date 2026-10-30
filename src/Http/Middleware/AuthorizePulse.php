<?php

namespace Spark\Pulse\Http\Middleware;

use Closure;
use Spark\Contracts\Http\MiddlewareInterface;
use Spark\Http\Request;
use Spark\Http\Response;
use Spark\Pulse\Pulse;

/**
 * Pulse Dashboard Authorization Middleware.
 *
 * Ensures only authorized requests (e.g. local environment or explicit Pulse::auth gate)
 * can access the Pulse dashboard and APIs.
 *
 * @package Spark\Pulse\Http\Middleware
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class AuthorizePulse implements MiddlewareInterface
{
    /**
     * Handle the request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        if (!Pulse::instance()->check($request)) {
            return Response::error(Response::STATUS_FORBIDDEN, 'Forbidden: You are not authorized to access Spark Pulse.');
        }

        return $next($request);
    }
}

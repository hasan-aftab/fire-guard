<?php

namespace App\Http\Middleware;

use App\Actions\SyncFireNotifications;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SyncFireNotificationsAfterWrite
{
    public function __construct(private SyncFireNotifications $notifications) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethod('GET') && $response->getStatusCode() < 400 && $request->user() instanceof User) {
            $this->notifications->handle($request->user());
        }

        return $response;
    }
}

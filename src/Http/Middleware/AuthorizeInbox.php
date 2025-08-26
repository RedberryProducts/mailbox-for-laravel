<?php

namespace Redberry\MailboxForLaravel\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Gate;

class AuthorizeInbox
{
    public function handle($request, Closure $next)
    {
        $ability = config('inbox.gate', 'viewMailbox');
        // Let Gate decide (works with or without authenticated user; $user can be null)
        if (Gate::allows($ability)) {
            return $next($request);
        }

        abort(403);
    }
}

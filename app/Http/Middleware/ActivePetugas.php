<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ActivePetugas
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }
        abort_unless($request->user()->aktif && $request->user()->hasRole('petugas'), 403);

        return $next($request);
    }
}

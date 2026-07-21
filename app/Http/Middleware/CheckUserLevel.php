<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckUserLevel
{
    public function handle(Request $request, Closure $next, int $minLevel = User::LEVEL_MEMBER): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->guest(front_route('login'));
        }

        if ($user->level < $minLevel) {
            abort(403);
        }

        return $next($request);
    }
}

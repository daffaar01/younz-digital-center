<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureYounzPpobAgentToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.younz_ppob.agent_token');
        $provided = (string) $request->header('X-Younz-Agent-Token', '');

        abort_if($expected === '' || ! hash_equals($expected, $provided), 401, 'Unauthorized agent.');

        return $next($request);
    }
}

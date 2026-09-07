<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureYounzPpobOperatorToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.younz_ppob.operator_token');
        $agentToken = (string) config('services.younz_ppob.agent_token');
        $provided = (string) $request->header('X-Younz-Operator-Token', '');

        abort_if(
            $expected === ''
                || $expected === $agentToken
                || ! hash_equals($expected, $provided),
            401,
            'Unauthorized operator.',
        );

        return $next($request);
    }
}

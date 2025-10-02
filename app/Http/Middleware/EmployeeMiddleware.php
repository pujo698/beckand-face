<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EmployeeMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user(); 

        if ($user && $user->role === 'employee') {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Unauthorized: Employees only'
        ], 403);
    }
}

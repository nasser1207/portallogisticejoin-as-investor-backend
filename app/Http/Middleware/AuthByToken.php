<?php
namespace App\Http\Middleware;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class AuthByToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractToken($request);
        if (!$token) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }
        $user = User::where('api_token', $token)->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }
        $request->setUserResolver(fn () => $user);
        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        // Method 1: standard bearerToken()
        $token = $request->bearerToken();
        if ($token) return $token;

        // Method 2: REDIRECT_HTTP_AUTHORIZATION (cPanel CGI)
        $header = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
        if ($header && str_starts_with(strtolower($header), 'bearer ')) {
            return trim(substr($header, 7));
        }

        // Method 3: HTTP_AUTHORIZATION set by RewriteRule
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
        if ($header && str_starts_with(strtolower($header), 'bearer ')) {
            return trim(substr($header, 7));
        }

        // Method 4: X-Authorization fallback header
        $header = $request->header('X-Authorization');
        if ($header && str_starts_with(strtolower($header), 'bearer ')) {
            return trim(substr($header, 7));
        }

        // Method 5: token query param as last resort
        $token = $request->query('api_token');
        if ($token) return $token;

        return null;
    }
}

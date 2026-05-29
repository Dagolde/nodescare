<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use Illuminate\Support\Facades\Cache;

class JWTAuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Allow session-authenticated Hospital Admin web users
        if ($request->header('X-Requested-With') === 'XMLHttpRequest' &&
            \Illuminate\Support\Facades\Auth::guard('web')->check()) {
            $user = \Illuminate\Support\Facades\Auth::guard('web')->user();
            if ($user && $user->is_active) {
                $request->setUserResolver(fn () => $user);
                // Ensure tenant scope is set
                if (!app()->bound('current_tenant_id') && $user->tenant_id) {
                    app()->instance('current_tenant_id', $user->tenant_id);
                }
                return $next($request);
            }
        }

        try {
            $token   = JWTAuth::parseToken();
            $payload = $token->getPayload();

            $userId   = $payload->get('sub');
            $tenantId = $payload->get('tenant_id');
            $exp      = $payload->get('exp');

            $cacheKey    = "tenant:{$tenantId}:user_perms:{$userId}";
            $remainingTtl = max(1, $exp - time());

            $user = Cache::remember($cacheKey, $remainingTtl, fn () => $token->authenticate());

            if (!$user) {
                return $this->unauthorizedResponse('User not found.');
            }

            if ($payload->get('token_version') !== $user->token_version) {
                Cache::forget($cacheKey);
                return $this->unauthorizedResponse('Token has been invalidated.');
            }

            if (!$user->is_active) {
                Cache::forget($cacheKey);
                return $this->unauthorizedResponse('User account is inactive.');
            }

            if ($user->isLocked()) {
                Cache::forget($cacheKey);
                return $this->unauthorizedResponse('User account is locked.');
            }

            // Bind tenant scope from JWT claim
            if ($tenantId && !app()->bound('current_tenant_id')) {
                app()->instance('current_tenant_id', $tenantId);
            }

            $request->setUserResolver(fn () => $user);

        } catch (TokenExpiredException $e) {
            return $this->unauthorizedResponse('Token has expired.');
        } catch (TokenInvalidException $e) {
            return $this->unauthorizedResponse('Token is invalid.');
        } catch (JWTException $e) {
            return $this->unauthorizedResponse('Token not provided.');
        }

        return $next($request);
    }

    protected function unauthorizedResponse(string $message): Response
    {
        return response()->json([
            'success' => false,
            'error'   => ['code' => 'UNAUTHORIZED', 'message' => $message],
        ], 401);
    }
}

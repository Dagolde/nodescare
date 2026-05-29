<?php

namespace App\Http\Middleware;

use App\Models\SuperAdmin;
use Closure;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminAuthMiddleware
{
    /**
     * Handle an incoming request.
     * Validates JWT for Super Admin authentication.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // For web/Inertia requests OR axios calls from the web dashboard
            // (identified by X-Inertia header, acceptsHtml, or X-Requested-With from axios)
            $isWebRequest = $request->header('X-Inertia')
                || $request->acceptsHtml()
                || $request->header('X-Requested-With') === 'XMLHttpRequest';

            if ($isWebRequest) {
                $superAdminId = session('super_admin_id');

                if (!$superAdminId) {
                    if ($request->header('X-Inertia') || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                        return response()->json(['success' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Not authenticated.']], 401);
                    }
                    return redirect()->route('admin.login');
                }

                $superAdmin = SuperAdmin::find($superAdminId);

                if (!$superAdmin) {
                    session()->forget('super_admin_id');
                    if ($request->header('X-Requested-With') === 'XMLHttpRequest') {
                        return response()->json(['success' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Not authenticated.']], 401);
                    }
                    return redirect()->route('admin.login');
                }

                $request->setUserResolver(fn () => $superAdmin);
                return $next($request);
            }

            // For pure API requests with JWT
            $token = JWTAuth::parseToken();
            $payload = $token->getPayload();

            $role = $payload->get('role');
            if ($role !== 'super_admin') {
                return $this->unauthorizedResponse('Access denied. Super Admin role required.');
            }

            $superAdminId = $payload->get('sub');
            $superAdmin = SuperAdmin::find($superAdminId);

            if (!$superAdmin) {
                return $this->unauthorizedResponse('Super Admin not found.');
            }

            $tokenVersion = $payload->get('token_version');
            if ($tokenVersion !== $superAdmin->token_version) {
                return $this->unauthorizedResponse('Token has been invalidated.');
            }

            $request->setUserResolver(fn () => $superAdmin);

        } catch (TokenExpiredException $e) {
            return $this->unauthorizedResponse('Token has expired.');
        } catch (TokenInvalidException $e) {
            return $this->unauthorizedResponse('Token is invalid.');
        } catch (JWTException $e) {
            // If no JWT and no session, redirect to login
            if ($request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json(['success' => false, 'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Not authenticated.']], 401);
            }
            return redirect()->route('admin.login');
        }

        return $next($request);
    }

    /**
     * Return unauthorized response.
     */
    protected function unauthorizedResponse(string $message): Response
    {
        // For Inertia/web requests, redirect to login
        if (request()->header('X-Inertia')) {
            return redirect()->route('admin.login');
        }

        // For API requests, return JSON
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'UNAUTHORIZED',
                'message' => $message,
            ],
        ], 401);
    }
}

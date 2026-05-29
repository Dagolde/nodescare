<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\FcmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function __construct(
        private readonly FcmService $fcmService,
    ) {}

    /**
     * Store an FCM device token for the authenticated user.
     *
     * POST /api/v1/device-tokens
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token'    => ['required', 'string', 'max:500'],
            'platform' => ['required', 'string', 'in:android,ios,web'],
        ]);

        $this->fcmService->storeDeviceToken(
            userId: $request->user()->id,
            token: $validated['token'],
            platform: $validated['platform'],
        );

        return response()->json([
            'success' => true,
            'message' => 'Device token stored successfully.',
        ]);
    }
}

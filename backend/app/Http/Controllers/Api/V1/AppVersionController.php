<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;

class AppVersionController extends Controller
{
    /**
     * Return mobile app version information.
     *
     * GET /api/v1/app/version
     *
     * No auth required — tenant resolution only.
     * Mobile app calls this on startup to check if an update is required.
     */
    public function version(): JsonResponse
    {
        $minVersion     = SystemSetting::get('mobile_app_min_version', '1.0.0');
        $forceUpdate    = SystemSetting::get('mobile_app_force_update', '0');
        $currentVersion = SystemSetting::get('mobile_app_version', '1.0.0');

        return response()->json([
            'success' => true,
            'data'    => [
                'min_version'     => $minVersion,
                'force_update'    => $forceUpdate === '1' || $forceUpdate === 'true',
                'current_version' => $currentVersion,
            ],
        ]);
    }
}

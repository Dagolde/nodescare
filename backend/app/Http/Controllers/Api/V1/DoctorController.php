<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VisitType;
use Illuminate\Http\JsonResponse;

class DoctorController extends Controller
{
    /**
     * List active doctors for a given visit type slug.
     * Matches doctors whose specialties contain the slug OR whose department type matches.
     *
     * GET /api/v1/doctors/by-visit-type/{visit_type}
     */
    public function byVisitType(string $slug): JsonResponse
    {
        // Validate slug exists as an active visit type for this tenant
        $visitTypeExists = VisitType::where('slug', $slug)
            ->where('is_active', true)
            ->exists();

        if (!$visitTypeExists) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'VALIDATION_ERROR',
                    'message' => "Visit type '{$slug}' not found or is inactive for this hospital.",
                ],
            ], 422);
        }

        // First try: doctors whose specialties contain the slug OR department type matches
        $doctors = User::with('department')
            ->where('role', 'doctor')
            ->where('is_active', true)
            ->where(function ($q) use ($slug) {
                $q->whereJsonContains('specialties', $slug)
                  ->orWhereHas('department', fn($d) => $d->where('type', $slug));
            })
            ->get(['id', 'name', 'department_id', 'specialties']);

        // Fallback: if no doctors match the specialty/department filter,
        // return ALL active doctors so front desk can still assign someone
        if ($doctors->isEmpty()) {
            $doctors = User::with('department')
                ->where('role', 'doctor')
                ->where('is_active', true)
                ->get(['id', 'name', 'department_id', 'specialties']);
        }

        return response()->json([
            'success' => true,
            'data'    => $doctors->map(fn($doctor) => [
                'id'              => $doctor->id,
                'name'            => $doctor->name,
                'department_id'   => $doctor->department_id,
                'department'      => $doctor->department
                    ? ['id' => $doctor->department->id, 'name' => $doctor->department->name]
                    : null,
                'specialties'     => $doctor->specialties ?? [],
            ]),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\VisitType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VisitTypeController extends Controller
{
    /**
     * List all active visit types for the current tenant, ordered by sort_order.
     * GET /api/v1/visit-types
     */
    public function index(): JsonResponse
    {
        $visitTypes = VisitType::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $visitTypes,
        ]);
    }

    /**
     * Create a new visit type for the current tenant.
     * POST /api/v1/visit-types
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'       => 'required|string|max:100',
            'slug'       => 'required|string|max:100|regex:/^[a-z0-9_]+$/',
            'sort_order' => 'nullable|integer|min:0',
            'is_active'  => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'VALIDATION_ERROR', 'message' => 'The given data was invalid.', 'details' => $validator->errors()],
            ], 422);
        }

        // Check slug uniqueness within tenant
        $exists = VisitType::withoutGlobalScopes()
            ->where('tenant_id', app('current_tenant_id'))
            ->where('slug', $request->input('slug'))
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'DUPLICATE_SLUG', 'message' => 'A visit type with this slug already exists for your hospital.'],
            ], 422);
        }

        $visitType = VisitType::create([
            'tenant_id'  => app('current_tenant_id'),
            'name'       => $request->input('name'),
            'slug'       => $request->input('slug'),
            'is_default' => false,
            'is_active'  => $request->input('is_active', true),
            'sort_order' => $request->input('sort_order', 99),
        ]);

        return response()->json([
            'success' => true,
            'data'    => $visitType,
            'message' => 'Visit type created successfully.',
        ], 201);
    }

    /**
     * Update an existing visit type.
     * PUT /api/v1/visit-types/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $visitType = VisitType::find($id);

        if (!$visitType) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'NOT_FOUND', 'message' => 'Visit type not found.'],
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'       => 'nullable|string|max:100',
            'slug'       => 'nullable|string|max:100|regex:/^[a-z0-9_]+$/',
            'sort_order' => 'nullable|integer|min:0',
            'is_active'  => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'VALIDATION_ERROR', 'message' => 'The given data was invalid.', 'details' => $validator->errors()],
            ], 422);
        }

        // Check slug uniqueness if slug is being changed
        if ($request->filled('slug') && $request->input('slug') !== $visitType->slug) {
            $exists = VisitType::withoutGlobalScopes()
                ->where('tenant_id', app('current_tenant_id'))
                ->where('slug', $request->input('slug'))
                ->where('id', '!=', $id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'error'   => ['code' => 'DUPLICATE_SLUG', 'message' => 'A visit type with this slug already exists for your hospital.'],
                ], 422);
            }
        }

        $visitType->fill(array_filter([
            'name'       => $request->input('name'),
            'slug'       => $request->input('slug'),
            'sort_order' => $request->input('sort_order'),
            'is_active'  => $request->input('is_active'),
        ], fn($v) => $v !== null));

        $visitType->save();

        return response()->json([
            'success' => true,
            'data'    => $visitType->fresh(),
            'message' => 'Visit type updated successfully.',
        ]);
    }

    /**
     * Soft-delete (deactivate) a visit type.
     * DELETE /api/v1/visit-types/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $visitType = VisitType::find($id);

        if (!$visitType) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'NOT_FOUND', 'message' => 'Visit type not found.'],
            ], 404);
        }

        if ($visitType->is_default) {
            return response()->json([
                'success' => false,
                'error'   => ['code' => 'CANNOT_DELETE_DEFAULT', 'message' => 'Default visit types cannot be deleted.'],
            ], 422);
        }

        $visitType->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Visit type deactivated.',
        ]);
    }
}

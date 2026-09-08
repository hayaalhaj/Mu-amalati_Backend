<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DepartmentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Department;
use Illuminate\Http\JsonResponse;

class DepartmentController extends Controller
{
    use ApiResponse;

    /**
     * List active departments for dropdowns.
     */
    public function index(): JsonResponse
    {
        $departments = Department::query()
            ->select([
                'id',
                'name',
                'description',
                'is_active',
                'created_at',
                'updated_at',
            ])
            ->active()
            ->orderBy('name')
            ->get();

        return $this->success(
            DepartmentResource::collection(
                $departments
            )
        );
    }

    /**
     * Show a single active department.
     */
    public function show(
        Department $department
    ): JsonResponse {
        if (! $department->is_active) {
            return $this->notFound(
                'Department not found.'
            );
        }

        $department->load('manager');

        return $this->success(
            new DepartmentResource(
                $department
            )
        );
    }
}
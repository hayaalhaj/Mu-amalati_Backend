<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDepartmentRequest;
use App\Http\Requests\Admin\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDepartmentController extends Controller
{
    use ApiResponse;

    /**
     * List all departments.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 10), 50);

        $departments = Department::with('manager')
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate($perPage);

        return $this->successCollection(
            DepartmentResource::collection($departments),
            200,
            [
                'current_page' => $departments->currentPage(),
                'per_page' => $departments->perPage(),
                'total' => $departments->total(),
                'last_page' => $departments->lastPage(),
            ]
        );
    }

    /**
     * Create a department.
     */
    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($data['manager_id'] ?? null) {
            $this->validateManager($data['manager_id']);
        }

        $department = Department::create($data);

        return $this->success(new DepartmentResource($department->load('manager')), 'Department created successfully.', 201);
    }

    /**
     * Show a department.
     */
    public function show(Department $department): JsonResponse
    {
        return $this->success(new DepartmentResource($department->load('manager')));
    }

    /**
     * Update a department.
     */
    public function update(UpdateDepartmentRequest $request, Department $department): JsonResponse
    {
        $data = $request->validated();

        if (($data['manager_id'] ?? null) && $data['manager_id'] !== $department->manager_id) {
            $this->validateManager($data['manager_id']);
        }

        $department->update($data);

        return $this->success(new DepartmentResource($department->load('manager')), 'Department updated successfully.');
    }

    /**
     * Delete a department, or refuse if referenced.
     */
    public function destroy(Department $department): JsonResponse
    {
        $referenced = $department->users()->exists()
            || $department->transactionTypes()->exists()
            || $department->workflowStepDefinitions()->exists()
            || $department->sourceTransactions()->exists()
            || $department->destinationTransactions()->exists()
            || $department->currentTransactions()->exists();

        if ($referenced) {
            return $this->error('This department is referenced by other records and cannot be deleted. Deactivate it instead.', 422);
        }

        $department->delete();

        return $this->success(null, 'Department deleted successfully.', 204);
    }

    /**
     * Activate a department.
     */
    public function activate(Department $department): JsonResponse
    {
        $department->update(['is_active' => true]);

        return $this->success(new DepartmentResource($department->load('manager')), 'Department activated successfully.');
    }

    /**
     * Deactivate a department.
     */
    public function deactivate(Department $department): JsonResponse
    {
        $department->update(['is_active' => false]);

        return $this->success(new DepartmentResource($department->load('manager')), 'Department deactivated successfully.');
    }

    /**
     * Validate that the selected manager has the manager role and is active.
     */
    protected function validateManager(int $userId): void
    {
        $manager = User::find($userId);

        if (! $manager || $manager->role !== UserRole::Manager || ! $manager->isActive()) {
            abort(422, 'The selected manager must exist, have the manager role, and be active.');
        }
    }
}

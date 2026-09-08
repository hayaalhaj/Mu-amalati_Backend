<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    use ApiResponse;

    /**
     * List users with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->integer('per_page', 10), 50);

        $users = User::with('department')
            ->when(
                $request->filled('role'),
                fn ($q) =>
                    $q->where('role', $request->role)
            )
            ->when(
                $request->filled('department_id'),
                fn ($q) =>
                    $q->where(
                        'department_id',
                        $request->department_id
                    )
            )
            ->when(
                $request->filled('status'),
                fn ($q) =>
                    $q->where(
                        'status',
                        $request->status
                    )
            )
            ->when(
                $request->filled('search'),
                fn ($q) =>
                    $q->where(
                        fn ($sub) =>
                            $sub
                                ->where(
                                    'name',
                                    'like',
                                    "%{$request->search}%"
                                )
                                ->orWhere(
                                    'email',
                                    'like',
                                    "%{$request->search}%"
                                )
                    )
            )
            ->orderBy('name')
            ->paginate($perPage);

        return $this->successCollection(
            UserResource::collection($users),
            200,
            [
                'current_page' =>
                    $users->currentPage(),

                'per_page' =>
                    $users->perPage(),

                'total' =>
                    $users->total(),

                'last_page' =>
                    $users->lastPage(),
            ]
        );
    }

    /**
     * Create a new user.
     */
    public function store(
        StoreUserRequest $request
    ): JsonResponse {
        $user = User::create(
            $request->validated()
        );

        return $this->success(
            new UserResource(
                $user->load('department')
            ),
            'User created successfully.',
            201
        );
    }

    /**
     * Show a single user.
     */
    public function show(
        User $user
    ): JsonResponse {
        return $this->success(
            new UserResource(
                $user->load('department')
            )
        );
    }

    /**
     * Update a user.
     */
    public function update(
        UpdateUserRequest $request,
        User $user
    ): JsonResponse {
        $user->update(
            $request->validated()
        );

        return $this->success(
            new UserResource(
                $user->load('department')
            ),
            'User updated successfully.'
        );
    }

    /**
     * Delete a user, or deactivate if referenced.
     */
    public function destroy(
        User $user
    ): JsonResponse {
        $referenced =
            $user->createdTransactions()->exists()
            || $user->uploadedAttachments()->exists()
            || $user->performedHistoryActions()->exists()
            || $user->managedDepartments()->exists();

        if ($referenced) {
            $user->update([
                'status' => 'inactive',
            ]);

            // Invalidate all existing sessions/tokens.
            $user->tokens()->delete();

            return $this->success(
                null,
                'User is referenced by other records and was deactivated instead.',
                200
            );
        }

        $user->tokens()->delete();

        $user->delete();

        return $this->success(
            null,
            'User deleted successfully.',
            204
        );
    }

    /**
     * Activate a user.
     */
    public function activate(
        User $user
    ): JsonResponse {
        $user->update([
            'status' => 'active',
        ]);

        return $this->success(
            new UserResource(
                $user->load('department')
            ),
            'User activated successfully.'
        );
    }

    /**
     * Deactivate a user.
     */
    public function deactivate(
        User $user
    ): JsonResponse {
        $user->update([
            'status' => 'inactive',
        ]);

        // Invalidate all existing sessions/tokens.
        $user->tokens()->delete();

        return $this->success(
            new UserResource(
                $user->load('department')
            ),
            'User deactivated successfully.'
        );
    }
}
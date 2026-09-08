<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use ApiResponse;

    /**
     * Authenticate a user and issue a token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return $this->error('Invalid credentials.', 401);
        }

        if (! $user->isActive()) {
            return $this->error('Your account has been deactivated.', 403);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return $this->success([
            'user' => new UserResource($user->load('department')),
            'token' => $token,
        ], 'Login successful.');
    }

    /**
     * Revoke the current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'Logout successful.');
    }

    /**
     * Revoke all access tokens for the authenticated user.
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return $this->success(null, 'Logged out from all devices.');
    }

    /**
     * Return the authenticated user.
     */
    public function me(Request $request): JsonResponse
    {
        return $this->success(new UserResource($request->user()->load('department')), 'Authenticated user.');
    }

    /**
     * Update the authenticated user's profile.
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->fill($request->validated());
        $user->save();

        return $this->success(new UserResource($user->load('department')), 'Profile updated successfully.');
    }

    /**
     * Change the authenticated user's password.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return $this->error('The current password is incorrect.', 422);
        }

        $user->update(['password' => $request->password]);

        return $this->success(null, 'Password changed successfully.');
    }
}

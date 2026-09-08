<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Login
    |--------------------------------------------------------------------------
    */

    public function test_an_active_user_can_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'employee@company.test',
            'password' => 'password',
            'role' => UserRole::Employee,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'employee@company.test',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['user', 'token'],
            ]);
    }

    public function test_invalid_credentials_return_401(): void
    {
        User::factory()->create([
            'email' => 'employee@company.test',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'employee@company.test',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Invalid credentials.');
    }

    public function test_an_inactive_user_cannot_log_in(): void
    {
        $user = User::factory()->inactive()->create([
            'email' => 'inactive@company.test',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'inactive@company.test',
            'password' => 'password',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    /*
    |--------------------------------------------------------------------------
    | Profile
    |--------------------------------------------------------------------------
    */

    public function test_authenticated_user_can_retrieve_their_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_user_can_update_only_limited_profile_fields(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'job_title' => 'Analyst',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/auth/me', [
                'name' => 'Updated Name',
                'phone' => '+491234567890',
                'job_title' => 'Senior Analyst',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.job_title', 'Senior Analyst');
    }

    public function test_user_cannot_change_role_email_or_status_through_profile(): void
    {
        $user = User::factory()->create([
            'email' => 'original@company.test',
            'role' => UserRole::Employee,
            'status' => UserStatus::Active,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/auth/me', [
                'email' => 'hacked@company.test',
                'role' => 'admin',
                'status' => 'inactive',
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'original@company.test',
            'role' => UserRole::Employee->value,
            'status' => UserStatus::Active->value,
        ]);
    }

    public function test_user_can_change_password_with_current_password(): void
    {
        $user = User::factory()->create([
            'password' => 'old-password',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/v1/auth/password', [
                'current_password' => 'old-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    /*
    |--------------------------------------------------------------------------
    | Logout
    |--------------------------------------------------------------------------
    */

    public function test_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('test-token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_user_can_log_out_from_all_devices(): void
    {
        $user = User::factory()->create();
        $user->createToken('first');
        $user->createToken('second');

        $token = $user->createToken('current')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout-all')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Registration
    |--------------------------------------------------------------------------
    */

    public function test_no_public_registration_endpoint_exists(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'New User',
            'email' => 'new@company.test',
            'password' => 'password',
        ]);

        $response->assertStatus(404);
    }

    public function test_user_resource_does_not_expose_password_or_token(): void
    {
        $department = Department::factory()->create();
        $user = User::factory()->employee($department)->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token')
            ->assertJsonMissingPath('data.email_verified_at');
    }
}

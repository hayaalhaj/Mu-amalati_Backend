<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_sees_only_their_notifications(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Notification::factory()->count(3)->create(['user_id' => $user->id]);
        Notification::factory()->count(2)->create(['user_id' => $other->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJsonPath('meta.total', 3);
    }

    public function test_user_can_mark_their_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notification = Notification::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertOk()
            ->assertJsonPath('data.is_read', true);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'is_read' => true,
        ]);
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $notification = Notification::factory()->create(['user_id' => $other->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertForbidden();

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'is_read' => false,
        ]);
    }

    public function test_read_all_updates_only_the_authenticated_users_notifications(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Notification::factory()->count(2)->create(['user_id' => $user->id]);
        Notification::factory()->count(2)->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/notifications/read-all')
            ->assertOk();

        $this->assertSame(2, Notification::where('user_id', $user->id)->where('is_read', true)->count());
        $this->assertSame(0, Notification::where('user_id', $other->id)->where('is_read', true)->count());
    }

    public function test_unread_count_is_returned(): void
    {
        $user = User::factory()->create();

        Notification::factory()->count(2)->create(['user_id' => $user->id]);
        Notification::factory()->read()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/notifications/unread-count');

        $response->assertOk()
            ->assertJsonPath('data.count', 2);
    }

    public function test_user_can_delete_their_own_notification(): void
    {
        $user = User::factory()->create();
        $notification = Notification::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/v1/notifications/{$notification->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }
}

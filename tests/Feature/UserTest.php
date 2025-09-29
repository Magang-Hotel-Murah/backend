<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // login sebagai admin untuk semua test
        Sanctum::actingAs(
            User::factory()->create(['role' => 'admin']),
            ['*']
        );
    }

    /** @test */
    public function test_it_can_list_users_without_deleted()
    {
        $deletedUser = User::factory()->create();
        $deletedUser->delete();

        $response = $this->getJson('/api/users');

        $expectedCount = User::count(); // hanya yg aktif
        $response->assertStatus(200)
            ->assertJsonCount($expectedCount);
    }

    /** @test */
    public function test_it_can_list_users_with_deleted()
    {
        $deletedUser = User::factory()->create();
        $deletedUser->delete();

        $response = $this->getJson('/api/users?with_deleted=1');

        $expectedCount = User::withTrashed()->count(); // semua, termasuk yg terhapus
        $response->assertStatus(200)
            ->assertJsonCount($expectedCount);
    }

    /** @test */
    public function test_it_can_show_a_user()
    {
        $user = User::factory()->create();

        $response = $this->getJson("/api/users/{$user->id}");

        $response->assertStatus(200)
            ->assertJsonFragment([
                'id' => $user->id,
                'email' => $user->email,
            ]);
    }

    /** @test */
    public function test_it_can_update_user_name_and_role()
    {
        $user = User::factory()->create();

        $response = $this->putJson("/api/users/{$user->id}", [
            'name' => 'Updated Name',
            'role' => 'admin',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'User updated successfully']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'role' => 'admin',
        ]);
    }

    /** @test */
    public function test_it_can_restore_a_deleted_user_via_update()
    {
        $user = User::factory()->create();
        $user->delete();

        $response = $this->putJson("/api/users/{$user->id}", [
            'restore' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'User restored successfully']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'deleted_at' => null,
        ]);
    }

    /** @test */
    public function test_it_returns_error_if_restore_called_on_active_user()
    {
        $user = User::factory()->create();

        $response = $this->putJson("/api/users/{$user->id}", [
            'restore' => true,
        ]);

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'User is not deleted']);
    }

    /** @test */
    public function test_it_can_delete_a_user()
    {
        $user = User::factory()->create();

        $response = $this->deleteJson("/api/users/{$user->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'User deleted successfully']);

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    /** @test */
    public function test_it_can_restore_a_user_via_destroy()
    {
        $user = User::factory()->create();
        $user->delete();

        $response = $this->deleteJson("/api/users/{$user->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'User restored successfully']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'deleted_at' => null,
        ]);
    }
}

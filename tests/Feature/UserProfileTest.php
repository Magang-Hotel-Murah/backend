<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserProfile;
use App\Models\Division;
use App\Models\Position;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_it_can_list_profiles()
    {
        $division = Division::factory()->create();
        $position = Position::factory()->create();

        UserProfile::factory()->count(3)->create([
            'division_id' => $division->id,
            'position_id' => $position->id,
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $response = $this->getJson('/api/user-profiles');

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    /** @test */
    public function test_it_can_show_own_profile_for_non_admin()
    {
        $user = User::factory()->create(['role' => 'user']);
        $profile = UserProfile::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/user-profile');

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $profile->id]);
    }

    /** @test */
    public function admin_can_show_other_user_profile()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);
        $profile = UserProfile::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($admin);

        $response = $this->getJson("/api/user-profiles/{$user->id}");

        $response->assertStatus(200)
            ->assertJsonFragment(['id' => $profile->id]);
    }

    /** @test */
    public function test_it_can_create_profile_for_authenticated_user()
    {
        $user = User::factory()->create(['role' => 'user']);
        $division = Division::factory()->create();
        $position = Position::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/user-profiles', [
            'division_id' => $division->id,
            'position_id' => $position->id,
            'address' => 'Jl. Contoh',
            'phone'   => '08123456789',
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['message' => 'Profile created successfully']);

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $user->id,
            'division_id' => $division->id,
            'position_id' => $position->id,
        ]);
    }

    /** @test */
    public function test_it_cannot_create_profile_if_already_exists()
    {
        $user = User::factory()->create();
        $profile = UserProfile::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/user-profiles', [
            'division_id' => $profile->division_id,
            'position_id' => $profile->position_id,
        ]);

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'Profile already exists for this user.']);
    }

    /** @test */
    public function test_it_can_update_profile()
    {
        $user = User::factory()->create();
        $profile = UserProfile::factory()->create(['user_id' => $user->id]);
        $division = Division::factory()->create();
        $position = Position::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/user-profiles/{$user->id}", [
            'division_id' => $division->id,
            'position_id' => $position->id,
            'address' => 'Updated address',
            'phone'   => '08987654321',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['message' => 'Profile updated successfully']);

        $this->assertDatabaseHas('user_profiles', [
            'id' => $profile->id,
            'division_id' => $division->id,
            'position_id' => $position->id,
            'address' => 'Updated address',
            'phone' => '08987654321',
        ]);
    }
}

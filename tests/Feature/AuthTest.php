<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\URL;

use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_user_can_register()
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'testuser@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'testuser@example.com',
        ]);
    }

    /** @test */
    public function test_user_can_login()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['user', 'token']);
    }

    /** @test */
    public function test_user_cannot_login_with_invalid_credentials()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'wrong@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Incorrect email or password'
            ]);
    }

    /** @test */
    public function test_user_cannot_login_with_unverified_email()
    {
        $user = User::factory()->unverified()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Please verify your email address',
            ]);

        // pastikan response punya token
        $this->assertArrayHasKey('token', $response->json());
    }

    /** @test */
    public function test_user_can_logout()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logged out successfully',
            ]);
    }

    /** @test */
    public function test_user_can_request_password_reset_link()
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function test_user_can_reset_password_with_valid_otp()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com'
        ]);

        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => '123456',
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'otp' => '123456',
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Password berhasil direset.'
            ]);

        $this->assertTrue(Hash::check('newpassword', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $user->email
        ]);
    }

    /** @test */
    public function test_user_cannot_reset_password_with_invalid_otp()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com'
        ]);

        $response = $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'otp' => '999999',
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'OTP tidak valid'
            ]);
    }

    /** @test */
    public function test_user_cannot_reset_password_with_expired_otp()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com'
        ]);

        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => '123456',
            'created_at' => Carbon::now()->subMinutes(10),
        ]);

        $response = $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'otp' => '123456',
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'OTP sudah kadaluarsa, silakan minta ulang.'
            ]);
    }

    /** @test */
    public function test_user_can_verify_email_with_valid_link()
    {
        Event::fake();

        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(5),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
        );

        $response = $this->getJson($url);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Email verified successfully.'
            ]);

        $this->assertNotNull($user->fresh()->email_verified_at);
        Event::assertDispatched(\Illuminate\Auth\Events\Verified::class);
    }

    /**@test */
    public function test_user_cannot_verify_email_with_invalid_link()
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(5),
            ['id' => $user->id, 'hash' => sha1('wrong-email')]
        );

        $response = $this->getJson($url);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid verification link.'
            ]);

        $this->assertNull($user->fresh()->email_verified_at);
    }

    /**@test */
    public function test_user_email_already_verified()
    {
        $user = User::factory()->create([
            'email_verified_at' => now(), // pastikan sudah verified
        ]);

        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(5),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())]
        );

        $response = $this->getJson($url);

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'message' => 'Email already verified.'
            ]);
    }
}

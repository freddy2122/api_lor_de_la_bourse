<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_token_and_user_resource(): void
    {
        $password = 'secret1234';
        $user = User::factory()->create([
            'password' => Hash::make($password),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'token',
                'user' => ['id', 'name', 'email', 'role', 'created_at', 'updated_at'],
            ]);
    }

    public function test_me_endpoint_requires_auth_and_works_with_token(): void
    {
        $password = 'secret1234';
        $user = User::factory()->create([
            'password' => Hash::make($password),
        ]);

        // Unauthorized first
        $this->getJson('/api/me')->assertStatus(401);

        // Login
        $token = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ])->json('token');

        // Authorized
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJson(['email' => $user->email]);
    }
}

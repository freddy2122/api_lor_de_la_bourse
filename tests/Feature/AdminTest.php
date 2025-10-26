<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\AccountOpeningRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_admin_routes(): void
    {
        $user = User::factory()->create([
            'role' => 'client',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/admin/account-opening-requests')
            ->assertStatus(403);
    }

    public function test_admin_can_approve_account_opening_request(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $req = AccountOpeningRequest::create([
            'nom' => 'Doe',
            'prenom' => 'John',
            'date_naissance' => '1990-01-01',
            'nationalite' => 'FR',
            'pays_residence' => 'FR',
            'adresse' => '1 rue test',
            'ville' => 'Paris',
            'telephone' => '0102030405',
            'email' => 'john.doe@example.com',
            'status' => 'en_attente_validation',
        ]);

        $res = $this->postJson("/api/admin/account-opening-requests/{$req->id}/approve");
        $res->assertOk()
            ->assertJsonStructure(['message', 'data' => ['user_id', 'request_id']]);

        $this->assertDatabaseHas('users', [
            'email' => 'john.doe@example.com',
            'role' => 'client',
        ]);

        $this->assertDatabaseHas('account_opening_requests', [
            'id' => $req->id,
            'status' => 'validee',
        ]);
    }

    public function test_admin_can_reject_account_opening_request(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $req = AccountOpeningRequest::create([
            'nom' => 'Doe',
            'prenom' => 'Jane',
            'date_naissance' => '1992-02-02',
            'nationalite' => 'FR',
            'pays_residence' => 'FR',
            'adresse' => '2 rue test',
            'ville' => 'Lyon',
            'telephone' => '0102030406',
            'email' => 'jane.doe@example.com',
            'status' => 'en_attente_validation',
        ]);

        $res = $this->postJson("/api/admin/account-opening-requests/{$req->id}/reject", [
            'reason' => 'Informations incomplètes',
        ]);
        $res->assertOk()
            ->assertJsonFragment(['message' => 'La demande a été rejetée.']);

        $this->assertDatabaseHas('account_opening_requests', [
            'id' => $req->id,
            'status' => 'rejete',
            'rejection_reason' => 'Informations incomplètes',
        ]);
    }
}

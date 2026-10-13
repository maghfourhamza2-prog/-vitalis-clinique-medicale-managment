<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_account_with_existing_token_is_revoked(): void
    {
        $user = User::factory()->create([
            'statut' => 'Inactif',
        ]);

        $token = $user->createToken('ancien-appareil')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me')
            ->assertStatus(401)
            ->assertJson(['message' => 'Non authentifié']);

        $this->assertSame(0, $user->fresh()->tokens()->count());
    }

    public function test_user_can_list_and_revoke_only_own_sessions(): void
    {
        $user = User::factory()->create(['statut' => 'Actif']);
        $other = User::factory()->create(['statut' => 'Actif']);

        $current = $user->createToken('courant')->plainTextToken;
        $second = $user->createToken('mobile');
        $otherToken = $other->createToken('autre');

        $headers = ['Authorization' => 'Bearer '.$current];

        $this->getJson('/api/sessions', $headers)
            ->assertStatus(200)
            ->assertJsonCount(2, 'sessions');

        $this->deleteJson('/api/sessions/'.$second->accessToken->id, [], $headers)
            ->assertStatus(200);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $second->accessToken->id,
        ]);

        $this->deleteJson('/api/sessions/'.$otherToken->accessToken->id, [], $headers)
            ->assertStatus(404);

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $otherToken->accessToken->id,
        ]);
    }

    public function test_logout_all_revokes_every_token_of_the_user(): void
    {
        $user = User::factory()->create(['statut' => 'Actif']);
        $current = $user->createToken('courant')->plainTextToken;
        $user->createToken('mobile');
        $user->createToken('tablette');

        $this->withHeader('Authorization', 'Bearer '.$current)
            ->postJson('/api/logout-all')
            ->assertStatus(200);

        $this->assertSame(0, $user->fresh()->tokens()->count());
    }

    public function test_authenticated_api_access_is_audited_without_request_payload(): void
    {
        $user = User::factory()->create(['statut' => 'Actif']);
        $token = $user->createToken('audit')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/me')
            ->assertStatus(200);

        $audit = AuditLog::query()->latest('id')->first();

        $this->assertNotNull($audit);
        $this->assertSame($user->id, $audit->user_id);
        $this->assertSame('GET', $audit->method);
        $this->assertSame('/api/me', $audit->path);
        $this->assertSame(200, $audit->status_code);

        $this->assertFalse(array_key_exists('request_body', $audit->getAttributes()));
        $this->assertFalse(array_key_exists('response_body', $audit->getAttributes()));
    }

    public function test_only_administrator_can_read_audit_logs(): void
    {
        $admin = User::factory()->create([
            'role' => 'administrateur',
            'statut' => 'Actif',
        ]);

        $secretary = User::factory()->create([
            'role' => 'secretaire',
            'statut' => 'Actif',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/audit-logs')
            ->assertStatus(200);

        $this->app['auth']->forgetGuards();

        $this->actingAs($secretary, 'sanctum')
            ->getJson('/api/audit-logs')
            ->assertStatus(403);
    }

    public function test_sensitive_role_requires_mfa_when_enforced(): void
    {
        config([
            'security.mfa.enforce_sensitive_roles' => true,
            'security.mfa.roles' => ['administrateur', 'medecin'],
        ]);

        Mail::fake();

        $admin = User::factory()->create([
            'email' => 'admin-mfa@example.com',
            'password' => 'SecurePass123',
            'role' => 'administrateur',
            'statut' => 'Actif',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $admin->email,
            'password' => 'SecurePass123',
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('mfa_required', true)
            ->assertJsonStructure(['challenge_id', 'expires_in']);

        $this->assertArrayNotHasKey('token', $response->json());
        $this->assertSame(0, $admin->fresh()->tokens()->count());
    }

    public function test_valid_mfa_challenge_issues_token_once(): void
    {
        config([
            'app.key' => 'test-security-key',
            'security.mfa.enforce_sensitive_roles' => true,
            'security.mfa.max_attempts' => 5,
            'security.mfa.ttl_seconds' => 300,
        ]);

        $admin = User::factory()->create([
            'role' => 'administrateur',
            'statut' => 'Actif',
        ]);

        $challengeId = (string) Str::uuid();
        $code = '123456';

        Cache::put('mfa:'.$challengeId, [
            'user_id' => $admin->id,
            'code_hash' => hash_hmac('sha256', $code, (string) config('app.key')),
            'ip_hash' => hash('sha256', '127.0.0.1'),
            'attempts' => 0,
        ], now()->addMinutes(5));

        $this->postJson('/api/login/mfa', [
            'challenge_id' => $challengeId,
            'code' => $code,
        ])->assertStatus(200)
            ->assertJsonStructure(['token', 'user']);

        $this->assertNull(Cache::get('mfa:'.$challengeId));

        $this->postJson('/api/login/mfa', [
            'challenge_id' => $challengeId,
            'code' => $code,
        ])->assertStatus(401);
    }
}

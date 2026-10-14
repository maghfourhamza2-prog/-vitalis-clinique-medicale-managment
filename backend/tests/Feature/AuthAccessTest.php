<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * SCRUM-5 - Securite d'acces.
 *
 * Verifie le modele d'acces de l'API :
 *  - une route protegee refuse un visiteur non authentifie (401) ;
 *  - une route reservee a un role refuse un autre role (403) ;
 *  - le token est bien revoque apres deconnexion ;
 *  - aucune donnee sensible n'est renvoyee dans les reponses.
 */
class AuthAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Routes qui ne doivent jamais repondre a un utilisateur non authentifie.
     *
     * @return array<string, array{string, string}>
     */
    public static function protectedRoutes(): array
    {
        return [
            'profil' => ['get', '/api/me'],
            'deconnexion' => ['post', '/api/logout'],
            'tableau de bord' => ['get', '/api/dashboard'],
            'nombre de patients' => ['get', '/api/dashboard/patients-count'],
            'liste des patients' => ['get', '/api/patients'],
            'liste des roles' => ['get', '/api/roles'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('protectedRoutes')]
    public function test_protected_routes_reject_unauthenticated_users(string $method, string $uri): void
    {
        $response = $this->json(strtoupper($method), $uri);

        $response->assertStatus(401);
    }

    public function test_public_routes_stay_reachable_without_authentication(): void
    {
        // Une route publique doit rester accessible : on verifie seulement
        // qu'elle ne repond pas 401/403 (le 422 de validation est attendu ici).
        $response = $this->postJson('/api/login', []);

        $response->assertStatus(422);
    }

    public function test_login_with_invalid_credentials_returns_401_and_no_token(): void
    {
        User::factory()->create([
            'email' => 'utilisateur@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'utilisateur@example.com',
            'password' => 'mauvais-mot-de-passe',
        ]);

        $response->assertStatus(401);
        $this->assertArrayNotHasKey('token', $response->json());
    }

    public function test_inactive_account_cannot_log_in(): void
    {
        User::factory()->create([
            'email' => 'inactif@example.com',
            'password' => 'password',
            'statut' => 'Inactif',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'inactif@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Identifiants invalides']);

        $this->assertArrayNotHasKey('token', $response->json());
    }

    public function test_login_records_last_connection(): void
    {
        $user = User::factory()->create([
            'email' => 'actif@example.com',
            'password' => 'password',
            'statut' => 'Actif',
            'derniere_connexion' => null,
        ]);

        $this->postJson('/api/login', [
            'email' => 'actif@example.com',
            'password' => 'password',
        ])->assertStatus(200);

        $this->assertNotNull($user->fresh()->derniere_connexion);
    }

    public function test_login_returns_a_token_and_never_exposes_the_password(): void
    {
        User::factory()->create([
            'email' => 'utilisateur@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'utilisateur@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);

        $response->assertJsonMissingPath('user.password');
        $response->assertJsonMissingPath('user.remember_token');
    }

    public function test_role_restricted_route_rejects_a_user_without_the_role(): void
    {
        $user = User::factory()->create(['role' => 'patient']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/patients');

        $response->assertStatus(403);
    }

    public function test_role_restricted_route_accepts_an_allowed_role(): void
    {
        $user = User::factory()->create(['role' => 'secretaire']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/patients');

        $response->assertStatus(200);
    }

    public function test_admin_routes_reject_non_admin_users(): void
    {
        $user = User::factory()->create(['role' => 'medecin']);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/roles');

        $response->assertStatus(403);
    }

    public function test_admin_routes_accept_the_administrator(): void
    {
        $admin = User::factory()->create(['role' => 'administrateur']);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/roles');

        $response->assertStatus(200)->assertJsonStructure(['roles']);
    }

    public function test_token_is_revoked_after_logout(): void
    {
        $user = User::factory()->create();

        // Token cree directement : la requete est authentifiee par le header
        // Bearer, comme le fait le frontend Vue.
        $token = $user->createToken('auth_token')->plainTextToken;

        $entetes = ['Authorization' => 'Bearer '.$token];

        // Le token fonctionne avant la deconnexion.
        $this->getJson('/api/me', $entetes)->assertStatus(200);

        $this->postJson('/api/logout', [], $entetes)->assertStatus(200);

        // Le token doit avoir disparu de la base : il ne peut plus authentifier
        // personne. On verifie le stockage plutot que de rejouer une requete,
        // car le guard reste resolu en memoire pendant un meme test.
        $this->assertSame(0, $user->tokens()->count());
        $this->assertNull(PersonalAccessToken::findToken($token));
    }

    public function test_logout_works_when_the_request_is_authenticated_by_session(): void
    {
        // Regression : quand Sanctum authentifie via la session (guard "web"),
        // currentAccessToken() renvoie un TransientToken, qui n'a pas de
        // methode delete(). La deconnexion doit fonctionner malgre tout.
        User::factory()->create([
            'email' => 'utilisateur@example.com',
            'password' => 'password',
        ]);

        $this->postJson('/api/login', [
            'email' => 'utilisateur@example.com',
            'password' => 'password',
        ])->assertStatus(200);

        $this->postJson('/api/logout')->assertStatus(200);
    }

    public function test_an_invalid_token_is_rejected(): void
    {
        $response = $this->getJson('/api/me', [
            'Authorization' => 'Bearer token-invalide',
        ]);

        $response->assertStatus(401);
    }
}

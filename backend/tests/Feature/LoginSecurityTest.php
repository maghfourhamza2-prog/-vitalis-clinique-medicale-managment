<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SCRUM-510 - Securite de la connexion.
 *
 * Verifie que /api/login resiste a la force brute et ne permet pas de savoir
 * si une adresse email correspond a un compte existant.
 */
class LoginSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Les compteurs de limitation sont stockes dans le cache Redis du
        // conteneur : les <env> de phpunit.xml ne surchargent pas une vraie
        // variable d'environnement, et le RateLimiter est resolu au demarrage
        // de l'application, avant ce setUp. Les compteurs survivent donc d'un
        // test a l'autre.
        //
        // Chaque test se presente donc depuis une adresse IP differente, ce
        // qui lui donne ses propres compteurs et le rend independant.
        $this->withServerVariables([
            'REMOTE_ADDR' => sprintf(
                '10.%d.%d.%d',
                random_int(0, 255),
                random_int(0, 255),
                random_int(1, 254)
            ),
        ]);
    }

    private function tenterConnexion(string $email, string $motDePasse)
    {
        return $this->postJson('/api/login', [
            'email' => $email,
            'password' => $motDePasse,
        ]);
    }

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        User::factory()->create(['email' => 'cible@example.com']);

        // Les 5 premieres tentatives sont refusees normalement (401).
        for ($i = 1; $i <= 5; $i++) {
            $this->tenterConnexion('cible@example.com', 'mauvais-mot-de-passe')
                ->assertStatus(401);
        }

        // La 6e est bloquee par la limite de debit.
        $this->tenterConnexion('cible@example.com', 'mauvais-mot-de-passe')
            ->assertStatus(429);
    }

    public function test_rate_limited_response_tells_when_to_retry(): void
    {
        User::factory()->create(['email' => 'cible@example.com']);

        for ($i = 1; $i <= 5; $i++) {
            $this->tenterConnexion('cible@example.com', 'faux');
        }

        $response = $this->tenterConnexion('cible@example.com', 'faux');

        $response->assertStatus(429);
        $response->assertHeader('Retry-After');
    }

    public function test_blocking_one_account_does_not_block_another(): void
    {
        User::factory()->create(['email' => 'cible@example.com']);
        User::factory()->create(['email' => 'autre@example.com']);

        for ($i = 1; $i <= 6; $i++) {
            $this->tenterConnexion('cible@example.com', 'faux');
        }

        // Un autre compte reste utilisable : la limite par compte ne doit pas
        // servir a bloquer un utilisateur legitime.
        $this->tenterConnexion('autre@example.com', 'password')
            ->assertStatus(200);
    }

    public function test_a_valid_login_still_works_within_the_limit(): void
    {
        $user = User::factory()->create(['email' => 'valide@example.com']);

        $this->tenterConnexion('valide@example.com', 'mauvais')->assertStatus(401);

        $response = $this->tenterConnexion('valide@example.com', 'password');

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'email']]);
        $this->assertSame($user->id, $response->json('user.id'));
    }

    public function test_registration_is_rate_limited(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/register', [
                'name' => 'Utilisateur '.$i,
                'email' => "utilisateur{$i}@example.com",
                'password' => 'MotDePasse123',
                'password_confirmation' => 'MotDePasse123',
            ])->assertStatus(201);
        }

        $this->postJson('/api/register', [
            'name' => 'Utilisateur 6',
            'email' => 'utilisateur6@example.com',
            'password' => 'MotDePasse123',
            'password_confirmation' => 'MotDePasse123',
        ])->assertStatus(429);
    }

    public function test_forgot_password_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'cible@example.com']);

        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/forgot-password', ['email' => 'cible@example.com']);
        }

        $this->postJson('/api/forgot-password', ['email' => 'cible@example.com'])
            ->assertStatus(429);
    }

    public function test_error_message_does_not_reveal_whether_the_account_exists(): void
    {
        User::factory()->create(['email' => 'existe@example.com']);

        $compteExistant = $this->tenterConnexion('existe@example.com', 'mauvais-mot-de-passe');
        $compteInconnu = $this->tenterConnexion('inconnu@example.com', 'mauvais-mot-de-passe');

        // Meme code HTTP et meme message : impossible de deduire de la reponse
        // si l'adresse correspond a un compte.
        $compteExistant->assertStatus(401);
        $compteInconnu->assertStatus(401);
        $this->assertSame($compteInconnu->json(), $compteExistant->json());
    }

    public function test_no_token_is_issued_when_credentials_are_invalid(): void
    {
        User::factory()->create(['email' => 'existe@example.com']);

        $response = $this->tenterConnexion('existe@example.com', 'mauvais-mot-de-passe');

        $response->assertStatus(401);
        $this->assertArrayNotHasKey('token', $response->json());
        $this->assertSame(0, User::where('email', 'existe@example.com')->first()->tokens()->count());
    }

    public function test_same_account_is_rate_limited_across_multiple_ips(): void
    {
        User::factory()->create(['email' => 'distribue@example.com']);

        for ($i = 1; $i <= 5; $i++) {
            $this->withServerVariables([
                'REMOTE_ADDR' => "10.20.30.{$i}",
            ])->postJson('/api/login', [
                'email' => 'distribue@example.com',
                'password' => 'mauvais-mot-de-passe',
            ])->assertStatus(401);
        }

        $this->withServerVariables([
            'REMOTE_ADDR' => '10.20.30.99',
        ])->postJson('/api/login', [
            'email' => 'distribue@example.com',
            'password' => 'mauvais-mot-de-passe',
        ])->assertStatus(429);
    }


    public function test_password_reset_email_is_rate_limited_across_multiple_ips(): void
    {
        User::factory()->create(['email' => 'reset-distribue@example.com']);

        for ($i = 1; $i <= 5; $i++) {
            $this->withServerVariables([
                'REMOTE_ADDR' => "10.40.50.{$i}",
            ])->postJson('/api/forgot-password', [
                'email' => 'reset-distribue@example.com',
            ])->assertStatus(200);
        }

        $this->withServerVariables([
            'REMOTE_ADDR' => '10.40.50.99',
        ])->postJson('/api/forgot-password', [
            'email' => 'reset-distribue@example.com',
        ])->assertStatus(429);
    }

}

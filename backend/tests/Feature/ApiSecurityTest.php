<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * SCRUM-526 - Securisation de l'API.
 *
 * Ce fichier couvre les points qui n'etaient pas encore verifies par les
 * tickets precedents :
 *  - la limitation de debit appliquee a TOUTE l'API, et pas seulement aux
 *    routes d'authentification (SCRUM-510) ;
 *  - l'autorisation du referentiel medecins / specialites, jusqu'ici protege
 *    par une permission qui ne le concernait pas ;
 *  - l'impossibilite de deviner qui possede un compte via
 *    /api/forgot-password ;
 *  - l'impossibilite de choisir son role a l'inscription ;
 *  - l'expiration des jetons Sanctum.
 *
 * L'exposition de /api/health est traitee a part, dans
 * HealthCheckSecurityTest.
 */
class ApiSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Meme precaution que LoginSecurityTest (SCRUM-510) : si les compteurs
        // sont stockes dans un cache partage plutot qu'en memoire, une adresse
        // IP differente par test garantit que chaque test part de compteurs
        // vierges.
        $this->withServerVariables([
            'REMOTE_ADDR' => sprintf(
                '10.%d.%d.%d',
                random_int(0, 255),
                random_int(0, 255),
                random_int(1, 254)
            ),
        ]);
    }

    private function utilisateur(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    // ------------------------------------------------ limitation de debit

    /**
     * Avant SCRUM-526, seules les quatre routes publiques d'authentification
     * etaient limitees. Les en-tetes prouvent que le limiteur s'applique
     * desormais aussi aux routes metier.
     */
    public function test_les_routes_metier_annoncent_une_limite_de_debit(): void
    {
        Sanctum::actingAs($this->utilisateur('secretaire'));

        $reponse = $this->getJson('/api/patients');

        $reponse->assertStatus(200)
            ->assertHeader('X-RateLimit-Limit', '60');

        $this->assertNotNull($reponse->headers->get('X-RateLimit-Remaining'));
    }

    public function test_lapi_repond_429_au_dela_de_la_limite(): void
    {
        Sanctum::actingAs($this->utilisateur('secretaire'));

        for ($i = 1; $i <= 60; $i++) {
            $this->getJson('/api/me')->assertStatus(200);
        }

        $this->getJson('/api/me')->assertStatus(429);
    }

    public function test_la_reponse_429_indique_quand_reessayer(): void
    {
        Sanctum::actingAs($this->utilisateur('secretaire'));

        for ($i = 1; $i <= 60; $i++) {
            $this->getJson('/api/me');
        }

        $reponse = $this->getJson('/api/me');

        $reponse->assertStatus(429);
        $this->assertNotNull($reponse->headers->get('Retry-After'));
    }

    /**
     * Le compteur suit l'utilisateur authentifie, pas l'adresse IP : tout le
     * personnel de la clinique sort par la meme connexion internet, un agent
     * qui atteint la limite ne doit pas bloquer ses collegues.
     */
    public function test_un_utilisateur_bloque_ne_bloque_pas_les_autres(): void
    {
        $premier = $this->utilisateur('secretaire');
        $second = $this->utilisateur('secretaire');

        $jetonPremier = $premier->createToken('test')->plainTextToken;
        $jetonSecond = $second->createToken('test')->plainTextToken;

        for ($i = 1; $i <= 61; $i++) {
            $this->requeteAvecJeton($jetonPremier);
        }

        $this->requeteAvecJeton($jetonPremier)->assertStatus(429);
        $this->requeteAvecJeton($jetonSecond)->assertStatus(200);
    }

    /**
     * Appelle /api/me avec un jeton donne, en repartant d'un garde vierge.
     *
     * En production chaque requete HTTP demarre une application neuve. Dans un
     * test, l'application est reutilisee et le garde Sanctum (un RequestGuard)
     * memorise le premier utilisateur qu'il a resolu : sans cet oubli
     * explicite, toutes les requetes du test seraient attribuees au meme
     * compte et l'assertion ne prouverait rien.
     */
    private function requeteAvecJeton(string $jeton): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', "Bearer {$jeton}")
            ->getJson('/api/me');
    }

    // -------------------------- autorisation medecins et specialites

    /**
     * @return array<string, array{string, string}>
     */
    public static function routesDuReferentiel(): array
    {
        return [
            'liste des medecins' => ['get', '/api/medecins'],
            'fiche medecin' => ['get', '/api/medecins/1'],
            'creation medecin' => ['post', '/api/medecins'],
            'modification medecin' => ['put', '/api/medecins/1'],
            'suppression medecin' => ['delete', '/api/medecins/1'],
            'liste des specialites' => ['get', '/api/specialites'],
            'fiche specialite' => ['get', '/api/specialites/1'],
            'creation specialite' => ['post', '/api/specialites'],
            'modification specialite' => ['put', '/api/specialites/1'],
            'suppression specialite' => ['delete', '/api/specialites/1'],
        ];
    }

    #[DataProvider('routesDuReferentiel')]
    public function test_le_referentiel_refuse_un_visiteur_non_authentifie(string $methode, string $uri): void
    {
        $this->json(strtoupper($methode), $uri)->assertStatus(401);
    }

    /**
     * Aucune route n'expose le referentiel au role patient : il n'a donc
     * aucune des permissions correspondantes.
     */
    #[DataProvider('routesDuReferentiel')]
    public function test_le_referentiel_refuse_le_role_patient(string $methode, string $uri): void
    {
        Sanctum::actingAs($this->utilisateur('patient'));

        $this->json(strtoupper($methode), $uri)->assertStatus(403);
    }

    /**
     * Le personnel consulte l'annuaire : c'est necessaire pour orienter un
     * patient vers le bon medecin. Avant SCRUM-526 ces routes etaient dans le
     * groupe 'can:roles.manage' et repondaient 403 a tout le monde sauf a
     * l'administrateur.
     */
    #[DataProvider('rolesDuPersonnel')]
    public function test_le_personnel_peut_consulter_le_referentiel(string $role): void
    {
        Sanctum::actingAs($this->utilisateur($role));

        $this->getJson('/api/medecins')->assertStatus(200);
        $this->getJson('/api/specialites')->assertStatus(200);
    }

    public static function rolesDuPersonnel(): array
    {
        return [
            'administrateur' => ['administrateur'],
            'medecin' => ['medecin'],
            'secretaire' => ['secretaire'],
            'infirmier' => ['infirmier'],
        ];
    }

    /**
     * La lecture s'ouvre au personnel, l'ecriture reste a l'administrateur :
     * le referentiel ne doit pas devenir modifiable par tous au passage.
     *
     * @return array<string, array{string, string}>
     */
    public static function ecrituresDuReferentiel(): array
    {
        return [
            'creation medecin' => ['post', '/api/medecins'],
            'modification medecin' => ['put', '/api/medecins/1'],
            'suppression medecin' => ['delete', '/api/medecins/1'],
            'creation specialite' => ['post', '/api/specialites'],
            'modification specialite' => ['put', '/api/specialites/1'],
            'suppression specialite' => ['delete', '/api/specialites/1'],
        ];
    }

    #[DataProvider('ecrituresDuReferentiel')]
    public function test_le_personnel_non_administrateur_ne_modifie_pas_le_referentiel(string $methode, string $uri): void
    {
        foreach (['medecin', 'secretaire', 'infirmier'] as $role) {
            Sanctum::actingAs($this->utilisateur($role));

            $this->json(strtoupper($methode), $uri)->assertStatus(403);
        }
    }

    #[DataProvider('ecrituresDuReferentiel')]
    public function test_ladministrateur_passe_le_controle_de_permission(string $methode, string $uri): void
    {
        Sanctum::actingAs($this->utilisateur('administrateur'));

        $reponse = $this->json(strtoupper($methode), $uri);

        // La ressource 1 n'existe pas et le corps est vide : la reponse sera
        // un 404 ou un 422. Seul compte ici le fait que ce ne soit pas un 403.
        $this->assertNotSame(403, $reponse->status());
    }

    /**
     * Le referentiel ne doit pas rouvrir la gestion des comptes : donner
     * 'medecins.read' au personnel ne doit pas lui donner 'roles.manage'.
     */
    public function test_lacces_au_referentiel_nouvre_pas_la_gestion_des_roles(): void
    {
        Sanctum::actingAs($this->utilisateur('secretaire'));

        $this->getJson('/api/medecins')->assertStatus(200);
        $this->getJson('/api/roles')->assertStatus(403);
    }

    // ------------------------------ enumeration des comptes existants

    /**
     * Le meme raisonnement que pour /api/login (SCRUM-510) : la reponse ne
     * doit pas permettre de savoir si une adresse correspond a un compte.
     */
    public function test_forgot_password_repond_a_lidentique_pour_un_compte_inconnu(): void
    {
        User::factory()->create(['email' => 'connu@example.com']);

        $connu = $this->postJson('/api/forgot-password', ['email' => 'connu@example.com']);
        $inconnu = $this->postJson('/api/forgot-password', ['email' => 'inconnu@example.com']);

        $this->assertSame($connu->status(), $inconnu->status());
        $this->assertSame(200, $connu->status());
        $this->assertSame($connu->json('message'), $inconnu->json('message'));
    }

    public function test_forgot_password_ne_revele_pas_lechec_de_lenvoi(): void
    {
        $reponse = $this->postJson('/api/forgot-password', ['email' => 'personne@example.com']);

        $corps = $reponse->getContent();

        $this->assertStringNotContainsString('Impossible', $corps);
        $this->assertStringNotContainsStringIgnoringCase('introuvable', $corps);
        $this->assertStringNotContainsStringIgnoringCase('user', $corps);
    }

    // ------------------------------ elevation de privileges a l inscription

    public function test_linscription_ne_permet_pas_de_choisir_son_role(): void
    {
        $reponse = $this->postJson('/api/register', [
            'name' => 'Visiteur Malveillant',
            'email' => 'escalade@example.com',
            'password' => 'MotDePasse123',
            'password_confirmation' => 'MotDePasse123',
            'role' => 'administrateur',
        ]);

        $reponse->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'escalade@example.com',
            'role' => 'patient',
        ]);
    }

    public function test_un_compte_cree_librement_na_pas_acces_au_dossier_patients(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Visiteur',
            'email' => 'visiteur@example.com',
            'password' => 'MotDePasse123',
            'password_confirmation' => 'MotDePasse123',
            'role' => 'administrateur',
        ])->assertStatus(201);

        $nouveau = User::where('email', 'visiteur@example.com')->firstOrFail();

        Sanctum::actingAs($nouveau);

        $this->getJson('/api/patients')->assertStatus(403);
        $this->getJson('/api/roles')->assertStatus(403);
        $this->getJson('/api/medecins')->assertStatus(403);
    }

    // ------------------------------------------- duree de vie des jetons

    public function test_les_jetons_ont_une_duree_de_vie_bornee(): void
    {
        $expiration = config('sanctum.expiration');

        $this->assertNotNull(
            $expiration,
            'Un jeton sans expiration reste valable indefiniment en cas de vol.'
        );
        $this->assertGreaterThan(0, $expiration);
    }

    public function test_un_jeton_expire_est_refuse(): void
    {
        $user = $this->utilisateur('secretaire');
        $jeton = $user->createToken('test')->plainTextToken;

        // Le jeton fonctionne tant qu'il est dans la fenetre de validite.
        $this->requeteAvecJeton($jeton)->assertStatus(200);

        // On le fait vieillir au-dela de la duree configuree.
        $user->tokens()->update([
            'created_at' => now()->subMinutes((int) config('sanctum.expiration') + 1),
        ]);

        // Voir requeteAvecJeton() : le garde a memorise l'utilisateur resolu
        // lors de la requete precedente, il faut le reinitialiser pour que le
        // jeton soit reellement reexamine.
        $this->requeteAvecJeton($jeton)->assertStatus(401);
    }

    public function test_lannuaire_medecin_minimise_les_champs_rh_pour_le_personnel(): void
    {
        $idMedecin = $this->creerMedecin('Confidentiel');

        Sanctum::actingAs($this->utilisateur('secretaire'));

        $reponse = $this->getJson('/api/medecins/'.$idMedecin)
            ->assertStatus(200);

        $reponse->assertJsonPath('medecin.nom', 'Confidentiel');

        foreach (['matricule', 'date_embauche', 'tarif_consultation'] as $champ) {
            $reponse->assertJsonMissingPath('medecin.'.$champ);
        }
    }

    public function test_ladministrateur_garde_les_champs_de_gestion_du_medecin(): void
    {
        $idMedecin = $this->creerMedecin('Administratif');

        Sanctum::actingAs($this->utilisateur('administrateur'));

        $reponse = $this->getJson('/api/medecins/'.$idMedecin)
            ->assertStatus(200);

        $this->assertNotNull($reponse->json('medecin.matricule'));
        $this->assertNotNull($reponse->json('medecin.date_embauche'));
        $this->assertNotNull($reponse->json('medecin.tarif_consultation'));
    }

}

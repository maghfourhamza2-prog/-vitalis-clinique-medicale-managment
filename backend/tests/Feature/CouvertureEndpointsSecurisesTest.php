<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * SCRUM-530 - Couverture des endpoints securises.
 *
 * Les suites precedentes verifient chacune un aspect sur un ECHANTILLON de
 * routes ecrit a la main : AccesNonAutorisesTest et PermissionTest pour les
 * patients et les roles, EndpointsSensiblesTest pour le tableau de bord,
 * ApiSecurityTest pour le referentiel, AutorisationsRequetesTest pour le
 * cloisonnement par dossier, Reponses401Et403Test pour la forme des reponses.
 *
 * Le probleme d'un echantillon ecrit a la main est qu'il vieillit : une route
 * ajoutee plus tard n'y entre pas toute seule. La mesure faite pour ce ticket
 * l'a confirme - en neutralisant chaque permission une par une et en relancant
 * la suite, une seule n'etait detectee par aucun test : 'patients.create'.
 * POST /api/patients n'avait qu'un test de succes (secretaire) et un test 401,
 * rien ne verifiait le refus oppose a un role sans la permission.
 *
 * Cette suite ne rejoue donc pas les scenarios existants : elle parcourt le
 * ROUTEUR au moment de l'execution et confronte chaque route protegee a la
 * matrice des roles issue de l'enum Role. Elle reste juste quand des routes
 * sont ajoutees, et signale immediatement une route exposee sans protection.
 *
 * Le cloisonnement par proprietaire n'est pas repris ici : il est deja couvert
 * par AutorisationsRequetesTest (dossiers medicaux) et EndpointsSensiblesTest
 * (agenda du medecin), verifie par mutation.
 */
class CouvertureEndpointsSecurisesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Routes volontairement accessibles sans jeton.
     *
     * Toute route d'API absente de cette liste doit exiger une
     * authentification. La liste est courte et doit le rester : l'ajout d'une
     * entree est une decision de securite, pas un detail d'implementation.
     */
    private const ROUTES_PUBLIQUES = [
        'api/login',
        'api/register',
        'api/forgot-password',
        'api/reset-password',
        'api/health',
    ];

    /**
     * Routes protegees par l'authentification seule, sans permission.
     *
     * Chacune ne renvoie que des donnees propres a l'appelant, ou choisit
     * elle-meme son contenu selon son role : il n'y a donc rien a cloisonner
     * par permission.
     */
    /**
     * Permissions detenues par TOUS les roles, protegees par l'appartenance.
     *
     * SCRUM-605 - Le balayage ci-dessous exige normalement qu'au moins un
     * role se voie refuser chaque permission : une permission que tout le
     * monde detient ne filtre rien. La lecture des rendez-vous est une
     * exception assumee - chacun consulte son agenda, y compris le patient -
     * mais elle n'est alors PLUS protegee par le role : seul le cloisonnement
     * par proprietaire la protege.
     *
     * Y inscrire une permission est donc un engagement : son cloisonnement
     * doit etre teste. Celui des rendez-vous l'est dans RendezVousAccesTest.
     */
    private const PERMISSIONS_CLOISONNEES_PAR_PROPRIETAIRE = [
        'rendez-vous.read',
    ];

    private const SANS_PERMISSION_ASSUMEE = [
        'api/logout',             // revoque le jeton de l'appelant
        'api/logout-all',         // revoque toutes les sessions de l'appelant
        'api/sessions',           // liste uniquement les sessions de l'appelant
        'api/sessions/{tokenId}', // revoque uniquement une session de l'appelant
        'api/me',                 // renvoie le compte de l'appelant
        'api/dashboard',          // DashboardController aiguille selon le role
    ];

    /**
     * Toutes les routes d'API, avec leur protection reelle.
     *
     * @return array<int, array{methode: string, uri: string, auth: bool, permission: string|null}>
     */
    private function routesDeLApi(): array
    {
        $routes = [];

        foreach (app('router')->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/')) {
                continue;
            }

            $middleware = implode(',', array_filter($route->gatherMiddleware(), 'is_string'));
            // SCRUM-605 : le tiret manquait dans la classe de caracteres. Une
            // permission comme 'rendez-vous.read' etait lue 'rendez', et ce
            // balayage ne verifiait donc rien sur ces routes.
            preg_match('/can:([a-zA-Z.\-]+)/', $middleware, $correspondance);

            $routes[] = [
                'methode' => $route->methods()[0],
                'uri' => $route->uri(),
                'auth' => str_contains($middleware, 'auth:sanctum'),
                'permission' => $correspondance[1] ?? null,
            ];
        }

        return $routes;
    }

    /**
     * Remplace les parametres d'URL par des identifiants reels, pour que la
     * requete atteigne un vrai dossier plutot qu'un 404 systematique.
     */
    private function uriConcrete(string $uri, array $identifiants): string
    {
        return match (true) {
            str_contains($uri, 'api/patients/{id}') => str_replace('{id}', (string) $identifiants['patient'], $uri),
            str_contains($uri, 'api/medecins/{id}') => str_replace('{id}', (string) $identifiants['medecin'], $uri),
            str_contains($uri, 'api/specialites/{id}') => str_replace('{id}', (string) $identifiants['specialite'], $uri),
            str_contains($uri, 'api/users/{user}') => str_replace('{user}', (string) $identifiants['cible'], $uri),
            str_contains($uri, 'api/rendez-vous/{id}') => str_replace('{id}', (string) $identifiants['rendez_vous'], $uri),
            default => $uri,
        };
    }

    /**
     * Cree un jeu de donnees minimal et renvoie les identifiants utilisables
     * dans les URL.
     *
     * @return array<string, int>
     */
    private function jeuDeDonnees(): array
    {
        $patient = Patient::create([
            'nom' => 'Alaoui',
            'prenom' => 'Fatima',
            'date_naissance' => '1990-01-01',
            'sexe' => 'F',
            'cin' => 'CIN'.uniqid(),
            'telephone' => '0600000000',
            'email' => 'patient'.uniqid().'@example.com',
            'groupe_sanguin' => 'O+',
        ]);

        $idSpecialite = DB::table('specialites')->insertGetId([
            'nom_specialite' => 'Cardiologie'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_specialite');

        $idMedecin = DB::table('medecins')->insertGetId([
            'matricule' => 'MAT'.uniqid(),
            'nom' => 'Bennani',
            'prenom' => 'Docteur',
            'telephone' => '0600000000',
            'email' => 'medecin'.uniqid().'@example.com',
            'date_embauche' => '2020-01-01',
            'tarif_consultation' => 300,
            'id_specialite' => $idSpecialite,
            'created_at' => now(),
            'updated_at' => now(),
        ], 'id_medecin');

        $rendezVous = \App\Models\RendezVous::create([
            'date_rendez_vous' => now()->addDay()->toDateString(),
            'heure_debut' => '09:00:00',
            'heure_fin' => '09:30:00',
            'motif' => 'Controle',
            'statut' => 'confirme',
            'id_patient' => $patient->id_patient,
            'id_medecin' => $idMedecin,
        ]);

        return [
            'patient' => $patient->id_patient,
            'medecin' => $idMedecin,
            'specialite' => $idSpecialite,
            'rendez_vous' => $rendezVous->id_rendez_vous,
            'cible' => User::factory()->create(['role' => 'patient'])->id,
        ];
    }

    /**
     * Premier role qui NE detient PAS la permission donnee.
     */
    private function roleSansLaPermission(string $permission): ?Role
    {
        $cible = Permission::tryFrom($permission);

        if ($cible === null) {
            return null;
        }

        foreach (Role::cases() as $role) {
            if (! $role->accorde($cible)) {
                return $role;
            }
        }

        return null;
    }

    // ------------------------------------------------------------------

    /**
     * Garde-fou : aucune route d'API ne doit etre exposee sans
     * authentification en dehors de la liste assumee.
     *
     * C'est le test qui protege les suivants du vieillissement : une route
     * ajoutee sans auth:sanctum echoue ici, meme si personne n'a pense a
     * ecrire un test pour elle.
     */
    public function test_aucune_route_dapi_nest_exposee_sans_authentification(): void
    {
        $exposees = [];

        foreach ($this->routesDeLApi() as $route) {
            if (! $route['auth'] && ! in_array($route['uri'], self::ROUTES_PUBLIQUES, true)) {
                $exposees[] = $route['methode'].' '.$route['uri'];
            }
        }

        $this->assertSame(
            [],
            $exposees,
            "Ces routes sont accessibles sans jeton sans figurer dans la liste des routes publiques assumees :\n".
            implode("\n", $exposees)
        );
    }

    /**
     * Contrepartie : la liste des routes publiques ne doit pas contenir
     * d'entree devenue inutile, qui laisserait une porte ouverte par oubli.
     */
    public function test_la_liste_des_routes_publiques_ne_contient_rien_dobsolete(): void
    {
        $uris = array_column($this->routesDeLApi(), 'uri');

        foreach (self::ROUTES_PUBLIQUES as $publique) {
            $this->assertContains(
                $publique,
                $uris,
                "La route publique assumee {$publique} n'existe plus : retirez-la de la liste."
            );
        }
    }

    /**
     * Toute route protegee doit exiger une permission, sauf celles dont
     * l'absence est assumee et documentee.
     */
    public function test_toute_route_protegee_porte_une_permission_ou_est_assumee(): void
    {
        $sansControle = [];

        foreach ($this->routesDeLApi() as $route) {
            if (! $route['auth']) {
                continue;
            }

            if ($route['permission'] === null && ! in_array($route['uri'], self::SANS_PERMISSION_ASSUMEE, true)) {
                $sansControle[] = $route['methode'].' '.$route['uri'];
            }
        }

        $this->assertSame(
            [],
            $sansControle,
            "Ces routes n'exigent qu'un compte valide, sans permission :\n".implode("\n", $sansControle)
        );
    }

    // ------------------------------------------------------------------

    /**
     * Balayage 401 : toute route protegee refuse un visiteur sans jeton.
     *
     * Les suites existantes couvraient un echantillon ecrit a la main ; ce
     * balayage est derive du routeur et couvre donc aussi les routes a venir.
     */
    public function test_toute_route_protegee_refuse_un_visiteur_non_authentifie(): void
    {
        $identifiants = $this->jeuDeDonnees();
        $verifiees = 0;

        foreach ($this->routesDeLApi() as $route) {
            if (! $route['auth']) {
                continue;
            }

            $uri = $this->uriConcrete($route['uri'], $identifiants);

            $this->json($route['methode'], '/'.$uri)->assertStatus(401);
            $verifiees++;
        }

        $this->assertGreaterThan(20, $verifiees, 'Le balayage devrait couvrir toutes les routes protegees.');
    }

    /**
     * Balayage 403 : pour chaque route portant une permission, un role qui ne
     * la detient pas est refuse.
     *
     * C'est ce balayage qui comble la lacune mesuree sur 'patients.create'.
     */
    public function test_toute_route_a_permission_refuse_un_role_qui_ne_la_detient_pas(): void
    {
        $identifiants = $this->jeuDeDonnees();
        $verifiees = 0;

        foreach ($this->routesDeLApi() as $route) {
            if ($route['permission'] === null) {
                continue;
            }

            if (in_array($route['permission'], self::PERMISSIONS_CLOISONNEES_PAR_PROPRIETAIRE, true)) {
                // Protegee par l'appartenance, pas par le role : voir la
                // constante et RendezVousAccesTest.
                continue;
            }

            $role = $this->roleSansLaPermission($route['permission']);

            $this->assertNotNull(
                $role,
                "Aucun role ne se voit refuser {$route['permission']} : la permission ne protege rien. "
                ."Si c'est volontaire, inscrivez-la dans PERMISSIONS_CLOISONNEES_PAR_PROPRIETAIRE "
                ."et testez son cloisonnement."
            );

            $this->app['auth']->forgetGuards();
            Sanctum::actingAs(User::factory()->create(['role' => $role->value]));

            $uri = $this->uriConcrete($route['uri'], $identifiants);

            $this->json($route['methode'], '/'.$uri)
                ->assertStatus(403);

            $verifiees++;
        }

        $this->assertGreaterThan(20, $verifiees);
    }

    /**
     * Balayage du chemin nominal : un role qui detient la permission n'est
     * bloque ni par l'authentification ni par l'autorisation.
     *
     * On n'exige pas un code de succes precis : sans corps de requete, une
     * ecriture repond legitimement 422. Ce qui compte est l'absence de 401
     * et de 403, c'est-a-dire que le controle d'acces laisse passer.
     */
    public function test_toute_route_a_permission_accepte_un_role_qui_la_detient(): void
    {
        $verifiees = 0;

        foreach ($this->routesDeLApi() as $route) {
            if ($route['permission'] === null) {
                continue;
            }

            // Jeu de donnees recree a chaque iteration : les routes DELETE du
            // balayage suppriment reellement la ressource visee.
            $identifiants = $this->jeuDeDonnees();

            $this->app['auth']->forgetGuards();
            Sanctum::actingAs(User::factory()->create(['role' => 'administrateur']));

            $uri = $this->uriConcrete($route['uri'], $identifiants);
            $reponse = $this->json($route['methode'], '/'.$uri);

            $this->assertNotContains(
                $reponse->status(),
                [401, 403],
                "L'administrateur devrait passer le controle d'acces sur {$route['methode']} {$route['uri']}, "
                ."recu {$reponse->status()}."
            );

            $verifiees++;
        }

        $this->assertGreaterThan(20, $verifiees);
    }

    // ------------------------------------------------------------------

    /**
     * Lacune reelle mesuree pour SCRUM-530, ecrite explicitement pour rester
     * lisible : la creation d'un dossier patient.
     *
     * Neutraliser 'patients.create' ne faisait echouer aucun test : le seul
     * test de cette route (PatientStoreTest) verifie le chemin nominal avec
     * une secretaire, et Reponses401Et403Test n'y verifie que le 401.
     */
    public function test_la_creation_dun_patient_est_refusee_au_role_patient(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'patient']));

        $this->postJson('/api/patients', [
            'nom' => 'Intrus',
            'prenom' => 'Tentative',
            'date_naissance' => '1990-01-01',
            'sexe' => 'F',
            'cin' => 'CIN999999',
            'telephone' => '0600000000',
            'email' => 'intrus@example.com',
            'groupe_sanguin' => 'O+',
        ])->assertStatus(403);

        $this->assertDatabaseMissing('patients', ['cin' => 'CIN999999']);
    }

    /**
     * Contrepartie : le personnel autorise cree bien un dossier. Sans cette
     * verification, retirer la permission a tout le monde ferait passer le
     * test precedent.
     */
    public function test_la_creation_dun_patient_reste_ouverte_au_personnel(): void
    {
        foreach (['administrateur', 'medecin', 'secretaire', 'infirmier'] as $role) {
            $this->app['auth']->forgetGuards();
            Sanctum::actingAs(User::factory()->create(['role' => $role]));

            $this->postJson('/api/patients', [
                'nom' => 'Dossier',
                'prenom' => 'Cree par '.$role,
                'date_naissance' => '1990-01-01',
                'sexe' => 'F',
                'cin' => 'CIN'.uniqid(),
                'telephone' => '0600000000',
                'email' => $role.random_int(1000, 9999).'@example.com',
                'groupe_sanguin' => 'O+',
            ])->assertStatus(201);
        }
    }
}

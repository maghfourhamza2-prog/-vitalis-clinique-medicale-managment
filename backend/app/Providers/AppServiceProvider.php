<?php

namespace App\Providers;

use App\Auth\PerimetreDossier;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Access\Response as ReponseAutorisation;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configurerLimitesAuthentification();
        $this->declarerPermissions();

        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            // SCRUM-540 : lecture via config() et non env(). En production
            // l'entrypoint execute `config:cache` ; env() hors d'un fichier
            // de configuration est alors une source de surprises. La valeur
            // est resolue une seule fois dans config/app.php.
            $frontendUrl = config('app.frontend_url');

            return $frontendUrl.'/reset-password?token='.$token.'&email='.urlencode($notifiable->getEmailForPasswordReset());
        });
    }

    /**
     * SCRUM-510 - Limites de debit sur les routes d'authentification publiques.
     *
     * Sans ces limites, /api/login accepte un nombre illimite de tentatives :
     * un mot de passe peut donc etre devine par force brute. Une fois la limite
     * atteinte, Laravel repond 429 avec un en-tete Retry-After.
     *
     * SCRUM-526 - S'y ajoute le limiteur 'api', applique a toutes les routes.
     */
    private function configurerLimitesAuthentification(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return [
                // Limite par compte, independamment de l'adresse IP : une
                // attaque distribuee ne peut pas contourner la limite en
                // changeant de relais. Le hash evite de mettre l'email brut
                // dans la cle de cache.
                Limit::perMinute(5)->by(
                    'login-email:'.hash('sha256', $email)
                ),
                // Limite par adresse IP : ralentit le balayage de nombreux
                // comptes depuis une meme source.
                Limit::perMinute(20)->by('login-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('password-reset-request', function (Request $request) {
            $email = Str::lower((string) $request->input('email'));

            return [
                // Evite le spam de liens de reinitialisation vers une meme
                // boite, meme si l'attaquant change d'adresse IP.
                Limit::perMinute(5)->by(
                    'reset-email:'.hash('sha256', $email)
                ),
                Limit::perMinute(10)->by('reset-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('auth-public', function (Request $request) {
            // Inscription, demande et confirmation de reinitialisation : limite
            // la creation de comptes en masse, l'envoi massif d'emails et les
            // essais de jetons de reinitialisation.
            return Limit::perMinute(5)->by($request->ip());
        });

        // SCRUM-526 - Limiteur applique a l'ensemble de l'API (voir
        // bootstrap/app.php).
        //
        // Les limiteurs de SCRUM-510 ne couvrent que la connexion et les
        // routes publiques d'authentification. Celui-ci protege tout le reste :
        // un jeton vole ou un compte legitime detourne ne peut plus enumerer
        // /api/patients ni marteler le serveur sans etre ralenti.
        //
        // La cle est l'identifiant de l'utilisateur quand il est authentifie :
        // deux membres du personnel derriere la meme connexion internet ne se
        // penalisent donc pas mutuellement. On retombe sur l'adresse IP pour
        // les requetes anonymes (health check, login deja limite par ailleurs).
        //
        // On interroge explicitement le garde 'sanctum' : le limiteur s'execute
        // AVANT le middleware auth:sanctum, donc $request->user() renverrait
        // null pour une requete portant un jeton Bearer et tout le personnel
        // partagerait le compteur de l'adresse IP de la clinique. Le garde
        // memorise l'utilisateur resolu, l'authentification n'a donc pas lieu
        // deux fois.
        //
        // 60 requetes/minute laisse largement passer l'usage normal du
        // frontend Vue (chargement d'un tableau de bord, pagination, recherche)
        // tout en coupant court a un script automatise.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(
                Auth::guard('sanctum')->id() ?: $request->ip()
            );
        });
    }

    /**
     * SCRUM-518 - Declare une Gate par permission.
     *
     * Chaque permission devient une Gate portant son propre nom, ce qui permet
     * d'utiliser le middleware natif `can:` sur les routes. Il n'y a donc pas
     * de middleware supplementaire a ecrire ni de table a creer : la reponse
     * vient de la correspondance role -> permissions definie dans l'enum Role.
     *
     * SCRUM-528 - Ces Gates acceptent desormais un dossier optionnel et
     * verifient, le cas echeant, que l'utilisateur en fait bien partie. Voir
     * App\Auth\PerimetreDossier.
     */
    private function declarerPermissions(): void
    {
        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, function (User $user, ?object $dossier = null) use ($permission) {
                $role = Role::tryFrom((string) $user->role);

                // 1. Le role accorde-t-il la permission ? (SCRUM-518)
                //
                // Le message est conserve a l'identique pour que les clients
                // existants voient la meme reponse 403 qu'avec le middleware
                // 'role' utilise jusqu'ici.
                if ($role === null || ! $role->accorde($permission)) {
                    return ReponseAutorisation::deny('Accès interdit');
                }

                // 2. SCRUM-528 - Et sur CE dossier ?
                //
                // Deuxieme question, distincte de la premiere : un medecin a
                // le droit de lire une consultation, mais pas n'importe
                // laquelle. Tant que l'appelant ne designe aucun dossier
                // ($dossier vaut null), il n'y a rien a cloisonner et le
                // comportement reste celui d'avant : c'est le cas de toutes
                // les routes actuelles, qui utilisent 'can:permission' sans
                // argument. Des qu'une route passera un modele - via
                // 'can:consultations.read,consultation' et le model binding -
                // l'appartenance sera verifiee ici, sans rien changer aux
                // routes existantes.
                if ($dossier !== null && ! PerimetreDossier::accessible($user, $dossier)) {
                    return ReponseAutorisation::deny('Accès interdit');
                }

                return ReponseAutorisation::allow();
            });
        }
    }
}
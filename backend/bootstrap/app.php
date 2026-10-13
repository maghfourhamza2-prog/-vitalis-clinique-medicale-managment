<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'account.active' => \App\Http\Middleware\EnsureAccountIsActive::class,
            'audit.api' => \App\Http\Middleware\AuditApiActivity::class,
        ]);

        // SCRUM-526 - Limitation de debit sur TOUTE l'API.
        //
        // Depuis Laravel 11, le groupe de middleware 'api' ne contient plus de
        // limiteur par defaut : il faut l'activer explicitement. Sans cet
        // appel, seules les quatre routes d'authentification etaient limitees
        // (SCRUM-510) et le reste de l'API acceptait un nombre illimite de
        // requetes par minute - de quoi aspirer le fichier patients ou saturer
        // le serveur avec un seul jeton valide.
        //
        // Le limiteur 'api' est defini dans AppServiceProvider.
        //
        // Portee exacte, verifiee par les tests : Laravel ordonne les
        // middleware par priorite et place l'authentification AVANT la
        // limitation. Une requete sans jeton sur une route protegee est donc
        // rejetee en 401 sans etre comptee - un refus qui ne coute presque
        // rien au serveur. Les routes publiques sensibles ne dependent pas de
        // ce limiteur : /api/login et les routes de mot de passe ont deja les
        // leurs, plus stricts (SCRUM-510).
        $middleware->throttleApi('api');

        // SCRUM-529 - Ne pas rediriger un visiteur non authentifie.
        //
        // Laravel applique par defaut redirectGuestsTo(fn () => route('login')).
        // Ce backend n'expose que des routes d'API et ne declare aucune route
        // nommee 'login' : le middleware auth appelait donc route('login') en
        // CONSTRUISANT son exception, ce qui levait RouteNotFoundException
        // avant meme qu'AuthenticationException n'existe. Le 401 attendu
        // devenait un 500 "Route [login] not defined.".
        //
        // Le declencheur etait l'absence d'attente de JSON : le client Vue
        // envoie toujours Accept: application/json et recevait un 401 correct,
        // ce qui masquait le probleme depuis l'application. Un navigateur, un
        // curl sans en-tete ou une sonde de supervision recevait une erreur
        // serveur la ou il fallait lire "authentifiez-vous".
        //
        // Rediriger n'a de toute facon pas de sens pour une API : il n'y a
        // aucune page de connexion a atteindre, c'est au client de reagir
        // au 401.
        $middleware->redirectGuestsTo(null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // SCRUM-529 - Reponse 401 unique pour toute l'API.
        //
        // Sans ce rendu, Laravel renvoie son message par defaut
        // "Unauthenticated." (anglais) pendant que les Gates repondent
        // "Accès interdit" (francais) : deux langues pour la meme API.
        //
        // La structure est identique a celle du 403 - un seul champ
        // 'message' - pour qu'un client puisse ecrire un unique gestionnaire
        // d'erreur. Le message reste volontairement generique et ne distingue
        // pas jeton absent, invalide ou expire : cette information n'aiderait
        // que celui qui essaie des jetons.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Non authentifié',
                ], 401);
            }
        });
    })->create();

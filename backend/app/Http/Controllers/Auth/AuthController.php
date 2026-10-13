<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /**
     * SCRUM-511 - Regles de validation d'une adresse email.
     *
     * `max:255` correspond a la taille de la colonne en base et evite qu'une
     * requete transporte une chaine de taille arbitraire.
     *
     * @return array<int, string>
     */
    private function reglesEmail(): array
    {
        return ['required', 'string', 'email', 'max:255'];
    }

    /**
     * SCRUM-511 - Regles d'un NOUVEAU mot de passe (inscription, reinitialisation).
     *
     * Au moins 8 caracteres, avec des lettres et des chiffres : cela ecarte les
     * mots de passe les plus faciles a deviner. La borne haute evite un envoi
     * de taille arbitraire (bcrypt ne prend de toute facon en compte que les
     * 72 premiers octets).
     *
     * @return array<int, mixed>
     */
    private function reglesNouveauMotDePasse(): array
    {
        return [
            'required',
            'string',
            'max:255',
            'confirmed',
            Password::min(8)->letters()->numbers(),
        ];
    }

    public function register(Request $request)
    {
        if (! (bool) config('security.allow_public_registration', false)) {
            return response()->json([
                'message' => 'L\'inscription publique est désactivée. Contactez l\'administrateur.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => [...$this->reglesEmail(), 'unique:users,email'],
            'password' => $this->reglesNouveauMotDePasse(),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        // SCRUM-526 : le role est impose par le serveur, jamais lu dans la
        // requete. L'inscription est publique : sans cette ligne, la seule
        // chose qui empeche un visiteur de se declarer administrateur est la
        // valeur par defaut de la colonne en base - une protection invisible
        // ici, qu'une future refonte du controller ferait disparaitre sans
        // que personne ne le remarque. Un compte cree librement demarre donc
        // toujours avec le role le moins privilegie ; c'est ensuite a un
        // administrateur de l'elever via PUT /api/users/{user}/role.
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => Role::PATIENT->value,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Inscription réussie',
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    public function login(Request $request)
    {
        // SCRUM-511 : a la connexion on borne les entrees, mais on n'applique
        // pas les regles de robustesse. Elles ne concernent que la creation
        // d'un mot de passe : les appliquer ici revelerait la politique de
        // l'application et bloquerait les comptes plus anciens.
        $validator = Validator::make($request->all(), [
            'email' => $this->reglesEmail(),
            'password' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (! Auth::attempt($request->only('email', 'password'))) {
            // SCRUM-510 : trace les echecs pour permettre de detecter une
            // attaque par force brute. Le mot de passe n'est jamais journalise.
            Log::warning('Tentative de connexion echouee', [
                'email' => $request->input('email'),
                'ip' => $request->ip(),
            ]);

            // Message volontairement generique : il ne permet pas de distinguer
            // un compte inexistant d'un mot de passe errone, donc pas
            // d'enumeration des comptes existants.
            return response()->json([
                'message' => 'Identifiants invalides',
            ], 401);
        }

        $user = User::where('email', $request->email)->firstOrFail();

        // Un compte desactive ne doit pas pouvoir continuer a obtenir de
        // nouveaux jetons. La comparaison est insensible a la casse car les
        // anciennes donnees utilisent a la fois "actif" et "Actif".
        $statut = strtolower(trim((string) $user->statut));

        if ($statut !== '' && $statut !== 'actif') {
            Auth::guard('web')->logout();

            Log::warning('Connexion refusee pour un compte inactif', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Identifiants invalides',
            ], 401);
        }

        if ($this->mfaRequisePour($user)) {
            // Auth::attempt peut ouvrir une session web. Aucun acces ne doit
            // survivre entre le premier et le second facteur.
            Auth::guard('web')->logout();

            return $this->demarrerMfa($request, $user);
        }

        return $this->reponseConnexionReussie($user);
    }

    public function verifyMfa(Request $request)
    {
        $validated = $request->validate([
            'challenge_id' => ['required', 'string', 'uuid'],
            'code' => ['required', 'digits:6'],
        ]);

        $cacheKey = 'mfa:'.$validated['challenge_id'];
        $challenge = Cache::get($cacheKey);

        if (! is_array($challenge)) {
            return response()->json(['message' => 'Code invalide ou expiré'], 401);
        }

        if ((int) ($challenge['expires_at'] ?? 0) < now()->timestamp) {
            Cache::forget($cacheKey);
            return response()->json(['message' => 'Code invalide ou expiré'], 401);
        }

        $maxAttempts = max(1, (int) config('security.mfa.max_attempts', 5));
        $attempts = (int) ($challenge['attempts'] ?? 0) + 1;

        if ($attempts > $maxAttempts) {
            Cache::forget($cacheKey);
            return response()->json(['message' => 'Code invalide ou expiré'], 401);
        }

        $challenge['attempts'] = $attempts;
        $remaining = max(1, (int) $challenge['expires_at'] - now()->timestamp);
        Cache::put($cacheKey, $challenge, now()->addSeconds($remaining));

        $ipHash = hash('sha256', (string) $request->ip());
        $userAgentHash = hash('sha256', (string) $request->userAgent());
        $codeHash = hash_hmac('sha256', $validated['code'], (string) config('app.key'));

        if (
            ! hash_equals((string) ($challenge['ip_hash'] ?? ''), $ipHash)
            || ! hash_equals((string) ($challenge['user_agent_hash'] ?? ''), $userAgentHash)
            || ! hash_equals((string) ($challenge['code_hash'] ?? ''), $codeHash)
        ) {
            return response()->json(['message' => 'Code invalide ou expiré'], 401);
        }

        $user = User::find($challenge['user_id'] ?? null);

        $statut = $user === null ? '' : strtolower(trim((string) $user->statut));

        if ($user === null || ($statut !== '' && $statut !== 'actif')) {
            Cache::forget($cacheKey);
            return response()->json(['message' => 'Code invalide ou expiré'], 401);
        }

        Cache::forget($cacheKey);

        return $this->reponseConnexionReussie($user);
    }

    private function mfaRequisePour(User $user): bool
    {
        return (bool) config('security.mfa.enforce_sensitive_roles', false)
            && in_array((string) $user->role, config('security.mfa.roles', []), true);
    }

    private function demarrerMfa(Request $request, User $user)
    {
        if (
            app()->environment('production')
            && in_array((string) config('mail.default'), ['log', 'array'], true)
        ) {
            Log::critical('MFA obligatoire mais aucun transport email de production n\'est configure');

            return response()->json([
                'message' => 'Le second facteur est temporairement indisponible.',
            ], 503);
        }

        $challengeId = (string) Str::uuid();
        $code = (string) random_int(100000, 999999);
        $ttl = max(60, (int) config('security.mfa.ttl_seconds', 300));
        $expiresAt = now()->addSeconds($ttl)->timestamp;

        Cache::put('mfa:'.$challengeId, [
            'user_id' => $user->id,
            'code_hash' => hash_hmac('sha256', $code, (string) config('app.key')),
            'ip_hash' => hash('sha256', (string) $request->ip()),
            'user_agent_hash' => hash('sha256', (string) $request->userAgent()),
            'attempts' => 0,
            'expires_at' => $expiresAt,
        ], now()->addSeconds($ttl));

        try {
            Mail::raw(
                "Votre code de sécurité VITALIS est : {$code}\n\nCe code expire dans ".ceil($ttl / 60)." minutes.",
                function ($message) use ($user) {
                    $message->to($user->email)
                        ->subject('Code de sécurité VITALIS');
                }
            );
        } catch (\Throwable $e) {
            Cache::forget('mfa:'.$challengeId);

            Log::error('Echec envoi code MFA', [
                'user_id' => $user->id,
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Le second facteur est temporairement indisponible.',
            ], 503);
        }

        return response()->json([
            'message' => 'Un code de sécurité a été envoyé.',
            'mfa_required' => true,
            'challenge_id' => $challengeId,
            'expires_in' => $ttl,
        ], 202);
    }

    private function reponseConnexionReussie(User $user)
    {
        $user->forceFill(['derniere_connexion' => now()])->save();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Connexion réussie',
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ], 200);
    }

    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            // Authentification par token d'API : on revoque uniquement ce token,
            // les autres appareils de l'utilisateur restent connectes.
            $token->delete();
        } elseif ($request->hasSession()) {
            // Authentification par session (Sanctum renvoie alors un TransientToken,
            // qui ne peut pas etre supprime) : on ferme la session a la place.
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => 'Déconnexion réussie',
        ], 200);
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user(),
        ], 200);
    }

    public function sessions(Request $request)
    {
        $current = $request->user()->currentAccessToken();
        $currentId = $current instanceof PersonalAccessToken ? $current->id : null;

        $sessions = $request->user()->tokens()
            ->latest('last_used_at')
            ->latest('created_at')
            ->get()
            ->map(fn (PersonalAccessToken $token) => [
                'id' => $token->id,
                'name' => $token->name,
                'last_used_at' => $token->last_used_at,
                'created_at' => $token->created_at,
                'expires_at' => $token->expires_at,
                'current' => $currentId !== null && $token->id === $currentId,
            ]);

        return response()->json(['sessions' => $sessions]);
    }

    public function revokeSession(Request $request, int $tokenId)
    {
        $token = $request->user()->tokens()->whereKey($tokenId)->firstOrFail();
        $token->delete();

        return response()->json([
            'message' => 'Session révoquée avec succès',
        ]);
    }

    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => 'Toutes les sessions ont été révoquées',
        ]);
    }
        /**
     * Demander un lien de réinitialisation de mot de passe
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => $this->reglesEmail(),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        $status = \Illuminate\Support\Facades\Password::sendResetLink(
            $request->only('email')
        );

        if ($status !== \Illuminate\Support\Facades\Password::RESET_LINK_SENT) {
            // SCRUM-526 : l'echec est journalise pour l'equipe, jamais renvoye
            // au client.
            Log::warning('Lien de reinitialisation non envoye', [
                'email' => $request->input('email'),
                'ip' => $request->ip(),
                'statut' => $status,
            ]);
        }

        // SCRUM-526 : la reponse est volontairement la MEME, que l'adresse
        // corresponde a un compte existant ou non.
        //
        // Auparavant une adresse inconnue recevait un 400 et une adresse
        // connue un 200 : il suffisait d'appeler cette route pour savoir qui
        // possede un compte dans la clinique. Sur une application de sante,
        // cette seule information est deja une donnee personnelle. Le meme
        // raisonnement avait ete applique a /api/login (SCRUM-510), mais pas
        // a cette route.
        return response()->json([
            'message' => 'Si un compte existe pour cette adresse, un lien de réinitialisation vient d\'être envoyé',
        ], 200);
    }

    /**
     * Réinitialiser le mot de passe avec le token
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => ['required', 'string', 'max:255'],
            'email' => $this->reglesEmail(),
            'password' => $this->reglesNouveauMotDePasse(),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        $status = \Illuminate\Support\Facades\Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                ])->setRememberToken(\Illuminate\Support\Str::random(60));

                $user->save();

                // Révoquer tous les tokens existants après reset
                $user->tokens()->delete();
            }
        );

        if ($status === \Illuminate\Support\Facades\Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Mot de passe réinitialisé avec succès',
            ], 200);
        }

        return response()->json([
            'message' => 'Token invalide ou expiré',
        ], 400);
    }
}
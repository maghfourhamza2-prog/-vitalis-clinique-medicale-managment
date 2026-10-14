<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $statut = $user === null ? '' : strtolower(trim((string) $user->statut));

        if ($user !== null && $statut !== '' && $statut !== 'actif') {
            // Une desactivation doit invalider les sessions deja emises,
            // pas seulement empecher une nouvelle connexion.
            $user->tokens()->delete();

            if ($request->hasSession()) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            Log::warning('Acces refuse pour un compte inactif', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Non authentifié',
            ], 401);
        }

        return $next($request);
    }
}

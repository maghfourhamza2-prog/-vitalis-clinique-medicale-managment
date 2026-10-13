<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AuditApiActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        $response = $next($request);

        try {
            $user = $request->user();

            if ($user !== null) {
                AuditLog::create([
                    'user_id' => $user->id,
                    'method' => $request->method(),
                    'path' => '/'.$request->path(),
                    'status_code' => $response->getStatusCode(),
                    'ip_address' => $request->ip(),
                    'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                    'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
                    'created_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            // Le journal d'audit ne doit jamais transformer une requete metier
            // reussie en erreur 500. La panne est signalee dans le log serveur.
            Log::error('Impossible d\'ecrire le journal d\'audit', [
                'exception' => $e->getMessage(),
            ]);
        }

        return $response;
    }
}

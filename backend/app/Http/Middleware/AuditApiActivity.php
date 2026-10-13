<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class AuditApiActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        $statusCode = 500;

        try {
            $response = $next($request);
            $statusCode = $response->getStatusCode();

            return $response;
        } catch (\Throwable $e) {
            $statusCode = match (true) {
                $e instanceof AuthorizationException => 403,
                $e instanceof ValidationException => $e->status,
                $e instanceof HttpExceptionInterface => $e->getStatusCode(),
                default => 500,
            };

            throw $e;
        } finally {
            $this->ecrire(
                $request,
                $statusCode,
                (int) round((hrtime(true) - $startedAt) / 1_000_000)
            );
        }
    }

    private function ecrire(Request $request, int $statusCode, int $durationMs): void
    {
        try {
            $user = $request->user();

            if ($user === null) {
                return;
            }

            AuditLog::create([
                'user_id' => $user->id,
                'method' => $request->method(),
                'path' => '/'.$request->path(),
                'status_code' => $statusCode,
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                'duration_ms' => $durationMs,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Une panne du journal ne doit jamais masquer la reponse metier
            // ni l'exception originale.
            Log::error('Impossible d\'ecrire le journal d\'audit', [
                'exception' => $e->getMessage(),
            ]);
        }
    }
}

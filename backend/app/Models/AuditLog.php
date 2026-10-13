<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'method',
        'path',
        'status_code',
        'ip_address',
        'user_agent',
        'duration_ms',
        'created_at',
    ];

    protected $hidden = [
        'integrity_hash',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'duration_ms' => 'integer',
            'status_code' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AuditLog $log) {
            $log->integrity_hash = $log->calculerIntegrite();
        });

        // Application code may append audit evidence, never rewrite or delete
        // it. Retention, if required, must be performed as a controlled
        // database operation with an explicit policy.
        static::updating(fn () => throw new LogicException('Audit logs are immutable.'));
        static::deleting(fn () => throw new LogicException('Audit logs are immutable.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function integriteValide(): bool
    {
        $stored = (string) $this->getRawOriginal('integrity_hash');

        return $stored !== ''
            && hash_equals($stored, $this->calculerIntegrite());
    }

    private function calculerIntegrite(): string
    {
        $createdAt = $this->created_at === null
            ? null
            : Carbon::parse($this->created_at)->utc()->format('Y-m-d H:i:s');

        $payload = json_encode([
            'user_id' => $this->user_id === null ? null : (int) $this->user_id,
            'method' => (string) $this->method,
            'path' => (string) $this->path,
            'status_code' => (int) $this->status_code,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'duration_ms' => $this->duration_ms === null ? null : (int) $this->duration_ms,
            'created_at' => $createdAt,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return hash_hmac(
            'sha256',
            $payload ?: '',
            (string) config('security.audit.hmac_key', config('app.key'))
        );
    }
}

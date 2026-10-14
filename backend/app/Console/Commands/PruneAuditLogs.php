<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneAuditLogs extends Command
{
    protected $signature = 'audit:prune';

    protected $description = 'Apply the configured VITALIS audit-log retention policy';

    public function handle(): int
    {
        $days = (int) config('security.audit.retention_days', 0);

        if ($days <= 0) {
            $this->info('Audit retention pruning is disabled (AUDIT_RETENTION_DAYS=0).');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days);

        // Deliberately bypass the immutable Eloquent model. Retention is the
        // single controlled deletion path and operates only on expired rows.
        $deleted = DB::table('audit_logs')
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info("Deleted {$deleted} audit log(s) older than {$days} days.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Calculation;
use Illuminate\Console\Command;

/**
 * Deletes saved estimates past their expires_at (90 days, config dutycalc.estimate_retention_days).
 * Breakdowns go with them (cascade). Scheduled daily in routes/console.php.
 */
class PruneEstimates extends Command
{
    protected $signature = 'estimates:prune';

    protected $description = 'Delete saved estimates that are past their expiry date';

    public function handle(): int
    {
        $deleted = 0;
        Calculation::where('expires_at', '<=', now())
            ->select('id')
            ->chunkById(500, function ($calculations) use (&$deleted) {
                $deleted += Calculation::whereKey($calculations->pluck('id'))->delete();
            });

        $this->info("Deleted {$deleted} expired estimate(s).");

        return self::SUCCESS;
    }
}

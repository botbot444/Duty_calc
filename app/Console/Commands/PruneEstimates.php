<?php

namespace App\Console\Commands;

use App\Models\AiProductAnalysis;
use App\Models\Calculation;
use Illuminate\Console\Command;

/**
 * Deletes saved estimates past their expires_at (90 days, config dutycalc.estimate_retention_days).
 * Breakdowns go with them (cascade). AI analysis records older than the same period go too,
 * unless a remaining estimate still points to them. Scheduled daily in routes/console.php.
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

        // AI analysis records follow the same retention, once no saved estimate points to them.
        $analyses = AiProductAnalysis::where('created_at', '<=', now()->subDays((int) config('dutycalc.estimate_retention_days')))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('calculations')
                ->whereColumn('calculations.ai_product_analysis_id', 'ai_product_analyses.id'))
            ->delete();
        $this->info("Deleted {$analyses} old product analysis record(s).");

        return self::SUCCESS;
    }
}

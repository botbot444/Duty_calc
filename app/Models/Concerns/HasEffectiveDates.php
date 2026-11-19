<?php

namespace App\Models\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Rows that are in force between a start date and an optional, inclusive end date.
 *
 * "Today" is read through now(), which uses the app timezone (Africa/Lusaka), so
 * tests can fix it with Carbon::setTestNow(). A row ending 2026-12-31 is in force
 * until 23:59:59 Lusaka time that day.
 *
 * Models may override the column names with EFFECTIVE_FROM_COLUMN / EFFECTIVE_TO_COLUMN.
 */
trait HasEffectiveDates
{
    public function scopeInForce(Builder $query, ?CarbonInterface $on = null): Builder
    {
        $date = ($on ?? now())->copy()->setTimezone(config('app.timezone'))->toDateString();

        $from = $query->qualifyColumn($this->effectiveFromColumn());
        $to = $query->qualifyColumn($this->effectiveToColumn());

        return $query
            ->where($from, '<=', $date)
            ->where(fn (Builder $q) => $q->whereNull($to)->orWhere($to, '>=', $date));
    }

    public function isInForce(?CarbonInterface $on = null): bool
    {
        $date = ($on ?? now())->copy()->setTimezone(config('app.timezone'))->toDateString();

        $from = $this->{$this->effectiveFromColumn()};
        $to = $this->{$this->effectiveToColumn()};

        return $from !== null
            && $from->toDateString() <= $date
            && ($to === null || $to->toDateString() >= $date);
    }

    protected function effectiveFromColumn(): string
    {
        return defined(static::class.'::EFFECTIVE_FROM_COLUMN') ? static::EFFECTIVE_FROM_COLUMN : 'effective_from';
    }

    protected function effectiveToColumn(): string
    {
        return defined(static::class.'::EFFECTIVE_TO_COLUMN') ? static::EFFECTIVE_TO_COLUMN : 'effective_to';
    }
}

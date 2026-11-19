<?php

namespace Tests\Support;

use Illuminate\Foundation\Testing\DatabaseTruncation;

/**
 * For tests that use MySQL/MariaDB FULLTEXT search. InnoDB FULLTEXT ignores uncommitted
 * rows, so RefreshDatabase (one transaction per test) cannot be used; rows are committed
 * and the tables truncated instead.
 *
 * DatabaseTruncation only truncates *before* each test, which would leave the last test's
 * rows behind for RefreshDatabase tests that run next. This trait also truncates after.
 */
trait FullTextDatabase
{
    use DatabaseTruncation;

    /** Called by Laravel's setUpTraits() for any trait with a matching setUp{TraitName} method. */
    protected function setUpFullTextDatabase(): void
    {
        $this->beforeApplicationDestroyed(fn () => $this->truncateDatabaseTables());
    }
}

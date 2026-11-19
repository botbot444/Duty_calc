<?php

namespace App\Services\Admin;

use App\Models\AdminAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The single audit writer. Every admin save goes through change(), which runs the save and
 * writes its log row in one transaction: a change is never saved without its log, or logged
 * without being saved.
 */
class AuditLog
{
    public const ACTIONS = ['create', 'update', 'replace', 'end', 'import', 'toggle', 'delete'];

    /**
     * Run $work in a transaction; it may call record() any number of times.
     *
     * @template T
     *
     * @param  callable(self): T  $work
     * @return T
     */
    public function change(callable $work): mixed
    {
        return DB::transaction(fn () => $work($this));
    }

    public function record(string $action, Model|string $subject, ?array $before, ?array $after, ?string $recordId = null): AdminAuditLog
    {
        if (! in_array($action, self::ACTIONS, true)) {
            throw new \InvalidArgumentException("Unknown audit action \"{$action}\"");
        }
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Audit records must be written inside AuditLog::change()');
        }

        return AdminAuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'table_name' => $subject instanceof Model ? $subject->getTable() : $subject,
            'record_id' => $recordId ?? ($subject instanceof Model ? (string) $subject->getKey() : null),
            'before' => $before,
            'after' => $after,
        ]);
    }

    /**
     * The row as stored in the database: dates as Y-m-d, decimals as strings, JSON columns
     * decoded and booleans as true/false. Read fresh, so the log shows what was saved.
     */
    public static function snapshot(Model $model): array
    {
        $fresh = $model->exists ? ($model->fresh() ?? $model) : $model;
        $attributes = $fresh->getAttributes();

        foreach ($fresh->getCasts() as $key => $cast) {
            if (! array_key_exists($key, $attributes) || $attributes[$key] === null) {
                continue;
            }
            if (in_array($cast, ['array', 'json'], true) && is_string($attributes[$key])) {
                $attributes[$key] = json_decode($attributes[$key], true);
            } elseif ($cast === 'boolean') {
                $attributes[$key] = (bool) $attributes[$key];
            }
        }

        return $attributes;
    }
}

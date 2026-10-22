<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

/**
 * Mendengarkan event Eloquent untuk semua model aplikasi dan menulis activity
 * log secara otomatis pada event created, updated, dan deleted.
 *
 * Catatan: operasi bulk lewat query builder (insert/update/upsert) tidak
 * memicu event model, jadi tetap perlu logging manual bila diperlukan.
 */
class ModelActivityLogger
{
    /**
     * Snapshot atribut sebelum update, di-key berdasarkan instance model.
     *
     * @var \WeakMap<Model, array<string, mixed>>|null
     */
    private static ?\WeakMap $original = null;

    public static function register(): void
    {
        Event::listen('eloquent.updating: *', static function (string $eventName, array $payload): void {
            $model = $payload[0] ?? null;

            if (! $model instanceof Model || ! self::shouldLog($model)) {
                return;
            }

            self::$original ??= new \WeakMap();

            // Cast nilai lama dengan cara yang sama seperti nilai baru agar
            // diff tidak memunculkan perubahan palsu (mis. 1 vs true).
            $oldModel = $model->newInstance([], true);
            $oldModel->setConnection($model->getConnectionName());
            $oldModel->setRawAttributes($model->getOriginal(), true);

            self::$original[$model] = $oldModel->attributesToArray();
        });

        Event::listen('eloquent.created: *', static function (string $eventName, array $payload): void {
            $model = $payload[0] ?? null;

            if (! $model instanceof Model || ! self::shouldLog($model)) {
                return;
            }

            app(ActivityLogService::class)->logCreated($model, auto: true);
        });

        Event::listen('eloquent.updated: *', static function (string $eventName, array $payload): void {
            $model = $payload[0] ?? null;

            if (! $model instanceof Model || ! self::shouldLog($model)) {
                return;
            }

            $old = self::$original[$model] ?? null;

            if ($old === null) {
                return;
            }

            unset(self::$original[$model]);

            $changes = $model->getChanges();
            unset($changes['updated_at']);

            if ($changes === []) {
                return;
            }

            app(ActivityLogService::class)->logUpdated(
                $model,
                $old,
                $model->attributesToArray(),
                auto: true,
            );
        });

        Event::listen('eloquent.deleted: *', static function (string $eventName, array $payload): void {
            $model = $payload[0] ?? null;

            if (! $model instanceof Model || ! self::shouldLog($model)) {
                return;
            }

            app(ActivityLogService::class)->logDeleted($model, auto: true);
        });
    }

    private static function shouldLog(Model $model): bool
    {
        // Jangan log model log itu sendiri agar tidak rekursif.
        if ($model instanceof ActivityLog) {
            return false;
        }

        // Batasi ke model aplikasi, bukan model internal framework/package.
        if (! str_starts_with($model::class, 'App\\Models\\')) {
            return false;
        }

        return self::activityTableAvailable();
    }

    private static function activityTableAvailable(): bool
    {
        $key = 'activity-log.table-available';

        if (app()->bound($key)) {
            return (bool) app($key);
        }

        try {
            $available = Schema::hasTable('activity_logs');
        } catch (\Throwable) {
            $available = false;
        }

        // Cache per instance aplikasi/request. Di test, container di-refresh
        // tiap test; di HTTP request, pengecekan hanya sekali.
        app()->instance($key, $available);

        return $available;
    }
}

<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ActivityLogService
{
    /**
     * Fields yang tidak boleh disimpan ke log (sensitif).
     *
     * @var list<string>
     */
    protected array $hiddenFields = [
        'password',
        'password_confirmation',
        'remember_token',
        'email_verified_at',
        'token',
        'access_token',
        'refresh_token',
        'api_token',
        'api_key',
        'secret',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * Log otomatis yang baru ditulis, di-key berdasarkan instance model.
     * Pemanggilan manual dari service dapat memperkaya baris yang sama
     * (deskripsi/properties) alih-alih membuat baris duplikat.
     *
     * @var \WeakMap<Model, array{event: string, id: int, at: int}>|null
     */
    protected static ?\WeakMap $recentAutoLogs = null;

    public function logCreated(Model $subject, ?string $description = null, bool $auto = false): ActivityLog
    {
        $attributes = $this->filterHidden($subject->attributesToArray());

        return $this->store(
            logName: $this->resolveLogName($subject),
            event: 'created',
            description: $description ?? $this->defaultDescription($subject, 'created'),
            subject: $subject,
            properties: [
                'new' => $attributes,
                'attributes' => $attributes,
            ],
            auto: $auto,
        );
    }

    /**
     * Log untuk event updated - menyimpan old & new + diff
     */
    public function logUpdated(Model $subject, array $oldData, array $newData, ?string $description = null, bool $auto = false): ActivityLog
    {
        $old = $this->filterHidden($oldData);
        $new = $this->filterHidden($newData);
        $diff = $this->calculateDiff($old, $new);

        return $this->store(
            logName: $this->resolveLogName($subject),
            event: 'updated',
            description: $description ?? $this->defaultDescription($subject, 'updated'),
            subject: $subject,
            properties: [
                'old' => $old,
                'new' => $new,
                'attributes' => $new, // alias
                'diff' => $diff,
            ],
            auto: $auto,
        );
    }

    /**
     * Log untuk event deleted - menyimpan snapshot sebelum hapus
     */
    public function logDeleted(Model $subject, ?string $description = null, bool $auto = false): ActivityLog
    {
        $old = $this->filterHidden($subject->attributesToArray());

        return $this->store(
            logName: $this->resolveLogName($subject),
            event: 'deleted',
            description: $description ?? $this->defaultDescription($subject, 'deleted'),
            subject: $subject,
            properties: [
                'old' => $old,
                'attributes' => $old,
            ],
            auto: $auto,
        );
    }

    /**
     * Log generic (bisa untuk created/custom event lainnya).
     */
    public function log(Model $subject, string $event, array $properties = [], ?string $description = null, bool $auto = false): ActivityLog
    {
        return $this->store(
            logName: $this->resolveLogName($subject),
            event: $event,
            description: $description ?? $this->defaultDescription($subject, $event),
            subject: $subject,
            properties: $properties,
            auto: $auto,
        );
    }

    protected function store(string $logName, string $event, ?string $description, Model $subject, array $properties, bool $auto = false): ActivityLog
    {
        // Jangan sampai logging memecahkan request bila tabel audit belum ada
        // (mis. pada test yang memakai skema minimal atau saat migrasi awal).
        if (! $this->tableAvailable()) {
            return new ActivityLog();
        }

        $properties = $this->filterProperties($properties);

        if (! $auto) {
            $existing = $this->pullRecentAutoLog($subject, $event);

            if ($existing !== null) {
                // Service manual biasanya punya deskripsi/properties yang lebih
                // kaya. Perkaya baris otomatis agar tidak muncul dua kali.
                $existing->forceFill([
                    'description' => $description ?? $existing->description,
                    'properties' => array_merge($existing->properties ?? [], $properties),
                ])->save();

                return $existing;
            }
        }

        $causer = Auth::user();
        $request = request();

        $log = ActivityLog::create([
            'log_name' => $logName,
            'event' => $event,
            'description' => $description,
            'subject_type' => get_class($subject),
            'subject_id' => $subject->getKey(),
            'causer_type' => $causer ? get_class($causer) : null,
            'causer_id' => $causer?->getKey(),
            'properties' => $properties,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'batch_uuid' => (string) Str::uuid(),
        ]);

        if ($auto) {
            $this->rememberAutoLog($subject, $event, $log);
        }

        return $log;
    }

    protected function tableAvailable(): bool
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

        app()->instance($key, $available);

        return $available;
    }

    protected function rememberAutoLog(Model $subject, string $event, ActivityLog $log): void
    {
        self::$recentAutoLogs ??= new \WeakMap();
        self::$recentAutoLogs[$subject] = [
            'event' => $event,
            'id' => (int) $log->getKey(),
            'at' => now()->timestamp,
        ];
    }

    protected function pullRecentAutoLog(Model $subject, string $event): ?ActivityLog
    {
        if (self::$recentAutoLogs === null) {
            return null;
        }

        $recent = self::$recentAutoLogs[$subject] ?? null;

        if ($recent === null) {
            return null;
        }

        unset(self::$recentAutoLogs[$subject]);

        if ($recent['event'] !== $event || now()->timestamp - $recent['at'] > 30) {
            return null;
        }

        return ActivityLog::query()->find($recent['id']);
    }

    protected function resolveLogName(Model $subject): string
    {
        // user -> user, OrganizationUnit -> organization_unit
        return Str::snake(class_basename($subject));
    }

    protected function defaultDescription(Model $subject, string $event): string
    {
        $name = $subject->name ?? $subject->username ?? $subject->title ?? "#{$subject->getKey()}";

        return ucfirst($event).' '.class_basename($subject)." {$name}";
    }

    protected function filterHidden(array $data): array
    {
        return array_diff_key($data, array_flip($this->hiddenFields));
    }

    /**
     * @param  array<string, mixed>  $properties
     * @return array<string, mixed>
     */
    protected function filterProperties(array $properties): array
    {
        $filtered = [];

        foreach ($properties as $key => $value) {
            if (in_array((string) $key, $this->hiddenFields, true)) {
                continue;
            }

            $filtered[$key] = is_array($value)
                ? $this->filterProperties($value)
                : $value;
        }

        return $filtered;
    }

    protected function calculateDiff(array $old, array $new): array
    {
        $diff = [];

        foreach ($new as $key => $value) {
            if ($key === 'updated_at') {
                continue;
            }

            $oldValue = $old[$key] ?? null;

            if ($oldValue !== $value) {
                $diff[$key] = ['old' => $oldValue, 'new' => $value];
            }
        }

        // field yang dihapus
        foreach ($old as $key => $value) {
            if ($key === 'updated_at') {
                continue;
            }

            if (! array_key_exists($key, $new)) {
                $diff[$key] = ['old' => $value, 'new' => null];
            }
        }

        return $diff;
    }
}

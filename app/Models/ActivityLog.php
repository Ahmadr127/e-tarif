<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'log_name',
        'event',
        'description',
        'subject_type',
        'subject_id',
        'causer_type',
        'causer_id',
        'properties',
        'ip_address',
        'user_agent',
        'batch_uuid',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function causer(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Subject hanya bisa di-resolve bila class modelnya masih ada. Baris lama
     * bisa menunjuk model yang sudah dihapus dari aplikasi (mis. Room), dan
     * eager load morph-nya akan error — jadi relasi ini tidak di-eager load.
     */
    public function subjectIsResolvable(): bool
    {
        return static::typeIsResolvable($this->subject_type);
    }

    /**
     * Causer bisa ikut menunjuk model yang sudah dihapus (mis. kalau log lama
     * memakai model causer selain User). Dipakai view sebelum akses relasi.
     */
    public function causerIsResolvable(): bool
    {
        return static::typeIsResolvable($this->causer_type);
    }

    /**
     * class_exists() tidak selalu aman: kalau classmap Composer yang sudah
     * di-optimize masih menunjuk file yang sudah dihapus, autoloader mencoba
     * include file itu dan melempar ErrorException. Audit log historis tidak
     * boleh membuat halaman 500 karena class yang tinggal namanya saja.
     */
    public static function typeIsResolvable(mixed $type): bool
    {
        if (! is_string($type) || $type === '') {
            return false;
        }

        try {
            return class_exists($type);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Label subject yang aman ditampilkan di view: tidak pernah error walau
     * model subject-nya sudah tidak ada lagi di aplikasi.
     */
    public function getSubjectLabelAttribute(): string
    {
        if (! $this->subject_type) {
            return '-';
        }

        $fallback = class_basename($this->subject_type).' #'.$this->subject_id;

        if (! $this->subjectIsResolvable()) {
            return $fallback.' (model tidak tersedia)';
        }

        $subject = $this->subject;

        return $subject?->name ?? $subject?->title ?? $fallback;
    }

    /**
     * Scope untuk filter by log_name
     */
    public function scopeInLog($query, string $logName)
    {
        return $query->where('log_name', $logName);
    }

    public function scopeForSubject($query, Model $subject)
    {
        return $query->where('subject_type', get_class($subject))
            ->where('subject_id', $subject->getKey());
    }
}

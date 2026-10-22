<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Menandai model yang memiliki relasi activity log.
 *
 * Pencatatan created/updated/deleted sekarang ditangani secara global oleh
 * App\Services\ModelActivityLogger untuk semua model di App\Models, sehingga
 * trait ini tidak lagi memasang event listener sendiri.
 */
trait Auditable
{
    /**
     * @return MorphMany<ActivityLog, $this>
     */
    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject')->latest();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitCostResultSnapshot extends Model
{
    public const STATUS_APPROVED = 'approved';

    protected $fillable = [
        'period_id',
        'unit_id',
        'status',
        'payload',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'approved_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<UnitCostPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(UnitCostPeriod::class, 'period_id');
    }

    /**
     * @return BelongsTo<OrganizationUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationUnit::class, 'unit_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}

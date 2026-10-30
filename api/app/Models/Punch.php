<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Punch extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'type',
        'punched_at',
        'received_at',
        'source',
        'synced_offline',
        'latitude',
        'longitude',
        'accuracy_meters',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'punched_at' => 'datetime',
            'received_at' => 'datetime',
            'synced_offline' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'accuracy_meters' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(PunchAudit::class);
    }    
}

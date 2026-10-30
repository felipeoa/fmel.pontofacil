<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyWorkSummary extends Model
{
    protected $fillable = [
        'user_id',
        'work_schedule_id',
        'work_date',
        'expected_minutes',
        'worked_minutes',
        'break_minutes',
        'balance_minutes',
        'status',
        'calculated_at',
        'revision',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'calculated_at' => 'datetime',
            'revision' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class, 'work_schedule_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(
            BankTransaction::class,
            'daily_work_summary_id'
        );
    }
}

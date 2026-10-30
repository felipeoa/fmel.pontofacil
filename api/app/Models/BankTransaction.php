<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'daily_work_summary_id',
        'uuid',
        'transaction_type',
        'minutes',
        'description',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function summary(): BelongsTo
    {
        return $this->belongsTo(
            DailyWorkSummary::class,
            'daily_work_summary_id'
        );
    }
}

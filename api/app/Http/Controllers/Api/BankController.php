<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankTransaction;
use Illuminate\Http\Request;

class BankController extends Controller
{
    public function balance(Request $request)
    {
        $userId = $request->user()->id;

        $balance = BankTransaction::query()
            ->where('user_id', $userId)
            ->sum('minutes');

        $credits = BankTransaction::query()
            ->where('user_id', $userId)
            ->where('minutes', '>', 0)
            ->sum('minutes');

        $debits = BankTransaction::query()
            ->where('user_id', $userId)
            ->where('minutes', '<', 0)
            ->sum('minutes');

        return response()->json([
            'balance_minutes' => (int) $balance,
            'balance_formatted' => $this->formatMinutes($balance),
            'credit_minutes' => (int) $credits,
            'debit_minutes' => (int) abs($debits),
        ]);
    }

    public function transactions(Request $request)
    {
        $transactions = BankTransaction::query()
            ->where('user_id', $request->user()->id)
            ->with('summary:id,work_date')
            ->orderByDesc('created_at')
            ->paginate(30);

        return response()->json($transactions);
    }

    private function formatMinutes(int $minutes): string
    {
        $signal = $minutes < 0 ? '-' : '';
        $absolute = abs($minutes);

        $hours = intdiv($absolute, 60);
        $remainingMinutes = $absolute % 60;

        return sprintf(
            '%s%02d:%02d',
            $signal,
            $hours,
            $remainingMinutes
        );
    }
}

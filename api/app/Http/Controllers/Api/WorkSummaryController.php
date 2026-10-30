<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyWorkSummary;
use App\Services\WorkdayCalculationService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class WorkSummaryController extends Controller
{
    public function show(Request $request, string $date)
    {
        $validatedDate = Carbon::createFromFormat(
            '!Y-m-d',
            $date
        );

        abort_unless(
            $validatedDate && $validatedDate->format('Y-m-d') === $date,
            422,
            'Data inválida. Utilize o formato AAAA-MM-DD.'
        );

        $summary = DailyWorkSummary::query()
            ->where('user_id', $request->user()->id)
            ->whereDate('work_date', $date)
            ->first();

        if (!$summary) {
            return response()->json([
                'message' => 'Ainda não existe apuração para esta data.',
                'data' => null,
            ]);
        }

        return response()->json([
            'data' => $summary,
        ]);
    }

    public function calculate(
        Request $request,
        string $date,
        WorkdayCalculationService $calculator
    ) {
        $validatedDate = Carbon::createFromFormat(
            '!Y-m-d',
            $date
        );

        abort_unless(
            $validatedDate && $validatedDate->format('Y-m-d') === $date,
            422,
            'Data inválida. Utilize o formato AAAA-MM-DD.'
        );

        $summary = $calculator->calculate(
            $request->user(),
            $date
        );

        return response()->json([
            'message' => 'Jornada apurada com sucesso.',
            'data' => $summary,
        ]);
    }
}

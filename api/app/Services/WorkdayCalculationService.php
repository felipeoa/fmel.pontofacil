<?php

namespace App\Services;

use App\Models\BankTransaction;
use App\Models\DailyWorkSummary;
use App\Models\Punch;
use App\Models\User;
use App\Models\WorkSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class WorkdayCalculationService
{
    public function calculate(User $user, string $date): DailyWorkSummary
    {
        return DB::transaction(function () use ($user, $date) {
            $schedule = WorkSchedule::query()
                ->where('user_id', $user->id)
                ->where('active', true)
                ->whereDate('effective_from', '<=', $date)
                ->where(function ($query) use ($date) {
                    $query->whereNull('effective_until')
                        ->orWhereDate('effective_until', '>=', $date);
                })
                ->orderByDesc('is_default')
                ->first();

            if (!$schedule) {
                throw new RuntimeException(
                    'Nenhuma jornada ativa encontrada para esta data.'
                );
            }

            $timezone = $schedule->timezone;

            $localStart = CarbonImmutable::parse($date, $timezone)
                ->startOfDay();

            $localEnd = $localStart->endOfDay();

            $utcStart = $localStart->utc();
            $utcEnd = $localEnd->utc();

            $weekday = $localStart->dayOfWeekIso;

            $scheduleDay = $schedule->days()
                ->where('weekday', $weekday)
                ->first();

            $expectedMinutes = (
                $scheduleDay && $scheduleDay->is_working_day
            ) ? $scheduleDay->expected_minutes : 0;

            $punches = Punch::query()
                ->where('user_id', $user->id)
                ->whereBetween('punched_at', [$utcStart, $utcEnd])
                ->orderBy('punched_at')
                ->orderBy('id')
                ->get();

            $workedSeconds = 0;
            $breakSeconds = 0;

            $workStart = null;
            $breakStart = null;

            $started = false;
            $finished = false;
            $invalid = false;

            $lastTimestamp = null;

            foreach ($punches as $punch) {
                $time = CarbonImmutable::parse(
                    $punch->punched_at
                )->setTimezone($timezone);

                $timestamp = $time->getTimestamp();

                // Impede horários repetidos ou regressivos.
                if (
                    $lastTimestamp !== null
                    && $timestamp <= $lastTimestamp
                ) {
                    $invalid = true;
                    break;
                }

                $lastTimestamp = $timestamp;

                if ($finished) {
                    $invalid = true;
                    break;
                }

                switch ($punch->type) {
                    case 'entry':
                        if ($started || $workStart || $breakStart) {
                            $invalid = true;
                            break 2;
                        }

                        $started = true;
                        $workStart = $time;
                        break;

                    case 'break_start':
                        if (!$workStart || $breakStart) {
                            $invalid = true;
                            break 2;
                        }

                        $workedSeconds +=
                            $timestamp - $workStart->getTimestamp();

                        $workStart = null;
                        $breakStart = $time;
                        break;

                    case 'break_end':
                        if (!$breakStart || $workStart) {
                            $invalid = true;
                            break 2;
                        }

                        $breakSeconds +=
                            $timestamp - $breakStart->getTimestamp();

                        $breakStart = null;
                        $workStart = $time;
                        break;

                    case 'exit':
                        if (!$workStart || $breakStart) {
                            $invalid = true;
                            break 2;
                        }

                        $workedSeconds +=
                            $timestamp - $workStart->getTimestamp();

                        $workStart = null;
                        $finished = true;
                        break;

                    default:
                        $invalid = true;
                        break 2;
                }
            }

            $complete = $started
                && $finished
                && !$workStart
                && !$breakStart
                && !$invalid;

            $status = $invalid
                ? 'invalid'
                : ($complete ? 'complete' : 'pending');

            // Tempo parcial pode ser exibido, mas ainda não gera saldo.
            if (!$invalid && $workStart) {
                $workedSeconds += now($timezone)->getTimestamp()
                    - $workStart->getTimestamp();
            }

            $workedMinutes = !$invalid
                ? intdiv(max(0, $workedSeconds), 60)
                : 0;

            $breakMinutes = !$invalid
                ? intdiv(max(0, $breakSeconds), 60)
                : 0;            

            $summary = DailyWorkSummary::query()
                ->where('user_id', $user->id)
                ->whereDate('work_date', $date)
                ->lockForUpdate()
                ->first();

            if (!$summary) {
                $summary = new DailyWorkSummary();
                $summary->user_id = $user->id;
                $summary->work_date = $date;
            }

            $oldBalance = (int) $summary->balance_minutes;

            $newBalance = $complete
                ? $workedMinutes - $expectedMinutes
                : 0;

            $summary->work_schedule_id = $schedule->id;
            $summary->expected_minutes = $expectedMinutes;
            $summary->worked_minutes = $workedMinutes;
            $summary->break_minutes = $breakMinutes;
            $summary->balance_minutes = $newBalance;
            $summary->status = $status;
            $summary->calculated_at = now();

            $summary->save();

            // Registra somente a diferença em relação ao saldo anterior.
            $difference = $newBalance - $oldBalance;

            if ($difference !== 0) {
                BankTransaction::create([
                    'user_id' => $user->id,
                    'daily_work_summary_id' => $summary->id,
                    'uuid' => (string) Str::uuid(),
                    'transaction_type' => $difference > 0
                        ? 'credit'
                        : 'debit',
                    'minutes' => $difference,
                    'description' => 'Apuração da jornada de '
                        . $date,
                ]);
            }

            return $summary;
        });
    }
}
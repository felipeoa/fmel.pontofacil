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

            /*
             * Serializa os cálculos do mesmo usuário.
             * Evita concorrência na criação e atualização
             * dos resumos diários.
             */
            $lockedUser = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $userId = $lockedUser->id;

            /*
             * Localiza a jornada válida para a data.
             */
            $schedule = WorkSchedule::query()
                ->where('user_id', $userId)
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

            /*
             * Busca somente marcações ativas.
             * Registros excluídos logicamente não entram no cálculo.
             */
            $punches = Punch::query()
                ->where('user_id', $userId)
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

            /*
             * Processamento da sequência de marcações.
             */
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

            /*
             * Identifica uma jornada completa.
             */
            $complete = $started
                && $finished
                && !$workStart
                && !$breakStart
                && !$invalid;

            $status = $invalid
                ? 'invalid'
                : ($complete ? 'complete' : 'pending');

            /*
             * Jornada em andamento:
             * calcula o tempo parcial sem gerar saldo.
             */
            if (!$invalid && $workStart) {

                $partialSeconds = now($timezone)->getTimestamp()
                    - $workStart->getTimestamp();

                $workedSeconds += max(0, $partialSeconds);
            }

            $workedMinutes = !$invalid
                ? intdiv(max(0, $workedSeconds), 60)
                : 0;

            $breakMinutes = !$invalid
                ? intdiv(max(0, $breakSeconds), 60)
                : 0;

            /*
             * Recupera e bloqueia o resumo diário.
             */
            $summary = DailyWorkSummary::query()
                ->where('user_id', $userId)
                ->whereDate('work_date', $date)
                ->lockForUpdate()
                ->first();

            if (!$summary) {

                $summary = new DailyWorkSummary();

                $summary->user_id = $userId;
                $summary->work_date = $date;
                $summary->revision = 0;
                $summary->balance_minutes = 0;
            }

            $oldBalance = (int) $summary->balance_minutes;

            /*
             * Somente jornadas completas geram saldo.
             */
            $newBalance = $complete
                ? $workedMinutes - $expectedMinutes
                : 0;

            /*
             * Diferença entre o saldo anterior e o atual.
             */
            $difference = $newBalance - $oldBalance;

            /*
             * Atualiza o resumo diário.
             */
            $summary->work_schedule_id = $schedule->id;
            $summary->expected_minutes = $expectedMinutes;
            $summary->worked_minutes = $workedMinutes;
            $summary->break_minutes = $breakMinutes;
            $summary->balance_minutes = $newBalance;
            $summary->status = $status;
            $summary->calculated_at = now();

            /*
             * Incrementa a revisão somente quando
             * existe alteração efetiva no saldo.
             */
            if ($difference !== 0) {

                $summary->revision = (int) $summary->revision + 1;
            }

            $summary->save();

            /*
             * Registra a movimentação no banco de horas.
             */
            if ($difference !== 0) {

                $sourceKey = sprintf(
                    'summary:%d:revision:%d',
                    $summary->id,
                    $summary->revision
                );

                BankTransaction::create([

                    'user_id' => $userId,

                    'daily_work_summary_id' => $summary->id,

                    'uuid' => (string) Str::uuid(),

                    'source_key' => $sourceKey,

                    'transaction_type' => $difference > 0
                        ? 'credit'
                        : 'debit',

                    'minutes' => $difference,

                    'description' => 'Apuração da jornada de '
                        . $date
                        . ' - revisão '
                        . $summary->revision,
                ]);
            }

            return $summary;
        }, 3);
    }
}
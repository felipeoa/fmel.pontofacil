<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkScheduleController extends Controller
{
    public function index(Request $request)
    {
        $schedules = WorkSchedule::query()
            ->where('user_id', $request->user()->id)
            ->with('days')
            ->orderByDesc('is_default')
            ->orderByDesc('effective_from')
            ->get();

        return response()->json([
            'data' => $schedules,
        ]);
    }

    public function show(Request $request, int $id)
    {
        $schedule = WorkSchedule::query()
            ->where('user_id', $request->user()->id)
            ->with('days')
            ->findOrFail($id);

        return response()->json([
            'data' => $schedule,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'timezone' => ['required', 'timezone'],
            'effective_from' => ['required', 'date'],
            'effective_until' => [
                'nullable',
                'date',
                'after_or_equal:effective_from',
            ],
            'is_default' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],

            'days' => ['required', 'array', 'size:7'],
            'days.*.weekday' => [
                'required',
                'integer',
                'between:1,7',
                'distinct',
            ],
            'days.*.is_working_day' => [
                'required',
                'boolean',
            ],
            'days.*.start_time' => [
                'nullable',
                'date_format:H:i',
            ],
            'days.*.end_time' => [
                'nullable',
                'date_format:H:i',
            ],
            'days.*.expected_minutes' => [
                'required',
                'integer',
                'between:0,1440',
            ],
        ]);

        foreach ($data['days'] as $day) {
            if ($day['is_working_day']) {
                if (
                    empty($day['start_time']) ||
                    empty($day['end_time']) ||
                    $day['expected_minutes'] <= 0
                ) {
                    throw ValidationException::withMessages([
                        'days' => 'Dias de trabalho precisam de horários e carga horária.',
                    ]);
                }
            } elseif ((int) $day['expected_minutes'] !== 0) {
                throw ValidationException::withMessages([
                    'days' => 'Dias de folga devem ter carga horária igual a zero.',
                ]);
            }
        }

        $schedule = DB::transaction(function () use ($request, $data) {
            $days = $data['days'];
            unset($data['days']);

            $data['user_id'] = $request->user()->id;

            $schedule = WorkSchedule::create($data);

            if ($schedule->is_default) {
                WorkSchedule::query()
                    ->where('user_id', $request->user()->id)
                    ->where('id', '!=', $schedule->id)
                    ->update(['is_default' => false]);
            }

            foreach ($days as $day) {
                $schedule->days()->create($day);
            }

            return $schedule->load('days');
        });

        return response()->json([
            'message' => 'Jornada cadastrada com sucesso.',
            'data' => $schedule,
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $schedule = WorkSchedule::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'timezone' => ['sometimes', 'required', 'timezone'],
            'effective_from' => ['sometimes', 'required', 'date'],
            'effective_until' => [
                'nullable',
                'date',
                'after_or_equal:effective_from',
            ],
            'is_default' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
            'days' => ['sometimes', 'array', 'size:7'],
            'days.*.weekday' => [
                'required',
                'integer',
                'between:1,7',
                'distinct',
            ],
            'days.*.is_working_day' => ['required', 'boolean'],
            'days.*.start_time' => ['nullable', 'date_format:H:i'],
            'days.*.end_time' => ['nullable', 'date_format:H:i'],
            'days.*.expected_minutes' => [
                'required',
                'integer',
                'between:0,1440',
            ],
        ]);

        return DB::transaction(function () use (
            $request,
            $schedule,
            $data
        ) {
            $days = $data['days'] ?? null;
            unset($data['days']);

            $schedule->update($data);

            if ($schedule->is_default) {
                WorkSchedule::query()
                    ->where('user_id', $request->user()->id)
                    ->where('id', '!=', $schedule->id)
                    ->update(['is_default' => false]);
            }

            if ($days !== null) {
                foreach ($days as $day) {
                    if (
                        $day['is_working_day'] &&
                        (
                            empty($day['start_time']) ||
                            empty($day['end_time']) ||
                            $day['expected_minutes'] <= 0
                        )
                    ) {
                        throw ValidationException::withMessages([
                            'days' => 'Dias de trabalho precisam de horários e carga horária.',
                        ]);
                    }

                    if (
                        !$day['is_working_day'] &&
                        (int) $day['expected_minutes'] !== 0
                    ) {
                        throw ValidationException::withMessages([
                            'days' => 'Dias de folga devem ter carga horária igual a zero.',
                        ]);
                    }
                }

                $schedule->days()->delete();

                foreach ($days as $day) {
                    $schedule->days()->create($day);
                }
            }

            return response()->json([
                'message' => 'Jornada atualizada com sucesso.',
                'data' => $schedule->load('days'),
            ]);
        });
    }
}

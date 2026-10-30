<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Punch;
use App\Models\User;
use App\Services\WorkdayCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PunchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $punches = $request->user()
            ->punches()
            ->orderByDesc('punched_at')
            ->paginate(50);

        return response()->json($punches);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(
        [
            'uuid' => ['required', 'uuid'],
            'type' => 
            [
                'required',
                Rule::in([
                    'entry',
                    'break_start',
                    'break_end',
                    'exit',
                ]),
            ],
            'punched_at' => ['required', 'date'],
            'source' => 
            [
                'nullable',
                Rule::in(['manual', 'gps', 'wifi']),
            ],
            'synced_offline' => ['sometimes', 'boolean'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();

        // Uma nova tentativa de sincronização não deve
        // criar novamente uma marcação já recebida.
        $existing = $user->punches()
            ->withTrashed()
            ->where('uuid', $data['uuid'])
            ->first();

        if ($existing) {
            if ($existing->trashed()) 
            {
                return response()->json(
                [
                    'message' => 'Esta marcação foi excluída anteriormente. '
                        . 'A sincronização não pode recriá-la.',
                ], 409);
            }

            return response()->json(
            [
                'message' => 'Registro já sincronizado.',
                'data' => $existing,
            ]);
        }

        $data['punched_at'] = Carbon::parse(
            $data['punched_at']
        )->utc();

        $data['received_at'] = now();
        $data['source'] = $data['source'] ?? 'manual';
        $data['synced_offline'] = $data['synced_offline'] ?? false;

        $punch = DB::transaction(function () use ($user, $data) 
        {
            $punch = $user->punches()->create($data);

            $punch->audits()->create([
                'user_id' => $user->id,
                'action' => 'created',
                'reason' => 'Marcação registrada',
                'before_data' => null,
                'after_data' => $this->snapshot($punch),
            ]);

            $this->recalculateForPunch($user, $punch);

            return $punch;
        });

        return response()->json(
        [
            'message' => 'Ponto registrado com sucesso.',
            'data' => $punch,
        ], 201);        
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'type' => [
                'sometimes',
                Rule::in(['entry', 'break_start', 'break_end', 'exit']),
            ],
            'punched_at' => ['sometimes', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        if (!array_intersect(
            array_keys($data),
            ['type', 'punched_at', 'notes']
        )) {
            throw ValidationException::withMessages([
                'data' => ['Informe pelo menos um campo para corrigir.'],
            ]);
        }

        return DB::transaction(function () use ($request, $data, $id) {
            $punch = $request->user()
                ->punches()
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            $before = $this->snapshot($punch);

            $oldWorkDate = $this->workDateForPunch(
                $request->user(),
                $punch
            );
            
            $newWorkDate = $this->workDateForPunch(
                $request->user(),
                $punch
            );

            $this->recalculateDate($request->user(), $oldWorkDate);

            if ($newWorkDate !== $oldWorkDate) {
                $this->recalculateDate($request->user(), $newWorkDate);
            }            

            $reason = $data['reason'];
            unset($data['reason']);

            if (isset($data['punched_at'])) {
                $data['punched_at'] = Carbon::parse(
                    $data['punched_at']
                )->utc();
            }

            $punch->update($data);

            $punch->audits()->create([
                'user_id' => $request->user()->id,
                'action' => 'updated',
                'reason' => $reason,
                'before_data' => $before,
                'after_data' => $this->snapshot($punch),
            ]);

            return response()->json([
                'message' => 'Marcação corrigida, auditada e jornada recalculada.',
                'data' => $punch,
            ]);
        });
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        return DB::transaction(function () use ($request, $data, $id) {
            $punch = $request->user()
                ->punches()
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            $before = $this->snapshot($punch);

            $workDate = $this->workDateForPunch(
                $request->user(),
                $punch
            );

            $punch->delete();

            $punch->audits()->create([
                'user_id' => $request->user()->id,
                'action' => 'deleted',
                'reason' => $data['reason'],
                'before_data' => $before,
                'after_data' => $this->snapshot($punch),
            ]);

            $this->recalculateDate($request->user(), $workDate);            

            return response()->json([
                'message' => 'Marcação excluída logicamente e auditada.',
            ]);
        });
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        return DB::transaction(function () use ($request, $data, $id) {
            $punch = $request->user()
                ->punches()
                ->withTrashed()
                ->whereKey($id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!$punch->trashed()) {
                return response()->json([
                    'message' => 'Esta marcação já está ativa.',
                ], 409);
            }

            $before = $this->snapshot($punch);

            $workDate = $this->workDateForPunch(
                $request->user(),
                $punch
            );            

            $punch->restore();

            $punch->audits()->create([
                'user_id' => $request->user()->id,
                'action' => 'restored',
                'reason' => $data['reason'],
                'before_data' => $before,
                'after_data' => $this->snapshot($punch),
            ]);

            $this->recalculateDate($request->user(), $workDate);

            return response()->json([
                'message' => 'Marcação restaurada, auditada e jornada recalculada.',
                'data' => $punch,
            ]);
        });
    }

    public function audit(Request $request, int $id): JsonResponse
    {
        $punch = $request->user()
            ->punches()
            ->withTrashed()
            ->whereKey($id)
            ->firstOrFail();

        return response()->json(
            $punch->audits()
                ->with('user:id,name')
                ->orderByDesc('created_at')
                ->paginate(50)
        );
    }

    private function snapshot(Punch $punch): array
    {
        return [
            'uuid' => $punch->uuid,
            'type' => $punch->type,
            'punched_at' => $punch->punched_at?->toISOString(),
            'source' => $punch->source,
            'synced_offline' => $punch->synced_offline,
            'latitude' => $punch->latitude,
            'longitude' => $punch->longitude,
            'accuracy_meters' => $punch->accuracy_meters,
            'notes' => $punch->notes,
            'deleted_at' => $punch->deleted_at?->toISOString(),
        ];
    }

    private function workDateForPunch(
        User $user,
        Punch $punch
    ): string {
        $schedule = $user->workSchedules()
            ->where('active', true)
            ->orderByDesc('is_default')
            ->first();

        $timezone = $schedule?->timezone
            ?? config('app.timezone');

        return Carbon::parse($punch->punched_at, 'UTC')
            ->setTimezone($timezone)
            ->toDateString();
    }

    private function recalculateDate(
        User $user,
        string $date
    ): void {
        app(WorkdayCalculationService::class)
            ->calculate($user, $date);
    }

    private function recalculateForPunch(
        User $user,
        Punch $punch
    ): void {
        $date = $this->workDateForPunch($user, $punch);

        $this->recalculateDate($user, $date);
    }    
}

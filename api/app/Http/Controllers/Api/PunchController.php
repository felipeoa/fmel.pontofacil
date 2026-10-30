<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

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

        $punch = $user->punches()->create($data);

        return response()->json(
        [
            'message' => 'Ponto registrado com sucesso.',
            'data' => $punch,
        ], 201);        
    }
}

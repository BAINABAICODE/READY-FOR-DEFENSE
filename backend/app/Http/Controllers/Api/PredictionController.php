<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BreedingPrediction;
use Illuminate\Http\JsonResponse;

class PredictionController extends Controller
{
    public function index(): JsonResponse
    {
        $query = BreedingPrediction::query()
            ->select([
                'id',
                'parent_1_bird_id',
                'parent_2_bird_id',
                'status',
                'parent_1',
                'parent_2',
                'computation_result_id',
                'created_at',
            ])
            ->orderByDesc('created_at');

        if ($query->getConnection()->getDriverName() === 'pgsql') {
            $query->selectRaw("validation::jsonb #>> '{compatibility,compatibility_status}' as list_compatibility_status");
            $query->selectRaw("validation::jsonb #>> '{compatibility,label}' as list_compatibility_label");
        } else {
            $query->addSelect('validation');
        }

        $items = $query->get()->map(fn (BreedingPrediction $record) => $this->summary($record));

        return response()->json(['data' => $items]);
    }

    public function show(BreedingPrediction $prediction): JsonResponse
    {
        return response()->json(['data' => $this->detail($prediction)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(BreedingPrediction $record): array
    {
        $compatibility = is_array($record->validation) ? ($record->validation['compatibility'] ?? []) : [];

        return [
            'id' => $record->id,
            'parent_1_bird_id' => $record->parent_1_bird_id,
            'parent_2_bird_id' => $record->parent_2_bird_id,
            'parent_1' => $record->parent_1,
            'parent_2' => $record->parent_2,
            'status' => $record->status,
            'compatibility_status' => $record->getAttribute('list_compatibility_status')
                ?? ($compatibility['compatibility_status'] ?? null),
            'compatibility_label' => $record->getAttribute('list_compatibility_label')
                ?? ($compatibility['label'] ?? null),
            'computation_result_id' => $record->computation_result_id,
            'created_at' => $record->created_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(BreedingPrediction $record): array
    {
        return [
            ...$this->summary($record),
            'validation' => $record->validation,
            'prediction' => $record->prediction,
        ];
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LovebirdSpecies;
use App\Models\VisualMutation;
use App\Support\CatalogResponseCache;
use App\Support\VisualMutationCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisualMutationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = VisualMutation::query()
            ->with('species:id,common_name,scientific_name')
            ->orderBy('lovebird_species_id')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->filled('species_id')) {
            $query->where('lovebird_species_id', $request->integer('species_id'));
        }

        if ($request->filled('q')) {
            $term = '%'.$request->string('q').'%';
            $query->where(function ($builder) use ($term) {
                $builder
                    ->where('name', 'like', $term)
                    ->orWhere('series', 'like', $term)
                    ->orWhere('allele', 'like', $term)
                    ->orWhere('genotype', 'like', $term)
                    ->orWhere('genetic_code', 'like', $term)
                    ->orWhere('inheritance_type', 'like', $term)
                    ->orWhere('scientific_name', 'like', $term)
                    ->orWhere('species_name', 'like', $term)
                    ->orWhere('verification_status', 'like', $term);
            });
        }

        if ($request->filled('verification_status')) {
            $query->where('verification_status', $request->string('verification_status'));
        }

        $payloads = CatalogResponseCache::remember($request, 'visual-mutations', function () use ($query) {
            return $this->transformMany($query->get());
        });

        return response()->json(['data' => $payloads]);
    }

    public function bySpecies(LovebirdSpecies $species): JsonResponse
    {
        $items = VisualMutationCatalog::forSpecies($species->id);

        return response()->json(['data' => $this->transformMany($items)]);
    }

    public function show(VisualMutation $visualMutation): JsonResponse
    {
        $visualMutation->load('species:id,common_name,scientific_name');

        return response()->json([
            'data' => VisualMutationCatalog::geneticPayload($visualMutation),
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, VisualMutation>  $items
     * @return list<array<string, mixed>>
     */
    private function transformMany($items): array
    {
        $grouped = $items->groupBy('lovebird_species_id');
        $payloads = [];

        foreach ($items as $mutation) {
            $speciesMutations = $grouped->get($mutation->lovebird_species_id, collect());
            $payload = VisualMutationCatalog::geneticPayload($mutation, $speciesMutations);
            if ($payload !== null) {
                $payloads[] = $payload;
            }
        }

        return $payloads;
    }
}

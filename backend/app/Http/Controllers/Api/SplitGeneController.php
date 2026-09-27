<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LovebirdSpecies;
use App\Models\SplitGene;
use App\Support\CatalogResponseCache;
use App\Support\SplitGeneCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SplitGeneController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SplitGene::query()
            ->with('species:id,common_name,scientific_name')
            ->orderBy('lovebird_species_id')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->filled('species_id')) {
            $query->where('lovebird_species_id', $request->integer('species_id'));
        }

        if ($request->filled('sex')) {
            $sex = strtolower((string) $request->string('sex'));
            if ($sex === 'hen') {
                $query->where('hen_can_split', true);
            } elseif ($sex === 'cock') {
                $query->where('cock_can_split', true);
            }
        }

        if ($request->filled('q')) {
            $term = '%'.$request->string('q').'%';
            $query->where(function ($builder) use ($term) {
                $builder
                    ->where('name', 'like', $term)
                    ->orWhere('genetic_symbol', 'like', $term)
                    ->orWhere('genetic_code', 'like', $term)
                    ->orWhere('inheritance_type', 'like', $term)
                    ->orWhere('genetic_category', 'like', $term)
                    ->orWhere('scientific_name', 'like', $term)
                    ->orWhere('species_name', 'like', $term)
                    ->orWhere('verification_status', 'like', $term);
            });
        }

        if ($request->filled('verification_status')) {
            $query->where('verification_status', $request->string('verification_status'));
        }

        $items = CatalogResponseCache::remember($request, 'split-genes', function () use ($query) {
            return $query->get()
                ->map(fn (SplitGene $gene) => SplitGeneCatalog::geneticPayload($gene))
                ->values()
                ->all();
        });

        return response()->json(['data' => $items]);
    }

    public function bySpecies(Request $request, LovebirdSpecies $species): JsonResponse
    {
        $items = SplitGeneCatalog::forSpecies($species->id);

        if ($request->filled('sex')) {
            $sex = strtolower((string) $request->string('sex'));
            $items = $items->filter(fn (SplitGene $gene) => SplitGeneCatalog::sexCanCarry($gene, $sex))->values();
        }

        return response()->json([
            'data' => $items->map(fn (SplitGene $gene) => SplitGeneCatalog::geneticPayload($gene))->values(),
        ]);
    }

    public function show(SplitGene $splitGene): JsonResponse
    {
        $splitGene->load('species:id,common_name,scientific_name');

        return response()->json([
            'data' => SplitGeneCatalog::geneticPayload($splitGene),
        ]);
    }
}

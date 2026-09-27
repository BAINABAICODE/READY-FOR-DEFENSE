<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BaseColor;
use App\Models\LovebirdSpecies;
use App\Support\BaseColorCatalog;
use App\Support\CatalogResponseCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BaseColorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = BaseColor::query()
            ->with('species:id,common_name,scientific_name')
            ->orderBy('lovebird_species_id')
            ->orderBy('sort_order');

        if ($request->filled('species_id')) {
            $query->where('lovebird_species_id', $request->integer('species_id'));
        }

        if ($request->filled('q')) {
            $term = '%'.$request->string('q').'%';
            $query->where(function ($builder) use ($term) {
                $builder
                    ->where('name', 'like', $term)
                    ->orWhere('series', 'like', $term)
                    ->orWhere('genetic_code', 'like', $term)
                    ->orWhere('allele', 'like', $term)
                    ->orWhere('scientific_name', 'like', $term)
                    ->orWhere('species_name', 'like', $term);
            });
        }

        if ($request->filled('verification_status')) {
            $query->where('verification_status', $request->string('verification_status'));
        }

        $items = CatalogResponseCache::remember($request, 'base-colors', function () use ($query) {
            return $query->get()
                ->map(fn (BaseColor $color) => $this->transform($color))
                ->values()
                ->all();
        });

        return response()->json(['data' => $items]);
    }

    public function bySpecies(LovebirdSpecies $species): JsonResponse
    {
        $items = BaseColorCatalog::forSpecies($species->id)
            ->map(fn (BaseColor $color) => $this->transform($color));

        return response()->json(['data' => $items]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(BaseColor $color): array
    {
        return BaseColorCatalog::geneticPayload($color) ?? [];
    }
}

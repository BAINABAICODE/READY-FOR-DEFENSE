<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HeadToTailPhenotype;
use App\Models\LovebirdSpecies;
use App\Support\CatalogResponseCache;
use App\Support\HeadToTailPhenotypeCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LovebirdSpeciesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $species = CatalogResponseCache::remember($request, 'lovebird-species', function () {
            $identities = HeadToTailPhenotype::query()
                ->where('kind', HeadToTailPhenotype::KIND_SPECIES)
                ->get()
                ->keyBy('lovebird_species_id');

            return LovebirdSpecies::query()
                ->orderBy('id')
                ->get([
                    'id',
                    'common_name',
                    'alternate_names',
                    'scientific_name',
                    'species_group',
                    'has_eye_ring',
                    'description',
                ])
                ->map(function (LovebirdSpecies $row) use ($identities) {
                    return [
                        'id' => $row->id,
                        'common_name' => $row->common_name,
                        'alternate_names' => $row->alternate_names,
                        'scientific_name' => $row->scientific_name,
                        'species_group' => $row->species_group,
                        'has_eye_ring' => $row->has_eye_ring,
                        'description' => $row->description,
                        'head_to_tail' => HeadToTailPhenotypeCatalog::payloadOrIdentity(
                            $identities->get($row->id),
                            $row->id,
                        ),
                    ];
                })
                ->values()
                ->all();
        });

        return response()->json([
            'data' => $species,
        ]);
    }
}

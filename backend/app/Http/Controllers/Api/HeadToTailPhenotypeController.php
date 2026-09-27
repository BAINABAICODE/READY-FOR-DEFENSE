<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\CatalogResponseCache;
use App\Support\HeadToTailPhenotypeCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HeadToTailPhenotypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $payload = CatalogResponseCache::remember($request, 'head-to-tail-phenotypes', function () {
            return HeadToTailPhenotypeCatalog::indexPayload();
        });

        return response()->json([
            'data' => $payload,
        ]);
    }
}

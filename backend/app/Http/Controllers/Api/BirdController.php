<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreBirdRequest;
use App\Http\Requests\Api\UpdateBirdRequest;
use App\Models\Bird;
use App\Models\BirdGrandparent;
use App\Support\BaseColorCatalog;
use App\Support\SplitGeneCatalog;
use App\Support\VisualMutationCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class BirdController extends Controller
{
    public function index(): JsonResponse
    {
        $birds = Bird::query()
            ->with($this->listRelations())
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Bird $bird) => $this->transformList($bird));

        return response()->json(['data' => $birds]);
    }

    public function show(Bird $bird): JsonResponse
    {
        $bird->load($this->relations());

        return response()->json(['data' => $this->transform($bird)]);
    }

    public function store(StoreBirdRequest $request): JsonResponse
    {
        $bird = DB::transaction(function () use ($request) {
            $data = $request->safe()->except(['grandparents', 'visual_mutation_ids', 'split_gene_ids']);
            $visualMutationIds = $this->idList($request->input('visual_mutation_ids', []));
            $splitGeneIds = $this->idList($request->input('split_gene_ids', []));
            $data['visual_mutation_id'] = $visualMutationIds[0] ?? null;
            $data['split_gene_id'] = $splitGeneIds[0] ?? null;

            /** @var Bird $bird */
            $bird = Bird::query()->create($data);
            $bird->visualMutations()->sync($visualMutationIds);
            $bird->splitGenes()->sync($splitGeneIds);
            $this->syncGrandparents($bird, $request->input('grandparents', []));

            return $bird->fresh($this->relations());
        });

        return response()->json(['data' => $this->transform($bird)], 201);
    }

    public function update(UpdateBirdRequest $request, Bird $bird): JsonResponse
    {
        $bird = DB::transaction(function () use ($request, $bird) {
            $data = $request->safe()->except(['grandparents', 'visual_mutation_ids', 'split_gene_ids']);
            $visualMutationIds = $this->idList($request->input('visual_mutation_ids', []));
            $splitGeneIds = $this->idList($request->input('split_gene_ids', []));
            $data['visual_mutation_id'] = $visualMutationIds[0] ?? null;
            $data['split_gene_id'] = $splitGeneIds[0] ?? null;
            $bird->update($data);
            $bird->visualMutations()->sync($visualMutationIds);
            $bird->splitGenes()->sync($splitGeneIds);
            $this->syncGrandparents($bird, $request->input('grandparents', []));

            return $bird->fresh($this->relations());
        });

        return response()->json(['data' => $this->transform($bird)]);
    }

    public function destroy(Bird $bird): JsonResponse
    {
        $bird->delete();

        return response()->json(null, 204);
    }

    /**
     * @return list<string>
     */
    private function listRelations(): array
    {
        return [
            'species:id,common_name,alternate_names,scientific_name',
            'baseColor:id,name',
            'visualMutation:id,name,sort_order',
            'visualMutations:id,name,sort_order',
            'splitGene:id,name,sort_order',
            'splitGenes:id,name,sort_order',
            'grandparents.species:id,common_name,alternate_names,scientific_name',
            'grandparents.baseColor:id,name',
            'grandparents.visualMutation:id,name,sort_order',
            'grandparents.visualMutations:id,name,sort_order',
            'grandparents.splitGene:id,name,sort_order',
            'grandparents.splitGenes:id,name,sort_order',
        ];
    }

    /**
     * @return list<string>
     */
    private function relations(): array
    {
        return [
            'species:id,common_name,alternate_names,scientific_name',
            'baseColor',
            'visualMutation',
            'visualMutations',
            'splitGene',
            'splitGenes',
            'grandparents.species:id,common_name,alternate_names,scientific_name',
            'grandparents.baseColor',
            'grandparents.visualMutation',
            'grandparents.visualMutations',
            'grandparents.splitGene',
            'grandparents.splitGenes',
        ];
    }

    /**
     * @param  array<string, mixed>  $grandparents
     */
    private function syncGrandparents(Bird $bird, array $grandparents): void
    {
        foreach (Bird::GRANDPARENT_ROLES as $role) {
            $payload = is_array($grandparents[$role] ?? null) ? $grandparents[$role] : [];

            $visualMutationIds = $this->idList($payload['visual_mutation_ids'] ?? []);
            $splitGeneIds = $this->idList($payload['split_gene_ids'] ?? []);

            $fields = [
                'species_id' => $payload['species_id'] ?? null,
                'base_color_id' => $payload['base_color_id'] ?? null,
                'visual_mutation_id' => $visualMutationIds[0] ?? null,
                'split_gene_id' => $splitGeneIds[0] ?? null,
            ];

            $hasData = collect($fields)->contains(fn ($value) => $value !== null && $value !== '')
                || $visualMutationIds !== []
                || $splitGeneIds !== [];

            if (! $hasData) {
                BirdGrandparent::query()
                    ->where('bird_id', $bird->id)
                    ->where('role', $role)
                    ->delete();

                continue;
            }

            $record = BirdGrandparent::query()->updateOrCreate(
                [
                    'bird_id' => $bird->id,
                    'role' => $role,
                ],
                $fields,
            );
            $record->visualMutations()->sync($visualMutationIds);
            $record->splitGenes()->sync($splitGeneIds);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function transformList(Bird $bird): array
    {
        $grandparents = [];

        foreach (Bird::GRANDPARENT_ROLES as $role) {
            $record = $bird->grandparents->firstWhere('role', $role);
            $grandparents[$role] = $record ? $this->transformGrandparentList($record) : null;
        }

        return [
            'id' => $bird->id,
            'bird_id' => $bird->bird_id,
            'age_months' => $bird->age_months,
            'sex' => $bird->sex,
            'sex_label' => $bird->sex === Bird::SEX_HEN ? 'Hen — Female' : 'Cock — Male',
            'species_id' => $bird->species_id,
            'base_color_id' => $bird->base_color_id,
            'visual_mutation_id' => $bird->visual_mutation_id,
            'visual_mutation_ids' => $this->idList($bird->visualMutations->pluck('id')->all()),
            'split_gene_id' => $bird->split_gene_id,
            'split_gene_ids' => $bird->splitGenes->pluck('id')->values()->all(),
            'species' => $this->speciesPayload($bird->species),
            'base_color' => $this->namedPayload($bird->baseColor),
            'visual_mutation' => $this->namedPayload($bird->visualMutation),
            'visual_mutations' => $bird->visualMutations
                ->map(fn ($mutation) => $this->namedPayload($mutation))
                ->filter()
                ->values()
                ->all(),
            'split_gene' => $this->namedPayload($bird->splitGene),
            'split_genes' => $bird->splitGenes
                ->map(fn ($gene) => $this->namedPayload($gene))
                ->filter()
                ->values()
                ->all(),
            'grandparents' => $grandparents,
            'created_at' => $bird->created_at,
            'updated_at' => $bird->updated_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Bird $bird): array
    {
        $grandparents = [];

        foreach (Bird::GRANDPARENT_ROLES as $role) {
            $record = $bird->grandparents->firstWhere('role', $role);
            $grandparents[$role] = $record ? $this->transformGrandparent($record) : null;
        }

        return [
            'id' => $bird->id,
            'bird_id' => $bird->bird_id,
            'age_months' => $bird->age_months,
            'sex' => $bird->sex,
            'sex_label' => $bird->sex === Bird::SEX_HEN ? 'Hen — Female' : 'Cock — Male',
            'species_id' => $bird->species_id,
            'base_color_id' => $bird->base_color_id,
            'visual_mutation_id' => $bird->visual_mutation_id,
            'visual_mutation_ids' => $this->idList($bird->visualMutations->pluck('id')->all()),
            'split_gene_id' => $bird->split_gene_id,
            'split_gene_ids' => $bird->splitGenes->pluck('id')->values()->all(),
            'species' => $this->speciesPayload($bird->species),
            'base_color' => BaseColorCatalog::geneticPayload($bird->baseColor),
            'visual_mutation' => VisualMutationCatalog::geneticPayload($bird->visualMutation),
            'visual_mutations' => $bird->visualMutations
                ->map(fn ($mutation) => VisualMutationCatalog::geneticPayload($mutation))
                ->filter()
                ->values()
                ->all(),
            'split_gene' => SplitGeneCatalog::geneticPayload($bird->splitGene),
            'split_genes' => $bird->splitGenes
                ->map(fn ($gene) => SplitGeneCatalog::geneticPayload($gene))
                ->filter()
                ->values()
                ->all(),
            'grandparents' => $grandparents,
            'created_at' => $bird->created_at,
            'updated_at' => $bird->updated_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformGrandparent(BirdGrandparent $record): array
    {
        return [
            'id' => $record->id,
            'role' => $record->role,
            'species_id' => $record->species_id,
            'base_color_id' => $record->base_color_id,
            'visual_mutation_id' => $record->visual_mutation_id,
            'visual_mutation_ids' => $record->visualMutations->pluck('id')->values()->all(),
            'split_gene_id' => $record->split_gene_id,
            'split_gene_ids' => $record->splitGenes->pluck('id')->values()->all(),
            'species' => $this->speciesPayload($record->species),
            'base_color' => BaseColorCatalog::geneticPayload($record->baseColor),
            'visual_mutation' => VisualMutationCatalog::geneticPayload($record->visualMutation),
            'visual_mutations' => $record->visualMutations
                ->map(fn ($mutation) => VisualMutationCatalog::geneticPayload($mutation))
                ->filter()
                ->values()
                ->all(),
            'split_gene' => SplitGeneCatalog::geneticPayload($record->splitGene),
            'split_genes' => $record->splitGenes
                ->map(fn ($gene) => SplitGeneCatalog::geneticPayload($gene))
                ->filter()
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformGrandparentList(BirdGrandparent $record): array
    {
        return [
            'id' => $record->id,
            'role' => $record->role,
            'species_id' => $record->species_id,
            'base_color_id' => $record->base_color_id,
            'visual_mutation_id' => $record->visual_mutation_id,
            'visual_mutation_ids' => $record->visualMutations->pluck('id')->values()->all(),
            'split_gene_id' => $record->split_gene_id,
            'split_gene_ids' => $record->splitGenes->pluck('id')->values()->all(),
            'species' => $this->speciesPayload($record->species),
            'base_color' => $this->namedPayload($record->baseColor),
            'visual_mutation' => $this->namedPayload($record->visualMutation),
            'visual_mutations' => $record->visualMutations
                ->map(fn ($mutation) => $this->namedPayload($mutation))
                ->filter()
                ->values()
                ->all(),
            'split_gene' => $this->namedPayload($record->splitGene),
            'split_genes' => $record->splitGenes
                ->map(fn ($gene) => $this->namedPayload($gene))
                ->filter()
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function namedPayload(?object $record): ?array
    {
        if ($record === null || ! isset($record->id, $record->name)) {
            return null;
        }

        return [
            'id' => $record->id,
            'name' => $record->name,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function speciesPayload(mixed $species): ?array
    {
        if ($species === null) {
            return null;
        }

        return [
            'id' => $species->id,
            'common_name' => $species->common_name,
            'alternate_names' => $species->alternate_names,
            'scientific_name' => $species->scientific_name,
            'label' => $this->speciesLabel($species->common_name, $species->alternate_names),
        ];
    }

    /**
     * @param  mixed  $ids
     * @return list<int>
     */
    private function idList(mixed $ids): array
    {
        if (! is_array($ids)) {
            return [];
        }

        $normalized = [];
        foreach ($ids as $id) {
            if ($id === null || $id === '') {
                continue;
            }
            $normalized[] = (int) $id;
        }

        return array_values(array_unique($normalized));
    }

    private function speciesLabel(string $commonName, ?string $alternateNames): string
    {
        if ($alternateNames) {
            return "{$commonName} ({$alternateNames})";
        }

        return $commonName;
    }
}

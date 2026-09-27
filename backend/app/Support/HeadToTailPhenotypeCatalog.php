<?php

namespace App\Support;

use App\Models\HeadToTailPhenotype;
use App\Models\LovebirdSpecies;
use App\Models\VisualMutation;

class HeadToTailPhenotypeCatalog
{
    /**
     * @return array<string, mixed>|null
     */
    public static function payload(?HeadToTailPhenotype $row): ?array
    {
        if (! $row) {
            return null;
        }

        return [
            'id' => $row->id,
            'kind' => $row->kind,
            'species_id' => $row->lovebird_species_id,
            'visual_mutation_id' => $row->visual_mutation_id,
            'mutation_name' => $row->mutation_name,
            'eyes' => $row->eyes,
            'head' => $row->head,
            'neck' => $row->neck,
            'body' => $row->body,
            'wings' => $row->wings,
            'rump' => $row->rump,
            'tail' => $row->tail,
            'pigment_notes' => $row->pigment_notes,
            'source' => $row->source,
            'phenotype_signature' => $row->phenotype_signature,
        ];
    }

    /**
     * @param  list<string>  $mutationNames
     * @return array<string, mixed>|null
     */
    public static function composeForOutcome(mixed $species, array $mutationNames = [], mixed $baseColor = null, mixed $sex = null): ?array
    {
        $speciesId = HeadToTailPhenotypeDataset::resolveSpeciesId($species);

        return HeadToTailPhenotypeDataset::compose($speciesId, $mutationNames, $baseColor, $sex);
    }

    /**
     * @return array<string, mixed>
     */
    public static function indexPayload(): array
    {
        $identities = HeadToTailPhenotype::query()
            ->where('kind', HeadToTailPhenotype::KIND_SPECIES)
            ->orderBy('lovebird_species_id')
            ->get()
            ->map(fn (HeadToTailPhenotype $row) => self::payload($row))
            ->values()
            ->all();

        if ($identities === []) {
            $identities = array_values(array_map(
                fn (array $row) => self::identityPayload($row),
                HeadToTailPhenotypeDataset::speciesIdentities(),
            ));
        }

        $mutations = HeadToTailPhenotype::query()
            ->where('kind', HeadToTailPhenotype::KIND_MUTATION)
            ->orderBy('lovebird_species_id')
            ->orderBy('mutation_name')
            ->get()
            ->map(fn (HeadToTailPhenotype $row) => self::payload($row))
            ->values()
            ->all();

        return [
            'species_identities' => $identities,
            'visual_mutations' => $mutations,
            'overlays' => HeadToTailPhenotypeDataset::overlaysPayload(),
            'region_order' => HeadToTailPhenotypeDataset::REGIONS,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function forSpecies(int $speciesId): ?array
    {
        $row = HeadToTailPhenotype::query()
            ->where('kind', HeadToTailPhenotype::KIND_SPECIES)
            ->where('lovebird_species_id', $speciesId)
            ->first();

        return self::payloadOrIdentity($row, $speciesId);
    }

    public static function payloadOrIdentity(?HeadToTailPhenotype $row, int $speciesId): ?array
    {
        return self::payload($row) ?? self::identityPayload(HeadToTailPhenotypeDataset::identityFor($speciesId) ?? []);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function forMutation(VisualMutation $mutation): ?array
    {
        $row = HeadToTailPhenotype::query()
            ->where('kind', HeadToTailPhenotype::KIND_MUTATION)
            ->where('visual_mutation_id', $mutation->id)
            ->first();

        if ($row) {
            return self::payload($row);
        }

        return HeadToTailPhenotypeDataset::uniqueForMutation(
            $mutation->lovebird_species_id,
            $mutation->name,
        );
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private static function identityPayload(array $row): ?array
    {
        if ($row === []) {
            return null;
        }

        return [
            'id' => null,
            'kind' => HeadToTailPhenotype::KIND_SPECIES,
            'species_id' => $row['species_id'] ?? null,
            'visual_mutation_id' => null,
            'mutation_name' => null,
            'eyes' => $row['eyes'] ?? null,
            'head' => $row['head'] ?? null,
            'neck' => $row['neck'] ?? null,
            'body' => $row['body'] ?? null,
            'wings' => $row['wings'] ?? null,
            'rump' => $row['rump'] ?? null,
            'tail' => $row['tail'] ?? null,
            'pigment_notes' => $row['pigment_notes'] ?? null,
            'source' => $row['source'] ?? null,
        ];
    }

    public static function speciesHasIdentity(LovebirdSpecies $species): bool
    {
        return HeadToTailPhenotypeDataset::identityFor($species->id) !== null;
    }
}

<?php

namespace Database\Seeders;

use App\Models\HeadToTailPhenotype;
use App\Models\LovebirdSpecies;
use App\Models\VisualMutation;
use App\Support\HeadToTailPhenotypeDataset;
use Illuminate\Database\Seeder;
use RuntimeException;

class HeadToTailPhenotypeSeeder extends Seeder
{
    public function run(): void
    {
        $kept = [];
        $signatures = [];

        foreach (HeadToTailPhenotypeDataset::speciesIdentities() as $speciesId => $identity) {
            $species = LovebirdSpecies::query()->find($speciesId);
            if (! $species) {
                throw new RuntimeException("No lovebird species matches id {$speciesId} for head-to-tail seeding.");
            }

            $row = HeadToTailPhenotype::query()->updateOrCreate(
                [
                    'kind' => HeadToTailPhenotype::KIND_SPECIES,
                    'lovebird_species_id' => $species->id,
                    'visual_mutation_id' => null,
                ],
                $this->regionAttributes($identity, [
                    'mutation_name' => null,
                    'pigment_notes' => $identity['pigment_notes'] ?? null,
                    'source' => $identity['source'] ?? null,
                    'phenotype_signature' => HeadToTailPhenotypeDataset::signature($identity),
                ]),
            );
            $signature = (string) $row->phenotype_signature;
            if ($signature === '' || in_array($signature, $signatures, true)) {
                throw new RuntimeException(
                    "Head-to-tail species identity for species {$species->id} is not unique."
                );
            }
            $signatures[] = $signature;
            $kept[] = $row->id;
        }

        $mutations = VisualMutation::query()->orderBy('id')->get();
        foreach ($mutations as $mutation) {
            $composed = HeadToTailPhenotypeDataset::uniqueForMutation(
                $mutation->lovebird_species_id,
                $mutation->name,
            );
            if ($composed === null) {
                throw new RuntimeException(
                    "No species identity for visual mutation {$mutation->name} (species {$mutation->lovebird_species_id})."
                );
            }

            $signature = (string) ($composed['phenotype_signature'] ?? '');
            if ($signature === '' || in_array($signature, $signatures, true)) {
                throw new RuntimeException(
                    "Head-to-tail phenotype for {$mutation->name} (species {$mutation->lovebird_species_id}) is not unique."
                );
            }
            $signatures[] = $signature;

            $row = HeadToTailPhenotype::query()->updateOrCreate(
                [
                    'kind' => HeadToTailPhenotype::KIND_MUTATION,
                    'lovebird_species_id' => $mutation->lovebird_species_id,
                    'visual_mutation_id' => $mutation->id,
                ],
                $this->regionAttributes($composed, [
                    'mutation_name' => $mutation->name,
                    'pigment_notes' => $composed['pigment_notes'] ?? $mutation->phenotype,
                    'source' => $mutation->scientific_source ?: ($composed['source'] ?? null),
                    'phenotype_signature' => $signature,
                ]),
            );
            $kept[] = $row->id;
        }

        HeadToTailPhenotype::query()
            ->whereNotIn('id', $kept)
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $map
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function regionAttributes(array $map, array $extra): array
    {
        return array_merge([
            'eyes' => $map['eyes'],
            'head' => $map['head'],
            'neck' => $map['neck'],
            'body' => $map['body'],
            'wings' => $map['wings'],
            'rump' => $map['rump'],
            'tail' => $map['tail'],
        ], $extra);
    }
}

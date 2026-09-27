<?php

namespace App\Support;

use App\Models\VisualMutation;
use Illuminate\Support\Collection;

/**
 * Visual-mutation records for species-aware inheritance calculations.
 * Source fields are returned exactly as stored. Compatibility keys are
 * extracted from allele and phenotype wording already present in the dataset.
 */
class VisualMutationCatalog
{
    /**
     * @return Collection<int, VisualMutation>
     */
    public static function forSpecies(int $speciesId): Collection
    {
        return VisualMutation::query()
            ->where('lovebird_species_id', $speciesId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Dosage pair symbol from allele text that begins "one X" or "two X".
     * Rows without that wording have no dosage key.
     */
    public static function dosageKey(?string $allele): ?string
    {
        if ($allele === null || $allele === '') {
            return null;
        }

        if (preg_match('/^(one|two)\s+([A-Za-z0-9*]+)/', $allele, $match) === 1) {
            return $match[2];
        }

        return null;
    }

    /**
     * 1 for a single-factor row, 2 for the double-factor row of the same allele.
     */
    public static function dosageRank(?string $allele): ?int
    {
        if ($allele !== null && preg_match('/^(one|two)\s+/', $allele, $match) === 1) {
            return $match[1] === 'two' ? 2 : 1;
        }

        return null;
    }

    /**
     * Shared locus for occupancy rules. Alleles of one gene share a key.
     */
    public static function locusKey(?string $series, ?string $allele): ?string
    {
        return GeneticLocus::fromSeriesAndAllele($series, $allele);
    }

    /**
     * @param  Collection<int, VisualMutation>  $mutations
     * @return array<int, list<string>>
     */
    public static function combinationNamesById(Collection $mutations): array
    {
        $byName = [];
        foreach ($mutations as $mutation) {
            $byName[self::nameKey($mutation->name)] = $mutation;
        }

        $partners = [];
        foreach ($mutations as $mutation) {
            $partners[$mutation->id] = [];
        }

        foreach ($mutations as $mutation) {
            foreach (self::partnerNamesFromPhenotype($mutation->phenotype) as $partnerName) {
                $partner = $byName[self::nameKey($partnerName)] ?? null;
                if (! $partner || $partner->id === $mutation->id) {
                    continue;
                }

                $partners[$mutation->id][] = $partner->name;
                $partners[$partner->id][] = $mutation->name;
            }
        }

        foreach ($partners as $id => $names) {
            $partners[$id] = array_values(array_unique($names));
        }

        return $partners;
    }

    /**
     * @param  list<int>  $ids
     */
    public static function incompatibleMessage(array $ids, int $speciesId, ?string $sex = null, ?Collection $splits = null): ?string
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return null;
        }

        $mutations = self::forSpecies($speciesId)->whereIn('id', $ids)->values();
        if ($mutations->count() !== count($ids)) {
            return 'Visual mutations must belong to the selected species.';
        }

        $message = self::selectionMessage($mutations, $sex);
        if ($message !== null) {
            return $message;
        }

        return $splits ? self::splitConflictMessage($mutations, $splits) : null;
    }

    /**
     * @param  Collection<int, object>  $splits
     */
    public static function splitConflictMessage(Collection $mutations, Collection $splits): ?string
    {
        foreach ($mutations as $mutation) {
            $locus = self::locusKey($mutation->series ?? null, $mutation->allele ?? null);
            if ($locus === null) {
                continue;
            }

            foreach ($splits as $split) {
                if (SplitGeneCatalog::locusKey($split) !== $locus) {
                    continue;
                }

                $hidden = SplitGeneCatalog::visualName($split);

                return 'This bird is split for '.$hidden.', so '.$mutation->name.' stays hidden. A split bird does not show this mutation.';
            }
        }

        return null;
    }

    /**
     * Mutations on different loci can be combined. One locus can contribute
     * at most two alleles, and a single-factor row cannot be stored with its
     * double-factor row. A hen has one Z chromosome.
     *
     * @param  Collection<int, VisualMutation>  $mutations
     */
    public static function selectionMessage(Collection $mutations, ?string $sex = null): ?string
    {
        $selected = $mutations->values();
        $hen = self::isHen($sex);

        foreach ($selected as $mutation) {
            if ($hen && self::isSexLinked($mutation->inheritance_type) && self::dosageRank($mutation->allele) === 2) {
                return $mutation->name.' cannot be selected for a hen. A hen has one Z chromosome, so she cannot show the double-factor form of a sex-linked mutation.';
            }
        }

        $dosageSeen = [];
        foreach ($selected as $mutation) {
            $dosageKey = self::dosageKey($mutation->allele);
            if ($dosageKey === null) {
                continue;
            }

            if (isset($dosageSeen[$dosageKey])) {
                return $mutation->name.' cannot be combined with '.$dosageSeen[$dosageKey].'. A bird is either the single-factor or the double-factor form of this mutation, not both.';
            }

            $dosageSeen[$dosageKey] = $mutation->name;
        }

        $locusGroups = [];
        foreach ($selected as $mutation) {
            $locusKey = self::locusKey($mutation->series, $mutation->allele);
            if ($locusKey === null) {
                continue;
            }

            $locusGroups[$locusKey][] = $mutation;
        }

        foreach ($locusGroups as $group) {
            $limit = $hen && self::isSexLinked($group[0]->inheritance_type) ? 1 : 2;
            if (count($group) <= $limit) {
                continue;
            }

            $names = array_map(static fn (VisualMutation $mutation) => $mutation->name, $group);
            $listed = self::joinNames($names);

            if ($limit === 1) {
                return $listed.' cannot be combined. A hen has one Z chromosome, so she can show only one allele of this sex-linked locus.';
            }

            return $listed.' cannot all be selected. A bird has two copies of this gene, so only two alleles of this locus can be present.';
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function geneticPayload(?VisualMutation $mutation, ?Collection $speciesMutations = null): ?array
    {
        if (! $mutation) {
            return null;
        }

        $speciesMutations ??= $mutation->lovebird_species_id
            ? self::forSpecies($mutation->lovebird_species_id)
            : collect([$mutation]);

        $combinations = self::combinationNamesById($speciesMutations);

        return [
            'id' => $mutation->id,
            'name' => $mutation->name,
            'species_id' => $mutation->lovebird_species_id,
            'species_name' => $mutation->species_name,
            'scientific_name' => $mutation->scientific_name,
            'series' => $mutation->series,
            'sf' => $mutation->sf,
            'df' => $mutation->df,
            'allele' => $mutation->allele,
            'genotype' => $mutation->genotype,
            'genetic_code' => $mutation->genetic_code,
            'inheritance_type' => $mutation->inheritance_type,
            'phenotype' => $mutation->phenotype,
            'verification_status' => $mutation->verification_status,
            'scientific_source' => $mutation->scientific_source,
            'computable' => $mutation->computable,
            'dosage_key' => self::dosageKey($mutation->allele),
            'dosage_rank' => self::dosageRank($mutation->allele),
            'locus_key' => self::locusKey($mutation->series, $mutation->allele),
            'combination_names' => $combinations[$mutation->id] ?? [],
            'head_to_tail' => HeadToTailPhenotypeDataset::uniqueForMutation($mutation->lovebird_species_id, $mutation->name),
        ];
    }

    /**
     * Partner names taken only from combination sentences in the source phenotype.
     *
     * @return list<string>
     */
    private static function partnerNamesFromPhenotype(?string $phenotype): array
    {
        if ($phenotype === null || $phenotype === '') {
            return [];
        }

        $names = [];

        if (preg_match('/NSL Ino plus this allele, a combination/i', $phenotype) === 1) {
            $names[] = 'NSL Ino';
        }

        if (preg_match('/this allele with NSL Ino/i', $phenotype) === 1) {
            $names[] = 'NSL Ino';
        }

        if (preg_match('/Opaline NSL ino is a combination/i', $phenotype) === 1) {
            $names[] = 'NSL Ino';
        }

        return $names;
    }

    private static function nameKey(?string $name): string
    {
        return strtolower(trim((string) $name));
    }

    private static function isHen(?string $sex): bool
    {
        return strtolower(trim((string) $sex)) === 'hen';
    }

    private static function isSexLinked(?string $inheritance): bool
    {
        return $inheritance !== null && preg_match('/^sex-linked/i', $inheritance) === 1;
    }

    /**
     * @param  list<string>  $names
     */
    private static function joinNames(array $names): string
    {
        if (count($names) < 2) {
            return $names[0] ?? '';
        }

        $last = array_pop($names);

        return implode(', ', $names).' and '.$last;
    }
}

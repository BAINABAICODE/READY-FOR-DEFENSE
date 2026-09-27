<?php

namespace App\Support;

use App\Models\BaseColor;
use App\Models\Bird;
use App\Models\SplitGene;
use Illuminate\Support\Collection;

/**
 * Split/hidden-gene records for species-aware inheritance calculations.
 * Genetic codes and allele symbols are returned exactly as stored.
 */
class SplitGeneCatalog
{
    /**
     * @return Collection<int, SplitGene>
     */
    public static function forSpecies(int $speciesId): Collection
    {
        return SplitGene::query()
            ->where('lovebird_species_id', $speciesId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public static function carrierLocus(?string $geneticCategory, ?string $mutantAllele = null): ?string
    {
        return GeneticLocus::fromSeriesAndAllele($geneticCategory, $mutantAllele);
    }

    public static function locusKey(object $gene): ?string
    {
        return GeneticLocus::fromSeriesAndAllele(
            $gene->genetic_category ?? null,
            $gene->mutant_allele ?? $gene->genetic_symbol ?? null,
        );
    }

    public static function visualName(object $gene): string
    {
        $phenotype = trim(explode(',', (string) ($gene->phenotype_when_visual ?? ''))[0]);
        if ($phenotype !== '') {
            return $phenotype;
        }

        return trim((string) preg_replace('/^split\s+/i', '', (string) ($gene->name ?? '')));
    }

    public static function sexCanCarry(SplitGene $gene, ?string $sex): bool
    {
        if ($sex === Bird::SEX_HEN) {
            return $gene->hen_can_split === true;
        }

        if ($sex === Bird::SEX_COCK) {
            return $gene->cock_can_split === true;
        }

        return true;
    }

    /**
     * @param  list<int>  $ids
     * @param  Collection<int, object>|null  $visualMutations
     */
    public static function incompatibleMessage(
        array $ids,
        int $speciesId,
        ?string $sex = null,
        ?BaseColor $color = null,
        ?Collection $visualMutations = null,
    ): ?string {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if ($ids === []) {
            return null;
        }

        $genes = self::forSpecies($speciesId)->whereIn('id', $ids)->values();
        if ($genes->count() !== count($ids)) {
            return 'Split/hidden genes must belong to the selected species.';
        }

        return self::selectionMessage($genes, $sex, $color, $visualMutations);
    }

    /**
     * A split is the hidden heterozygous form. One locus can hide one mutant
     * allele. A visual mutation or a mutant blue-series color already occupies
     * that gene, so it cannot also be stored as a split.
     *
     * @param  Collection<int, SplitGene>  $genes
     * @param  Collection<int, object>|null  $visualMutations
     */
    public static function selectionMessage(
        Collection $genes,
        ?string $sex = null,
        ?BaseColor $color = null,
        ?Collection $visualMutations = null,
    ): ?string {
        $selected = $genes->values();
        $visuals = $visualMutations ?? collect();

        foreach ($selected as $gene) {
            if (! self::sexCanCarry($gene, $sex)) {
                if ($sex === Bird::SEX_HEN) {
                    return $gene->name.' cannot be stored as a hidden split for a hen. A hen with this sex-linked gene on her single Z is visual, not split.';
                }

                return $gene->name.' cannot be stored as a hidden split for a cock.';
            }
        }

        $locusSeen = [];
        foreach ($selected as $gene) {
            $locus = self::locusKey($gene);
            if ($locus === null) {
                continue;
            }

            if (isset($locusSeen[$locus])) {
                return $gene->name.' cannot be combined with '.$locusSeen[$locus].'. A split keeps one wild-type copy of this gene, so only one hidden allele can be stored.';
            }

            $locusSeen[$locus] = $gene->name;
        }

        foreach ($selected as $gene) {
            $locus = self::locusKey($gene);
            if (GeneticLocus::isBlLocus($locus) && ! GeneticLocus::canHideBlSplit($color?->genetic_code)) {
                $colorName = $color?->name ?: 'This base color';

                return $gene->name.' can only stay hidden on a green-series bird. '.$colorName.' already uses both copies of the blue gene.';
            }

            if ($locus === null) {
                continue;
            }

            foreach ($visuals as $mutation) {
                $visualLocus = VisualMutationCatalog::locusKey($mutation->series ?? null, $mutation->allele ?? null);
                if ($visualLocus === $locus) {
                    $visualName = $mutation->name ?? 'This mutation';

                    return $visualName.' is already visual on this bird, so '.$gene->name.' cannot also be stored as a hidden split. A split is the hidden heterozygous form.';
                }
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function geneticPayload(?SplitGene $gene): ?array
    {
        if (! $gene) {
            return null;
        }

        $locusKey = self::locusKey($gene);

        return [
            'id' => $gene->id,
            'name' => $gene->name,
            'species_id' => $gene->lovebird_species_id,
            'species_name' => $gene->species_name,
            'scientific_name' => $gene->scientific_name,
            'genetic_symbol' => $gene->genetic_symbol,
            'allele' => $gene->genetic_symbol,
            'wild_type_allele' => $gene->wild_type_allele,
            'mutant_allele' => $gene->mutant_allele,
            'inheritance_type' => $gene->inheritance_type,
            'genetic_category' => $gene->genetic_category,
            'cock_can_split' => $gene->cock_can_split,
            'hen_can_split' => $gene->hen_can_split,
            'heterozygous_genotype' => $gene->heterozygous_genotype,
            'homozygous_genotype' => $gene->homozygous_genotype,
            'genotype' => $gene->heterozygous_genotype,
            'genetic_code' => $gene->genetic_code,
            'phenotype_when_visual' => $gene->phenotype_when_visual,
            'description' => $gene->description,
            'phenotype' => $gene->description,
            'verification_status' => $gene->verification_status,
            'scientific_source' => $gene->scientific_source,
            'computable' => $gene->computable,
            'carrier_locus' => $locusKey,
            'locus_key' => $locusKey,
        ];
    }
}

<?php

namespace App\Services\Breeding;

use App\Models\Bird;
use App\Models\SpeciesBreedingCompatibility;

/**
 * AGAPORA GICA — Genetic Inheritance Compatibility Analysis.
 *
 * Answers: how compatible are these two lovebirds as a breeding pair
 * based on stored genetic information and documented rules?
 *
 * Does not invent genetics. Does not alter RBGIA Mendelian fractions.
 * Uses RBGIA outcomes only as supporting evidence for risk/diversity notes.
 */
class GicaAnalyzer
{
    /**
     * @param  array<string, mixed>  $speciesCompatibility
     * @param  array<string, mixed>  $validation
     * @param  array<string, mixed>  $prediction
     * @param  Bird  $parentOne
     * @param  Bird  $parentTwo
     * @return array<string, mixed>
     */
    public function analyze(
        array $speciesCompatibility,
        array $validation,
        array $prediction,
        Bird $parentOne,
        Bird $parentTwo,
    ): array {
        $errors = collect($validation['errors'] ?? []);
        $warnings = collect($validation['warnings'] ?? []);
        $information = collect($validation['information'] ?? []);

        $speciesFactor = $this->speciesFactor($speciesCompatibility);
        $inheritanceFactor = $this->inheritanceInformationFactor($validation, $prediction, $parentOne, $parentTwo);
        $mutationFactor = $this->mutationCompatibilityFactor($errors, $warnings, $prediction);
        $riskFactor = $this->geneticRiskFactor($speciesCompatibility, $errors, $warnings, $information, $prediction);
        $diversityFactor = $this->geneticDiversityFactor($parentOne, $parentTwo, $prediction);
        $constraintFactor = $this->breedingConstraintFactor($speciesCompatibility, $validation, $warnings);

        $factors = [
            $speciesFactor,
            $inheritanceFactor,
            $mutationFactor,
            $riskFactor,
            $diversityFactor,
            $constraintFactor,
        ];

        $rawScore = array_sum(array_map(fn ($factor) => (int) ($factor['points'] ?? 0), $factors));
        $blocking = $errors->isNotEmpty()
            || ! ($speciesCompatibility['compatible'] ?? false)
            || ($speciesCompatibility['status'] ?? null) === SpeciesBreedingCompatibility::UNSUPPORTED;

        if ($blocking) {
            $score = min($rawScore, 39);
            $label = 'Not Recommended';
        } else {
            $score = max(0, min(100, $rawScore));
            $label = $this->classify($score);
        }

        $positive = [];
        $warningsWhy = [];
        foreach ($factors as $factor) {
            foreach ($factor['positives'] ?? [] as $item) {
                $positive[] = $item;
            }
            foreach ($factor['warnings'] ?? [] as $item) {
                $warningsWhy[] = $item;
            }
        }
        foreach ($errors as $error) {
            if (is_array($error) && ! empty($error['message'])) {
                $warningsWhy[] = $error['message'];
            }
        }

        $positive = array_values(array_unique($positive));
        $warningsWhy = array_values(array_unique($warningsWhy));

        $recommendation = match ($label) {
            'Excellent' => 'The pair shows excellent rule-based genetic compatibility based on documented species status, available genotypes, and no blocking breeding constraints.',
            'Good' => 'The pair shows good genetic compatibility. Proceed with attention to listed warnings and verification status.',
            'Fair' => 'The pair shows fair genetic compatibility. Review incomplete genetics, mutation notes, and breeding constraints before relying on the analysis.',
            'Poor' => 'The pair shows poor genetic compatibility under AGAPORA rules. Prefer a better-documented same-species pair when possible.',
            default => 'This pairing is not recommended for genetic prediction until blocking compatibility or validation issues are resolved.',
        };

        return [
            'score' => $score,
            'label' => $label,
            'classification_scale' => [
                'Excellent' => '90–100',
                'Good' => '75–89',
                'Fair' => '60–74',
                'Poor' => '40–59',
                'Not Recommended' => '0–39',
            ],
            'recommendation' => $recommendation,
            'summary' => sprintf(
                'The selected pair is classified as %s (%d / 100) based on documented genetic compatibility rules and available parental information.',
                strtoupper($label),
                $score,
            ),
            'why' => [
                'headline' => 'Why this pair received this score',
                'positives' => $positive,
                'warnings' => $warningsWhy,
            ],
            'breakdown' => array_map(fn ($factor) => [
                'factor' => $factor['name'],
                'key' => $factor['key'],
                'value' => $factor['value'],
                'max_points' => $factor['max'],
                'points' => $factor['points'],
                'contribution' => $factor['points'],
                'detail' => $factor['detail'],
            ], $factors),
            'risk' => $riskFactor['risk_payload'],
            'diversity' => $diversityFactor['diversity_payload'],
            'mutation_compatibility' => [
                'level' => $mutationFactor['level'],
                'detail' => $mutationFactor['detail'],
            ],
            'factors' => $factors,
            'raw_score_before_cap' => $rawScore,
            'blocking' => $blocking,
            'note' => 'GICA evaluates pair compatibility. It does not change RBGIA Mendelian inheritance fractions. Offspring distributions support — they do not replace — the compatibility analysis.',
            'methodology' => 'AGAPORA weighted GICA rubric: species compatibility, inheritance-information completeness, mutation compatibility, genetic risk (from validation + RBGIA support), genetic diversity at documented loci, and breeding constraints.',
            'thesis_role' => 'GICA = pair compatibility analysis; RBGIA = rule-based genetic inheritance analysis; together they form the AGAPORA genetic pair compatibility result.',
        ];
    }

    private function classify(int $score): string
    {
        return match (true) {
            $score >= 90 => 'Excellent',
            $score >= 75 => 'Good',
            $score >= 60 => 'Fair',
            $score >= 40 => 'Poor',
            default => 'Not Recommended',
        };
    }

    /**
     * @param  array<string, mixed>  $speciesCompatibility
     * @return array<string, mixed>
     */
    private function speciesFactor(array $speciesCompatibility): array
    {
        $status = $speciesCompatibility['status'] ?? SpeciesBreedingCompatibility::NOT_DOCUMENTED;
        [$points, $detail, $positives, $warnings] = match ($status) {
            SpeciesBreedingCompatibility::SAME_SPECIES => [30, 'Same-species pairing documented as fully compatible for prediction.', ['Compatible species (same species)'], []],
            SpeciesBreedingCompatibility::DOCUMENTED_HYBRID => [18, 'Documented hybrid pairing — prediction may be limited.', [], ['Documented hybrid pairing — not same-species']],
            SpeciesBreedingCompatibility::LIMITED_OR_UNCERTAIN => [12, 'Limited or uncertain species-pairing evidence.', [], ['Limited/uncertain species compatibility evidence']],
            SpeciesBreedingCompatibility::UNSUPPORTED => [0, 'Unsupported species pairing in AGAPORA breeding records.', [], ['Unsupported species pairing']],
            default => [8, 'Species pairing not documented in AGAPORA compatibility table.', [], ['Species pairing not documented']],
        };

        return [
            'key' => 'species_compatibility',
            'name' => 'Species Compatibility',
            'max' => 30,
            'points' => $points,
            'value' => $speciesCompatibility['label'] ?? $status,
            'detail' => $detail,
            'positives' => $positives,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<string, mixed>  $validation
     * @param  array<string, mixed>  $prediction
     * @return array<string, mixed>
     */
    private function inheritanceInformationFactor(array $validation, array $prediction, Bird $parentOne, Bird $parentTwo): array
    {
        $points = 0;
        $positives = [];
        $warnings = [];
        $max = 20;

        $hasBaseOne = (bool) $parentOne->base_color_id;
        $hasBaseTwo = (bool) $parentTwo->base_color_id;
        if ($hasBaseOne && $hasBaseTwo) {
            $points += 8;
            $positives[] = 'Both parents have stored base-color records';
        } elseif ($hasBaseOne || $hasBaseTwo) {
            $points += 3;
            $warnings[] = 'Only one parent has a stored base-color record';
        } else {
            $warnings[] = 'Missing parental base-color genotype information';
        }

        $calculated = collect($prediction['outcomes'] ?? [])
            ->filter(fn ($outcome) => is_array($outcome) && ($outcome['status'] ?? null) === 'calculated')
            ->count();
        $notCalculated = collect($prediction['outcomes'] ?? [])
            ->filter(fn ($outcome) => is_array($outcome) && in_array($outcome['status'] ?? null, ['not_calculated', 'blocked'], true))
            ->count();

        if ($calculated > 0) {
            $points += min(6, 2 + $calculated);
            $positives[] = 'Documented inheritance loci were calculable under RBGIA';
        } else {
            $warnings[] = 'No shared calculable parental genotypes for RBGIA loci';
        }

        $incomplete = collect($validation['warnings'] ?? [])
            ->filter(fn ($warning) => is_array($warning) && ($warning['code'] ?? '') === 'incomplete_genetics')
            ->count();
        if ($incomplete === 0 && $hasBaseOne && $hasBaseTwo) {
            $points += 4;
            $positives[] = 'No incomplete-genetics warnings for the pair';
        } elseif ($incomplete > 0) {
            $points += max(0, 4 - $incomplete);
            $warnings[] = 'Incomplete genetics notices were raised for one or both parents';
        }

        $confidence = $prediction['confidence'] ?? null;
        if ($confidence === 'CONFIRMED') {
            $points += 2;
            $positives[] = 'RBGIA confidence: confirmed from stored genotypes';
        } elseif ($confidence === 'PARTIALLY_DETERMINED') {
            $points += 1;
            $warnings[] = 'RBGIA confidence is only partially determined';
        } else {
            $warnings[] = 'RBGIA confidence is insufficient for a complete joint outcome';
        }

        if ($notCalculated > 0) {
            $warnings[] = $notCalculated.' locus outcome(s) were not calculated or blocked';
        }

        return [
            'key' => 'inheritance_information',
            'name' => 'Inheritance Information',
            'max' => $max,
            'points' => min($max, $points),
            'value' => $confidence ?: 'unknown',
            'detail' => 'Completeness of stored parental genotypes and calculable RBGIA loci.',
            'positives' => $positives,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $errors
     * @param  \Illuminate\Support\Collection<int, mixed>  $warnings
     * @param  array<string, mixed>  $prediction
     * @return array<string, mixed>
     */
    private function mutationCompatibilityFactor($errors, $warnings, array $prediction): array
    {
        $max = 15;
        $points = $max;
        $positives = [];
        $warn = [];

        $mutationErrors = $errors->filter(fn ($item) => is_array($item) && in_array($item['code'] ?? '', [
            'invalid_visual_mutation',
            'invalid_mutation_combination',
            'inapplicable_visual_mutation',
            'invalid_split_gene',
            'invalid_base_color',
        ], true));
        if ($mutationErrors->isNotEmpty()) {
            $points = 0;
            foreach ($mutationErrors as $item) {
                $warn[] = $item['message'] ?? 'Invalid mutation/genetic combination';
            }
        } else {
            $positives[] = 'No blocking mutation-combination errors detected';
        }

        $unverified = $warnings->filter(fn ($item) => is_array($item) && in_array($item['code'] ?? '', [
            'unverified_mutation',
            'unverified_gene',
        ], true));
        foreach ($unverified as $item) {
            $points = max(0, $points - 3);
            $warn[] = $item['message'] ?? 'Unverified genetic record';
        }

        $visualCalculated = collect($prediction['outcomes'] ?? [])
            ->filter(fn ($outcome) => is_array($outcome) && ($outcome['category'] ?? null) === 'visual_mutation' && ($outcome['status'] ?? null) === 'calculated')
            ->count();
        if ($visualCalculated > 0 && $mutationErrors->isEmpty()) {
            $positives[] = 'Visual mutation loci were resolved with documented inheritance types';
        }

        $level = match (true) {
            $points >= 12 => 'Compatible',
            $points >= 7 => 'Caution',
            default => 'Incompatible / Unverified',
        };

        return [
            'key' => 'mutation_compatibility',
            'name' => 'Mutation Compatibility',
            'max' => $max,
            'points' => $points,
            'value' => $level,
            'level' => $level,
            'detail' => 'Dataset mutation combination rules and verification status for parental visual/split records.',
            'positives' => $positives,
            'warnings' => $warn,
        ];
    }

    /**
     * @param  array<string, mixed>  $speciesCompatibility
     * @param  \Illuminate\Support\Collection<int, mixed>  $errors
     * @param  \Illuminate\Support\Collection<int, mixed>  $warnings
     * @param  \Illuminate\Support\Collection<int, mixed>  $information
     * @param  array<string, mixed>  $prediction
     * @return array<string, mixed>
     */
    private function geneticRiskFactor(
        array $speciesCompatibility,
        $errors,
        $warnings,
        $information,
        array $prediction,
    ): array {
        $max = 15;
        $points = $max;
        $positives = [];
        $warn = [];
        $affected = [];

        if (($speciesCompatibility['status'] ?? null) === SpeciesBreedingCompatibility::UNSUPPORTED) {
            $points = 0;
            $warn[] = 'Unsupported species pairing elevates breeding risk under AGAPORA records';
        }

        foreach ($information as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (($item['code'] ?? '') === 'shared_recessive') {
                $points = max(0, $points - 5);
                $warn[] = $item['message'] ?? 'Shared recessive allele noted';
                $affected[] = [
                    'type' => 'shared_recessive',
                    'message' => $item['message'] ?? null,
                ];
            }
        }

        foreach ($warnings as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (($item['code'] ?? '') === 'close_relationship') {
                $points = max(0, $points - 8);
                $warn[] = $item['message'] ?? 'Close relatedness warning';
            }
            if (($item['code'] ?? '') === 'age_below_minimum' || ($item['code'] ?? '') === 'age_outside_range') {
                $points = max(0, $points - 2);
                $warn[] = $item['message'] ?? 'Age outside documented breeding-safety range';
            }
        }

        // RBGIA support: homozygous recessive visual outcomes when both parents contribute mutant alleles.
        foreach ($prediction['outcomes'] ?? [] as $outcome) {
            if (! is_array($outcome) || ($outcome['status'] ?? null) !== 'calculated') {
                continue;
            }
            $type = strtolower((string) ($outcome['inheritance_type'] ?? ''));
            if (! str_contains($type, 'recessive') || str_contains($type, 'dominant')) {
                continue;
            }
            foreach ($outcome['results'] ?? [] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $expression = $row['expression'] ?? null;
                if (in_array($expression, ['visual', 'visual_homozygous', 'visual_hemizygous', 'visual_compound'], true)
                    && ((float) ($row['probability'] ?? 0)) > 0) {
                    $affected[] = [
                        'type' => 'recessive_visual_outcome',
                        'locus' => $outcome['name'] ?? null,
                        'inheritance_type' => $outcome['inheritance_type'] ?? null,
                        'parent_1' => $outcome['genetic_code_cock'] ?? null,
                        'parent_2' => $outcome['genetic_code_hen'] ?? null,
                        'possible_offspring' => $row['genotype'] ?? null,
                        'probability' => $row['probability'] ?? null,
                        'fraction' => $row['fraction'] ?? null,
                        'expression' => $expression,
                    ];
                }
            }
        }

        if ($affected !== [] && $points === $max) {
            $points = max(0, $points - 3);
            $warn[] = 'RBGIA identifies recessive visual genotype(s) that can appear from this parental cross';
        }

        if ($points >= 13 && $warn === []) {
            $positives[] = 'No documented high-risk genetic combination identified from available parental information';
        }

        $level = match (true) {
            $points >= 12 => 'LOW',
            $points >= 7 => 'MODERATE',
            default => 'HIGH',
        };

        $riskPayload = [
            'level' => $level,
            'points' => $points,
            'max_points' => $max,
            'summary' => match ($level) {
                'LOW' => 'No documented undesirable genetic combination was identified from the available parental information.',
                'MODERATE' => 'The parental combination may produce an offspring genotype associated with a documented genetic concern or shared recessive pathway.',
                default => 'Elevated genetic-risk signals were identified from species status, validation findings, or RBGIA recessive outcomes.',
            },
            'affected' => $affected,
            'species_risk_level' => $speciesCompatibility['risk_level'] ?? null,
        ];

        return [
            'key' => 'genetic_risk',
            'name' => 'Genetic Risk',
            'max' => $max,
            'points' => $points,
            'value' => $level,
            'detail' => $riskPayload['summary'],
            'positives' => $positives,
            'warnings' => $warn,
            'risk_payload' => $riskPayload,
        ];
    }

    /**
     * @param  array<string, mixed>  $prediction
     * @return array<string, mixed>
     */
    private function geneticDiversityFactor(Bird $parentOne, Bird $parentTwo, array $prediction): array
    {
        $max = 10;
        $points = 0;
        $positives = [];
        $warnings = [];
        $lociNotes = [];

        $codeOne = $this->resolvedParentGenotype($parentOne, $prediction) ?? $parentOne->baseColor?->genetic_code;
        $codeTwo = $this->resolvedParentGenotype($parentTwo, $prediction) ?? $parentTwo->baseColor?->genetic_code;
        if ($codeOne && $codeTwo) {
            if (strcasecmp(trim($codeOne), trim($codeTwo)) !== 0) {
                $points += 4;
                $positives[] = 'Parents contribute different base-color genotypes';
                $lociNotes[] = [
                    'locus' => 'base_color',
                    'parent_1' => $codeOne,
                    'parent_2' => $codeTwo,
                    'relationship' => 'different',
                ];
            } else {
                $points += 1;
                $warnings[] = 'Parents share the same stored base-color genotype';
                $lociNotes[] = [
                    'locus' => 'base_color',
                    'parent_1' => $codeOne,
                    'parent_2' => $codeTwo,
                    'relationship' => 'identical',
                ];
            }
        } else {
            $warnings[] = 'Base-color genotype comparison unavailable for diversity scoring';
        }

        $mutationsOne = $parentOne->visualMutations?->pluck('id')->sort()->values()->all() ?? [];
        $mutationsTwo = $parentTwo->visualMutations?->pluck('id')->sort()->values()->all() ?? [];
        if ($mutationsOne !== [] || $mutationsTwo !== []) {
            $shared = array_values(array_intersect($mutationsOne, $mutationsTwo));
            $union = array_values(array_unique(array_merge($mutationsOne, $mutationsTwo)));
            if ($union !== [] && count($shared) < count($union)) {
                $points += 3;
                $positives[] = 'Parents contribute different visual-mutation selections at documented loci';
            } elseif ($shared !== [] && count($shared) === count($union)) {
                $points += 1;
                $warnings[] = 'Parents share the same visual-mutation selection set';
            }
        }

        $splitsOne = $parentOne->splitGenes?->pluck('id')->sort()->values()->all() ?? [];
        $splitsTwo = $parentTwo->splitGenes?->pluck('id')->sort()->values()->all() ?? [];
        if ($splitsOne !== [] || $splitsTwo !== []) {
            $sharedSplits = array_values(array_intersect($splitsOne, $splitsTwo));
            $unionSplits = array_values(array_unique(array_merge($splitsOne, $splitsTwo)));
            if ($unionSplits !== [] && count($sharedSplits) < count($unionSplits)) {
                $points += 2;
                $positives[] = 'Parents differ in documented split/hidden gene selections';
            } elseif ($sharedSplits !== []) {
                $points += 1;
            }
        }

        foreach ($prediction['outcomes'] ?? [] as $outcome) {
            if (! is_array($outcome) || ($outcome['status'] ?? null) !== 'calculated') {
                continue;
            }
            $left = $outcome['genetic_code_cock'] ?? null;
            $right = $outcome['genetic_code_hen'] ?? null;
            if ($left && $right && strcasecmp((string) $left, (string) $right) !== 0) {
                $lociNotes[] = [
                    'locus' => $outcome['name'] ?? ($outcome['locus_key'] ?? 'locus'),
                    'parent_1' => $left,
                    'parent_2' => $right,
                    'relationship' => 'different',
                ];
            }
        }

        if ($points >= 7) {
            $level = 'HIGH';
            $summary = 'Parents contribute differing alleles or mutation selections at multiple documented loci.';
        } elseif ($points >= 4) {
            $level = 'MODERATE';
            $summary = 'The parents share some documented genetic characteristics while contributing different alleles at other relevant loci.';
        } else {
            $level = 'LOW';
            $summary = 'Limited allelic difference was detected from available parental genotypes and mutation selections. Color display names alone were not treated as diversity.';
        }

        $points = min($max, $points + (($parentOne->species_id && $parentTwo->species_id && (int) $parentOne->species_id === (int) $parentTwo->species_id) ? 1 : 0));

        return [
            'key' => 'genetic_diversity',
            'name' => 'Genetic Diversity',
            'max' => $max,
            'points' => min($max, $points),
            'value' => $level,
            'detail' => $summary,
            'positives' => $positives,
            'warnings' => $warnings,
            'diversity_payload' => [
                'level' => $level,
                'summary' => $summary,
                'loci' => $lociNotes,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $speciesCompatibility
     * @param  array<string, mixed>  $validation
     * @param  \Illuminate\Support\Collection<int, mixed>  $warnings
     * @return array<string, mixed>
     */
    private function breedingConstraintFactor(array $speciesCompatibility, array $validation, $warnings): array
    {
        $max = 10;
        $points = $max;
        $positives = [];
        $warn = [];

        if (! ($validation['can_predict'] ?? false)) {
            $points = 0;
            $warn[] = 'Prediction is not allowed for this pairing under current validation rules';
        } else {
            $positives[] = 'Breeding-pair validation allows genetic prediction';
        }

        foreach ($warnings as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (in_array($item['code'] ?? '', ['age_below_minimum', 'age_outside_range'], true)) {
                $points = max(0, $points - 3);
                $warn[] = $item['message'] ?? 'Age outside documented breeding-safety guidance';
            }
        }

        if (($speciesCompatibility['fertility_status'] ?? null) && stripos((string) $speciesCompatibility['fertility_status'], 'infertile') !== false) {
            $points = max(0, $points - 5);
            $warn[] = 'Documented fertility status indicates infertility concern';
        }

        if ($points >= 8 && $warn === []) {
            $positives[] = 'No blocking breeding-constraint findings beyond standard documentation';
        }

        return [
            'key' => 'breeding_constraints',
            'name' => 'Breeding Constraints',
            'max' => $max,
            'points' => $points,
            'value' => ($validation['can_predict'] ?? false) ? 'Prediction allowed' : 'Prediction blocked',
            'detail' => 'Pair validity, age/safety notices, and fertility documentation from AGAPORA records.',
            'positives' => $positives,
            'warnings' => $warn,
        ];
    }

    /**
     * Genotype actually crossed: hidden splits replace a wild-type base color at that locus.
     *
     * @param  array<string, mixed>  $prediction
     */
    private function resolvedParentGenotype(Bird $bird, array $prediction): ?string
    {
        $side = $bird->sex === Bird::SEX_COCK ? 'genetic_code_cock' : 'genetic_code_hen';
        $parts = [];
        foreach (['ground_color', 'dark_factor'] as $key) {
            foreach ($prediction['outcomes'] ?? [] as $outcome) {
                if (! is_array($outcome) || ($outcome['locus_key'] ?? null) !== $key || ($outcome['status'] ?? null) !== 'calculated') {
                    continue;
                }
                if (! empty($outcome[$side])) {
                    $parts[] = $outcome[$side];
                }
            }
        }

        return $parts === [] ? null : implode('|', $parts);
    }
}

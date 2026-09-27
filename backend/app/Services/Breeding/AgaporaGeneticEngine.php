<?php

namespace App\Services\Breeding;

use App\Models\Bird;
use App\Models\SpeciesBreedingCompatibility;

/**
 * AGAPORA thesis genetic computation orchestrator.
 *
 * Unique to REVISE SYSTEM / AGAPORA:
 * - Dataset-driven RBGIA loci (stored genetic_code only; never invent wild type)
 * - Species compatibility from lovebird_species + species_breeding_compatibilities
 * - GICA compatibility index separate from inheritance math
 * - Egg/chick examples = illustrative genetic outcomes, not a biological clutch lottery
 *
 * This is not a port of any third-party mobile genetics engine.
 */
class AgaporaGeneticEngine
{
    public const METHOD = 'AGAPORA-RBGIA-GICA-v3';

    public function __construct(
        private readonly GicaAnalyzer $gicaAnalyzer,
    ) {}

    /**
     * Assemble the Computation & Result payload from already-run RBGIA + validation.
     *
     * @param  array<string, mixed>  $validation
     * @param  array<string, mixed>  $prediction
     * @param  list<array<string, mixed>>  $eggOutcomes
     * @param  array<string, mixed>|null  $clutchSimulation  Layer-2 GICA clutch simulation (never alters RBGIA math)
     * @return array<string, mixed>
     */
    public function compose(Bird $parentOne, Bird $parentTwo, array $validation, array $prediction, array $eggOutcomes, ?array $clutchSimulation = null): array
    {
        $compatibility = $validation['compatibility'] ?? [];
        $speciesCompatibility = $this->speciesCompatibility($parentOne, $parentTwo, $compatibility, $validation);
        $gica = $this->gicaAnalyzer->analyze($speciesCompatibility, $validation, $prediction, $parentOne, $parentTwo);
        $probabilities = $this->probabilities($prediction);
        $forecast = $this->reproductiveForecast($parentOne, $parentTwo, $speciesCompatibility, $gica, $eggOutcomes, $validation, $clutchSimulation);
        $examples = $this->eggChickExamples($eggOutcomes);
        $inherited = $this->inheritedTraits($validation, $prediction, $examples);
        $verification = $this->verification($validation, $compatibility, $prediction, $probabilities, $gica, $clutchSimulation);
        $geneticAnalysis = $this->geneticAnalysis($prediction, $probabilities);
        $punnett = $prediction['punnett_squares'] ?? $this->punnettDetail($prediction);
        $algorithm = $this->algorithmMeta($prediction, $examples);

        $pairCompatibility = [
            'headline' => 'Genetic Pair Compatibility',
            'score' => $gica['score'] ?? null,
            'label' => $gica['label'] ?? null,
            'summary' => $gica['summary'] ?? null,
            'recommendation' => $gica['recommendation'] ?? null,
            'why' => $gica['why'] ?? ['positives' => [], 'warnings' => []],
            'breakdown' => $gica['breakdown'] ?? [],
            'risk' => $gica['risk'] ?? null,
            'diversity' => $gica['diversity'] ?? null,
            'mutation_compatibility' => $gica['mutation_compatibility'] ?? null,
            'parent_1' => $speciesCompatibility['parent_1'] ?? null,
            'parent_2' => $speciesCompatibility['parent_2'] ?? null,
            'rbgia_support' => [
                'message' => 'RBGIA calculated theoretical inheritance outcomes from stored parental genotypes. Those outcomes support the GICA compatibility analysis; they do not replace it.',
                'confidence' => $prediction['confidence'] ?? null,
                'outcome_count' => count($probabilities['complete_offspring'] ?? $examples),
                'sex' => $probabilities['sex'] ?? [],
                'base_color' => $probabilities['base_color'] ?? [],
                'visual_mutations' => $probabilities['visual_mutations'] ?? [],
                'split_hidden_genes' => $probabilities['split_hidden_genes'] ?? [],
            ],
            'final_analysis' => [
                'compatibility' => trim(($gica['score'] ?? '—').' / 100 · '.($gica['label'] ?? '—')),
                'genetic_risk' => $gica['risk']['level'] ?? '—',
                'genetic_diversity' => $gica['diversity']['level'] ?? '—',
                'mutation_compatibility' => $gica['mutation_compatibility']['level'] ?? '—',
                'inheritance_summary' => $prediction['message'] ?? null,
                'recommendation' => $gica['recommendation'] ?? null,
            ],
            'thesis_statement' => 'Analysis of lovebird pair compatibility using rule-based genetic inheritance (GICA + RBGIA).',
        ];

        return [
            'species_compatibility' => $speciesCompatibility,
            'gica' => $gica,
            'pair_compatibility' => $pairCompatibility,
            'probabilities' => $probabilities,
            'data_confidence' => [
                'level' => $prediction['confidence'] ?? 'INSUFFICIENT_DATA',
                'notes' => $prediction['confidence_notes'] ?? [],
                'message' => match ($prediction['confidence'] ?? null) {
                    'CONFIRMED' => 'Confirmed genetic result based on stored genotype/allele data for the calculated loci.',
                    'PARTIALLY_DETERMINED' => 'Partially determined — some parental loci were unknown, blocked, or provisional.',
                    default => 'Insufficient data — available records are not enough for a complete reliable joint outcome.',
                },
            ],
            'theoretical_distribution' => $prediction['theoretical_distribution'] ?? [],
            'parent_profiles' => $prediction['parent_profiles'] ?? [],
            'reproductive_forecast' => $forecast,
            'clutch_simulation' => $clutchSimulation,
            'egg_chick_examples' => $examples,
            'example_outcomes_note' => $clutchSimulation
                ? 'Egg/chick cards are a seeded compatibility-based clutch simulation. Living chicks sample the unchanged RBGIA distribution; they are not a guaranteed clutch sequence.'
                : 'Egg/chick cards are example representations derived from the theoretical distribution. They are not a guaranteed clutch sequence.',
            'inherited_traits' => $inherited['traits'],
            'report' => $inherited['report'],
            'verification' => $verification,
            'genetic_analysis' => $geneticAnalysis,
            'punnett_square' => $punnett,
            'algorithm' => $algorithm,
        ];
    }

    /**
     * @param  array<string, mixed>  $compatibility
     * @param  array<string, mixed>  $validation
     * @return array<string, mixed>
     */
    private function speciesCompatibility(Bird $parentOne, Bird $parentTwo, array $compatibility, array $validation): array
    {
        $status = $compatibility['compatibility_status'] ?? SpeciesBreedingCompatibility::NOT_DOCUMENTED;
        $same = $status === SpeciesBreedingCompatibility::SAME_SPECIES
            || ((int) $parentOne->species_id === (int) $parentTwo->species_id && $parentOne->species_id);
        $hybrid = ! $same && $parentOne->species_id && $parentTwo->species_id;
        $unsupported = $status === SpeciesBreedingCompatibility::UNSUPPORTED;
        $compatible = ! $unsupported
            && ($validation['errors'] ?? []) === []
            && ($compatibility['prediction_allowed'] ?? $same);

        $speciesOne = $parentOne->species;
        $speciesTwo = $parentTwo->species;

        $score = match ($status) {
            SpeciesBreedingCompatibility::SAME_SPECIES => 1.0,
            SpeciesBreedingCompatibility::DOCUMENTED_HYBRID => 0.72,
            SpeciesBreedingCompatibility::LIMITED_OR_UNCERTAIN => 0.48,
            SpeciesBreedingCompatibility::UNSUPPORTED => 0.12,
            default => 0.28,
        };

        if (($validation['errors'] ?? []) !== []) {
            $score = min($score, 0.2);
            $compatible = false;
        }

        return [
            'compatible' => (bool) $compatible,
            'same_species' => (bool) $same,
            'hybrid' => (bool) $hybrid,
            'warning' => $compatibility['warning_message']
                ?? (($validation['warnings'][0]['message'] ?? null)),
            'warning_message' => $compatibility['warning_message']
                ?? (($validation['warnings'][0]['message'] ?? null)),
            'score' => round($score, 3),
            'status' => $status,
            'label' => $compatibility['label'] ?? null,
            'breeding_type' => $compatibility['breeding_type'] ?? null,
            'fertility_status' => $compatibility['fertility_status'] ?? null,
            'risk_level' => $compatibility['risk_level'] ?? null,
            'prediction_allowed' => (bool) ($compatibility['prediction_allowed'] ?? false),
            'can_predict' => (bool) ($validation['can_predict'] ?? false),
            'parent_1' => [
                'bird_id' => $parentOne->bird_id,
                'species' => $speciesOne?->common_name,
                'scientific_name' => $speciesOne?->scientific_name,
                'species_group' => $speciesOne?->species_group,
                'has_eye_ring' => $speciesOne?->has_eye_ring,
                'sex' => $parentOne->sex === Bird::SEX_HEN ? 'Hen — Female' : ($parentOne->sex === Bird::SEX_COCK ? 'Cock — Male' : $parentOne->sex),
            ],
            'parent_2' => [
                'bird_id' => $parentTwo->bird_id,
                'species' => $speciesTwo?->common_name,
                'scientific_name' => $speciesTwo?->scientific_name,
                'species_group' => $speciesTwo?->species_group,
                'has_eye_ring' => $speciesTwo?->has_eye_ring,
                'sex' => $parentTwo->sex === Bird::SEX_HEN ? 'Hen — Female' : ($parentTwo->sex === Bird::SEX_COCK ? 'Cock — Male' : $parentTwo->sex),
            ],
            'scientific_basis' => $compatibility['scientific_basis'] ?? null,
            'scientific_source' => $compatibility['scientific_source'] ?? null,
            'verification_status' => $compatibility['verification_status'] ?? null,
            'errors' => $validation['errors'] ?? [],
            'warnings' => $validation['warnings'] ?? [],
            'methodology' => 'Species pair key from lovebird_species IDs; SAME_SPECIES when IDs match; otherwise stored species_breeding_compatibilities row. Eye-ring group is read from species records, not keyword guessing.',
        ];
    }

    /**
     * @param  array<string, mixed>  $prediction
     * @return array<string, mixed>
     */
    private function probabilities(array $prediction): array
    {
        $theoretical = $prediction['theoretical_distribution'] ?? [];
        if (is_array($theoretical) && $theoretical !== []) {
            return $this->probabilitiesFromTheoretical($prediction, $theoretical);
        }

        $sex = [];
        $baseColor = [];
        $visual = [];
        $split = [];
        $genotype = [];
        $phenotype = [];

        foreach ($prediction['outcomes'] ?? [] as $outcome) {
            if (! is_array($outcome) || ($outcome['status'] ?? null) !== 'calculated') {
                continue;
            }

            foreach ($outcome['results'] ?? [] as $row) {
                if (! is_array($row) || empty($row['genotype'])) {
                    continue;
                }

                $entry = [
                    'trait' => $outcome['name'] ?? null,
                    'category' => $outcome['category'] ?? null,
                    'inheritance_type' => $outcome['inheritance_type'] ?? null,
                    'sex' => $row['sex'] ?? null,
                    'genotype' => $row['genotype'],
                    'fraction' => $row['fraction'] ?? null,
                    'probability' => $row['probability'] ?? null,
                    'status' => $outcome['status'] ?? null,
                    'expression' => $row['expression'] ?? null,
                    'phenotype' => $row['phenotype'] ?? null,
                ];

                $sexKey = strtolower((string) ($row['sex'] ?? 'both'));
                if (in_array($sexKey, ['cock', 'male', 'hen', 'female'], true)) {
                    $normalizedSex = in_array($sexKey, ['cock', 'male'], true) ? 'cock' : 'hen';
                    if (! isset($sex[$normalizedSex])) {
                        $sex[$normalizedSex] = [
                            'sex' => $normalizedSex,
                            'trait' => $normalizedSex === 'cock' ? 'Male / Cock' : 'Female / Hen',
                            'fraction' => $row['fraction'] ?? null,
                            'probability' => 0.0,
                        ];
                    }
                    $sex[$normalizedSex]['probability'] += (float) ($row['probability'] ?? 0);
                }

                $genotype[] = $entry;
                $phenotype[] = [
                    ...$entry,
                    'note' => empty($row['phenotype'])
                        ? 'Not specified in stored phenotype record.'
                        : null,
                ];

                match ($outcome['category'] ?? null) {
                    'base_color' => $baseColor[] = $entry,
                    'visual_mutation' => $visual[] = $entry,
                    'split_gene' => $split[] = $entry,
                    default => null,
                };
            }
        }

        return [
            'sex' => array_values($sex),
            'base_color' => $baseColor,
            'base_colors' => $baseColor,
            'visual_mutations' => $visual,
            'mutations' => $visual,
            'split_hidden_genes' => $split,
            'split_genes' => $split,
            'genotype' => $genotype,
            'phenotype' => $phenotype,
            'message' => $prediction['message'] ?? null,
            'confidence' => $prediction['confidence'] ?? null,
        ];
    }

    /**
     * Marginal distributions derived only from the joint theoretical offspring set.
     *
     * @param  array<string, mixed>  $prediction
     * @param  list<array<string, mixed>>  $theoretical
     * @return array<string, mixed>
     */
    private function probabilitiesFromTheoretical(array $prediction, array $theoretical): array
    {
        $sex = [];
        $baseColor = [];
        $visual = [];
        $split = [];
        $genotype = [];
        $phenotype = [];
        $complete = [];
        $hasVisualLocus = collect($prediction['outcomes'] ?? [])->contains(
            fn ($o) => is_array($o) && ($o['category'] ?? null) === 'visual_mutation' && ($o['status'] ?? null) === 'calculated'
        );
        $hasSplitLocus = collect($prediction['outcomes'] ?? [])->contains(
            fn ($o) => is_array($o) && ($o['category'] ?? null) === 'split_gene' && ($o['status'] ?? null) === 'calculated'
        );

        foreach ($theoretical as $row) {
            if (! is_array($row)) {
                continue;
            }
            $probability = (float) ($row['probability'] ?? 0);
            $fraction = $row['fraction'] ?? null;

            $sexKey = strtolower((string) ($row['sex'] ?? 'both'));
            if (in_array($sexKey, ['cock', 'male', 'hen', 'female'], true)) {
                $normalizedSex = in_array($sexKey, ['cock', 'male'], true) ? 'cock' : 'hen';
                if (! isset($sex[$normalizedSex])) {
                    $sex[$normalizedSex] = [
                        'sex' => $normalizedSex,
                        'trait' => $normalizedSex === 'cock' ? 'Male / Cock' : 'Female / Hen',
                        'probability' => 0.0,
                        'fraction' => null,
                    ];
                }
                $sex[$normalizedSex]['probability'] += $probability;
            }

            if (! empty($row['base_color'])) {
                $key = (string) $row['base_color'];
                if (! isset($baseColor[$key])) {
                    $baseColor[$key] = [
                        'trait' => $key,
                        'category' => 'base_color',
                        'genotype' => $row['base_color_genotype'] ?? null,
                        'probability' => 0.0,
                        'fraction' => null,
                    ];
                }
                $baseColor[$key]['probability'] += $probability;
                if (empty($baseColor[$key]['genotype']) && ! empty($row['base_color_genotype'])) {
                    $baseColor[$key]['genotype'] = $row['base_color_genotype'];
                }
            }

            $visuals = array_values(array_filter($row['visual_mutations'] ?? []));
            sort($visuals);
            $visualKey = $visuals === [] ? 'No visual mutation' : implode(' + ', $visuals);
            if (! isset($visual[$visualKey])) {
                $visual[$visualKey] = [
                    'trait' => $visualKey,
                    'category' => 'visual_mutation',
                    'expression' => $visuals === [] ? 'non_carrier' : 'visual',
                    'probability' => 0.0,
                    'fraction' => null,
                ];
            }
            $visual[$visualKey]['probability'] += $probability;

            $splits = array_values(array_filter($row['split_hidden_genes'] ?? []));
            $splitLabels = array_map(function ($gene) {
                $name = (string) $gene;
                return str_starts_with(strtolower($name), 'split ') ? $name : 'Split '.$name;
            }, $splits);
            sort($splitLabels);
            $splitKey = $splitLabels === [] ? 'Non-carrier' : implode(' + ', $splitLabels);
            if (! isset($split[$splitKey])) {
                $split[$splitKey] = [
                    'trait' => $splitKey,
                    'category' => 'split_gene',
                    'expression' => $splitLabels === [] ? 'non_carrier' : 'carrier_split',
                    'probability' => 0.0,
                    'fraction' => null,
                ];
            }
            $split[$splitKey]['probability'] += $probability;

            $genotype[] = [
                'trait' => 'Joint genotype',
                'category' => 'joint_offspring',
                'sex' => $row['sex'] ?? null,
                'genotype' => $row['genotype'] ?? null,
                'fraction' => $fraction,
                'probability' => $probability,
            ];
            $phenotype[] = [
                'trait' => 'Joint phenotype',
                'category' => 'joint_offspring',
                'sex' => $row['sex'] ?? null,
                'genotype' => $row['genotype'] ?? null,
                'phenotype' => $row['phenotype'] ?? null,
                'fraction' => $fraction,
                'probability' => $probability,
            ];
            $complete[] = [
                'sex' => $row['sex'] ?? null,
                'sex_label' => $row['sex_label'] ?? null,
                'base_color' => $row['base_color'] ?? null,
                'base_color_genotype' => $row['base_color_genotype'] ?? null,
                'visual_mutations' => $visuals,
                'split_hidden_genes' => $splits,
                'genotype' => $row['genotype'] ?? null,
                'phenotype' => $row['phenotype'] ?? null,
                'dark_factor' => $row['dark_factor'] ?? null,
                'fraction' => $fraction,
                'probability' => $probability,
                'inheritance_classes' => $this->inheritanceClassesFromLoci($row['loci'] ?? [], $splits),
                'loci' => $row['loci'] ?? [],
                'inheritance_paths' => $row['inheritance_paths'] ?? [],
                'passed_from_parents' => $row['passed_from_parents'] ?? [],
            ];
        }

        if ($visual === [] && $theoretical !== []) {
            $visual['__none__'] = [
                'trait' => 'No visual mutation',
                'category' => 'visual_mutation',
                'expression' => 'non_carrier',
                'probability' => 1.0,
                'fraction' => '1/1',
                'note' => $hasVisualLocus
                    ? null
                    : 'Neither parent carried a documented visual mutation locus for this pair.',
            ];
        }

        if ($split === [] && $theoretical !== []) {
            $split['__none__'] = [
                'trait' => 'Non-carrier',
                'category' => 'split_gene',
                'expression' => 'non_carrier',
                'probability' => 1.0,
                'fraction' => '1/1',
                'note' => $hasSplitLocus
                    ? null
                    : 'Neither parent carried a documented split/hidden gene locus for this pair.',
            ];
        }

        $sex = $this->finalizeMarginal($sex, 'sex');
        $baseColor = $this->finalizeMarginal($baseColor, 'trait');
        $visual = $this->finalizeMarginal($visual, 'trait');
        $split = $this->finalizeMarginal($split, 'trait');

        $unavailable = [];
        foreach ($prediction['outcomes'] ?? [] as $outcome) {
            if (! is_array($outcome)) {
                continue;
            }
            if (! in_array($outcome['status'] ?? null, ['not_calculated', 'blocked'], true)) {
                continue;
            }
            $unavailable[] = [
                'category' => $outcome['category'] ?? null,
                'name' => $outcome['name'] ?? null,
                'reason' => $outcome['reason'] ?? 'Calculation unavailable.',
            ];
        }

        return [
            'sex' => array_values($sex),
            'base_color' => array_values($baseColor),
            'base_colors' => array_values($baseColor),
            'visual_mutations' => array_values($visual),
            'mutations' => array_values($visual),
            'split_hidden_genes' => array_values($split),
            'split_genes' => array_values($split),
            'genotype' => $genotype,
            'phenotype' => $phenotype,
            'complete_offspring' => $complete,
            'unavailable' => $unavailable,
            'message' => $prediction['message'] ?? null,
            'confidence' => $prediction['confidence'] ?? null,
            'source' => 'theoretical_joint_distribution',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $loci
     * @param  list<string>  $splits
     * @return array{dominant: bool, sex_linked: bool, recessive: bool}
     */
    private function inheritanceClassesFromLoci(array $loci, array $splits): array
    {
        $classes = [
            'dominant' => false,
            'sex_linked' => false,
            'recessive' => false,
        ];

        foreach ($loci as $locus) {
            if (! is_array($locus)) {
                continue;
            }
            $type = strtolower((string) ($locus['inheritance_type'] ?? ''));
            if ($type === '') {
                continue;
            }
            if (str_contains($type, 'sex-linked')) {
                $classes['sex_linked'] = true;
            }
            if (str_contains($type, 'dominant') && ! str_contains($type, 'recessive')) {
                $classes['dominant'] = true;
            }
            if (str_contains($type, 'incomplete') || str_contains($type, 'intermediate') || str_contains($type, 'partial')) {
                $classes['dominant'] = true;
            }
            if (str_contains($type, 'recessive')) {
                $classes['recessive'] = true;
            }
        }

        if ($splits !== []) {
            $classes['recessive'] = true;
        }

        return $classes;
    }

    /**
     * @param  array<string, array<string, mixed>>  $rows
     * @return array<string, array<string, mixed>>
     */
    private function finalizeMarginal(array $rows, string $sortKey): array
    {
        foreach ($rows as &$row) {
            $probability = (float) ($row['probability'] ?? 0);
            if (empty($row['fraction'])) {
                $row['fraction'] = $this->approxFraction($probability);
            }
            $row['probability'] = $probability;
        }
        unset($row);

        uasort($rows, fn ($a, $b) => ((float) ($b['probability'] ?? 0)) <=> ((float) ($a['probability'] ?? 0)));

        return $rows;
    }

    private function approxFraction(float $probability): string
    {
        $bestNum = 1;
        $bestDen = 1;
        $bestError = PHP_FLOAT_MAX;
        for ($den = 1; $den <= 32; $den++) {
            $num = (int) round($probability * $den);
            if ($num < 0) {
                continue;
            }
            $error = abs(($num / $den) - $probability);
            if ($error < $bestError) {
                $bestError = $error;
                $bestNum = $num;
                $bestDen = $den;
            }
        }
        $gcd = $this->gcdFraction(max($bestNum, 1), $bestDen);

        return ($bestNum / $gcd).'/'.($bestDen / $gcd);
    }

    private function gcdFraction(int $left, int $right): int
    {
        return $right === 0 ? max($left, 1) : $this->gcdFraction($right, $left % $right);
    }

    /**
     * Reproductive forecast is separate from inheritance.
     * Biological clutch/hatch numbers are not invented when absent from breeding-safety data.
     * eggs_forecast = count of illustrative genetic outcome examples derived from RBGIA.
     *
     * @param  array<string, mixed>  $speciesCompatibility
     * @param  array<string, mixed>  $gica
     * @param  list<array<string, mixed>>  $eggOutcomes
     * @param  array<string, mixed>  $validation
     * @param  array<string, mixed>|null  $clutchSimulation
     * @return array<string, mixed>
     */
    private function reproductiveForecast(
        Bird $parentOne,
        Bird $parentTwo,
        array $speciesCompatibility,
        array $gica,
        array $eggOutcomes,
        array $validation,
        ?array $clutchSimulation = null,
    ): array {
        $clutch = $clutchSimulation;
        $exampleCount = count($eggOutcomes);
        $factors = [
            $clutch
                ? 'Simulated clutch cards: '.$exampleCount.' (compatibility-based clutch simulation, seed '.($clutch['seed'] ?? '—').').'
                : 'Illustrative genetic outcome examples from RBGIA: '.$exampleCount.'.',
            'GICA label: '.($gica['label'] ?? '—').' (score '.($gica['score'] ?? '—').').',
            'Species compatibility score: '.($speciesCompatibility['score'] ?? '—').'.',
        ];
        if ($clutch) {
            $factors[] = 'Compatibility level used for clutch simulation: '.($clutch['compatibility_level']['label'] ?? '—')
                .' (tendency '.($clutch['compatibility_level']['tendency'] ?? '—').'; '.($clutch['compatibility_level']['viability_tendency'] ?? '—').')'
                .' → simulated clutch '.($clutch['clutch_size'] ?? '—').' eggs, '.($clutch['counts']['living_chicks'] ?? '—').' living chick(s).';
        }

        if (! empty($speciesCompatibility['fertility_status'])) {
            $factors[] = 'Stored fertility status: '.$speciesCompatibility['fertility_status'];
        }
        if (! empty($speciesCompatibility['risk_level'])) {
            $factors[] = 'Stored risk level: '.$speciesCompatibility['risk_level'];
        }
        foreach (array_merge($validation['warnings'] ?? [], $validation['information'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (in_array($item['code'] ?? '', ['breeding_safety', 'age_not_documented', 'age_below_minimum', 'age_outside_range', 'incomplete_pedigree'], true)) {
                $factors[] = $item['message'];
            }
        }

        $speciesUsed = ($speciesCompatibility['same_species'] ?? false)
            ? ($parentOne->species?->scientific_name ?: $parentOne->species?->common_name)
            : trim(($parentOne->species?->scientific_name ?: '?').' × '.($parentTwo->species?->scientific_name ?: '?'));

        $counts = $clutch['counts'] ?? null;
        $hatchRate = $counts && ($counts['eggs_laid'] ?? 0) > 0
            ? round(($counts['hatched'] ?? 0) / $counts['eggs_laid'], 4)
            : null;

        return [
            'species_used' => $speciesUsed,
            'eggs_laid_min' => $clutch ? ClutchSimulationService::MIN_EGGS : null,
            'eggs_laid_mean' => null,
            'eggs_laid_max' => $clutch ? ClutchSimulationService::MAX_EGGS : null,
            'eggs_forecast' => $clutch ? ($clutch['clutch_size'] ?? $exampleCount) : $exampleCount,
            'hatch_rate' => $hatchRate,
            'hatch_rate_percent' => $hatchRate === null ? null : round($hatchRate * 100, 1),
            'expected_hatchlings' => $counts['living_chicks'] ?? null,
            'expected_hatch_count' => $counts['hatched'] ?? null,
            'clutch_factor' => $clutch['compatibility_level']['label'] ?? null,
            'clutch_simulation_summary' => $clutch ? [
                'gica_score' => $clutch['gica_score'] ?? null,
                'compatibility_level' => $clutch['compatibility_level']['label'] ?? null,
                'simulated_clutch' => $counts['eggs_laid'] ?? null,
                'living_chicks' => $counts['living_chicks'] ?? null,
                'unfertilized' => $counts['unfertilized'] ?? null,
                'failed_to_develop' => $counts['failed_to_develop'] ?? null,
                'failed_to_hatch' => $counts['failed_to_hatch'] ?? null,
            ] : null,
            'adjustment_notes' => $factors,
            'forecast_factors' => $factors,
            'hybrid_warning' => ($speciesCompatibility['hybrid'] ?? false) ? ($speciesCompatibility['warning'] ?? null) : null,
            'estimated_eggs' => $clutch
                ? 'Simulated clutch: '.($clutch['clutch_size'] ?? '—').' eggs (allowed range '.ClutchSimulationService::MIN_EGGS.'–'.ClutchSimulationService::MAX_EGGS.'; compatibility level '.($clutch['compatibility_level']['label'] ?? '—').').'
                : 'Not documented in AGAPORA breeding-safety records. Biological clutch size is not invented.',
            'estimated_hatchlings' => $clutch
                ? 'Simulated living chicks: '.($counts['living_chicks'] ?? '—').' of '.($counts['eggs_laid'] ?? '—').' eggs.'
                : 'Not documented in AGAPORA breeding-safety records. Hatchling count is not invented.',
            'expected_hatch_range' => 'Not documented in AGAPORA breeding-safety records. Hatch timing is not invented.',
            'disclaimer' => $clutch
                ? ClutchSimulationService::DISCLAIMER
                : 'Real-world clutch and hatch outcomes depend on health, husbandry, environment, fertility, and other biological factors. eggs_forecast here is the count of deterministic genetic example cards, not a promised nest size.',
            'note' => $clutch
                ? 'Reproductive Forecast is a compatibility-based simulation layer. It remains separate from RBGIA inheritance math, which it never modifies.'
                : 'Reproductive Forecast remains separate from RBGIA inheritance math.',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $eggOutcomes
     * @param  array<string, mixed>  $probabilities
     * @return list<array<string, mixed>>
     */
    private function eggChickExamples(array $eggOutcomes): array
    {
        return array_values(array_map(function (array $egg, int $index) {
            $number = $egg['egg_number'] ?? ($index + 1);
            $genotypeString = (string) ($egg['genotype'] ?? '');
            $alleles = $egg['alleles'] ?? (array_values(array_filter(array_map('trim', explode('/', $genotypeString)))));

            return [
                'egg_chick_outcome' => 'Egg/Chick Outcome #'.$number,
                'egg_number' => $number,
                'chick_number' => $number,
                'egg_outcome_id' => $egg['egg_outcome_id'] ?? ('egg-'.$number),
                'outcome_key' => $egg['outcome_key'] ?? null,
                'rbgia_outcome_key' => $egg['rbgia_outcome_key'] ?? ($egg['outcome_key'] ?? null),
                'rbgia_outcome_index' => $egg['rbgia_outcome_index'] ?? null,
                'status' => isset($egg['egg_status']) ? 'simulated_clutch_egg' : 'illustrative_genetic_outcome',
                'egg_status' => $egg['egg_status'] ?? null,
                'egg_status_label' => $egg['egg_status_label'] ?? null,
                'source' => $egg['source'] ?? null,
                'clutch_simulation' => $egg['clutch_simulation'] ?? null,
                'hatch_forecast_status' => $egg['hatch_forecast_status'] ?? null,
                'species' => $egg['species'] ?? null,
                'sex' => $egg['sex'] ?? null,
                'sex_label' => $egg['sex_label'] ?? null,
                'base_color' => $egg['base_color'] ?? null,
                'visual_mutations' => $egg['visual_mutations'] ?? [],
                'split_genes' => $egg['split_hidden_genes'] ?? $egg['split_genes'] ?? [],
                'split_hidden_genes' => $egg['split_hidden_genes'] ?? [],
                'genotype' => $genotypeString !== '' ? $genotypeString : null,
                'genotype_structured' => [
                    'sex_chromosomes' => match ($egg['sex'] ?? null) {
                        'cock', 'male' => 'ZZ',
                        'hen', 'female' => 'ZW',
                        default => null,
                    },
                    'base_color' => ($egg['category'] ?? null) === 'base_color' ? $genotypeString : ($egg['base_color'] ?? null),
                    'alleles' => $alleles,
                    'loci' => [
                        [
                            'category' => $egg['category'] ?? null,
                            'name' => $egg['trait_name'] ?? null,
                            'genotype' => $genotypeString,
                            'inheritance_type' => $egg['inheritance_type'] ?? null,
                        ],
                    ],
                    'mutations' => $egg['visual_mutations'] ?? [],
                    'display' => $genotypeString,
                ],
                'genotype_loci' => [
                    [
                        'category' => $egg['category'] ?? null,
                        'name' => $egg['trait_name'] ?? null,
                        'genotype' => $genotypeString,
                    ],
                ],
                'genotype_display' => $genotypeString,
                'phenotype' => $egg['phenotype'] ?? null,
                'phenotype_structured' => [
                    'base_color' => $egg['base_color'] ?? null,
                    'visual_mutations' => $egg['visual_mutations'] ?? [],
                    'split_genes' => $egg['split_hidden_genes'] ?? [],
                    'description' => $egg['phenotype'] ?? null,
                    'display' => $egg['phenotype'] ?? null,
                ],
                'pattern' => $egg['pattern'] ?? null,
                'markings' => $egg['markings'] ?? null,
                'eyes' => $egg['eyes'] ?? null,
                'head' => $egg['head'] ?? null,
                'body' => $egg['body'] ?? null,
                'wings' => $egg['wings'] ?? null,
                'rump' => $egg['rump'] ?? null,
                'tail' => $egg['tail'] ?? null,
                'other_calculated_visual_characteristics' => $egg['other_visual_characteristics'] ?? null,
                'probability' => $egg['probability'] ?? null,
                'parent_1_inheritance' => $egg['parent_1_inheritance'] ?? null,
                'parent_2_inheritance' => $egg['parent_2_inheritance'] ?? null,
                'parent_inheritance' => [
                    'parent_1' => $egg['parent_1_inheritance'] ?? null,
                    'parent_2' => $egg['parent_2_inheritance'] ?? null,
                ],
                'inherited_traits' => $egg['inherited_traits'] ?? [],
                'inherited_from' => [
                    'parent_1_code' => $egg['parent_1_inheritance'] ?? null,
                    'parent_2_code' => $egg['parent_2_inheritance'] ?? null,
                ],
                'genetic_explanation' => $egg['genetic_explanation'] ?? null,
                'ai_interpretation' => $egg['ai_interpretation'] ?? null,
                'ai_visual_description' => $egg['ai_visual_description'] ?? null,
                'visualization' => [
                    'species' => is_array($egg['species'] ?? null) ? ($egg['species']['common_name'] ?? null) : null,
                    'sex' => $egg['sex'] ?? null,
                    'base_color' => $egg['base_color'] ?? null,
                    'visual_mutations' => $egg['visual_mutations'] ?? [],
                    'split_genes' => $egg['split_hidden_genes'] ?? [],
                    'genotype' => $genotypeString,
                    'phenotype' => $egg['phenotype'] ?? null,
                    'pattern' => $egg['pattern'] ?? null,
                    'markings' => $egg['markings'] ?? null,
                    'eyes' => $egg['eyes'] ?? null,
                    'head' => $egg['head'] ?? null,
                    'body' => $egg['body'] ?? null,
                    'wings' => $egg['wings'] ?? null,
                    'rump' => $egg['rump'] ?? null,
                    'tail' => $egg['tail'] ?? null,
                    'other_calculated_visual_characteristics' => $egg['other_visual_characteristics'] ?? null,
                ],
                'image' => $egg['image'] ?? null,
                'collected_traits' => $egg['collected_traits'] ?? [
                    'species' => $egg['species'] ?? null,
                    'base_color' => $egg['base_color'] ?? null,
                    'visual_mutations' => $egg['visual_mutations'] ?? [],
                    'split_hidden_genes' => $egg['split_hidden_genes'] ?? [],
                ],
                'composed_image_prompt' => $egg['composed_image_prompt'] ?? null,
                'hf_image_prompt' => $egg['hf_image_prompt'] ?? null,
                'image_prompt_short' => $egg['image_prompt_short'] ?? null,
                'image_prompt_pdf' => $egg['image_prompt_pdf'] ?? null,
                'title' => $egg['title'] ?? 'Predicted Offspring Visualization',
                'disclaimer' => $egg['disclaimer'] ?? 'AI Visual Representation — Not an Exact Biological Guarantee',
                'example_note' => 'Illustrative deterministic genetic outcome from RBGIA. Not a guaranteed individual chick.',
            ];
        }, $eggOutcomes, array_keys($eggOutcomes)));
    }

    /**
     * @param  array<string, mixed>  $validation
     * @param  array<string, mixed>  $prediction
     * @param  list<array<string, mixed>>  $examples
     * @return array{traits: array<string, mixed>, report: array<string, mixed>}
     */
    private function inheritedTraits(array $validation, array $prediction, array $examples): array
    {
        $parentOne = $validation['parents']['parent_1'] ?? [];
        $parentTwo = $validation['parents']['parent_2'] ?? [];

        $fromParent = function (array $parent): array {
            $traits = [];
            if (! empty($parent['base_color']['name'])) {
                $traits[] = 'Base color: '.$parent['base_color']['name'];
            }
            foreach ($parent['visual_mutations'] ?? [] as $item) {
                if (! empty($item['name'])) {
                    $traits[] = 'Visual mutation: '.$item['name'];
                }
            }
            foreach ($parent['split_genes'] ?? [] as $item) {
                if (! empty($item['name'])) {
                    $traits[] = 'Split/hidden gene: '.$item['name'];
                }
            }

            return $traits;
        };

        $baseOutcome = collect($prediction['outcomes'] ?? [])
            ->first(fn ($outcome) => is_array($outcome) && ($outcome['category'] ?? null) === 'base_color');
        $mostLikelyBase = null;
        $mostLikelyBasePct = null;
        if (is_array($baseOutcome)) {
            $top = collect($baseOutcome['results'] ?? [])->sortByDesc(fn ($row) => $row['probability'] ?? 0)->first();
            if (is_array($top)) {
                $mostLikelyBase = $baseOutcome['name'] ?? $top['genotype'] ?? null;
                $mostLikelyBasePct = $top['fraction'] ?? $top['probability'] ?? null;
            }
        }

        $sexLinked = collect($prediction['outcomes'] ?? [])
            ->filter(fn ($outcome) => is_array($outcome) && str_contains(strtolower((string) ($outcome['inheritance_type'] ?? '')), 'sex-linked'))
            ->values()
            ->all();

        $traits = [
            'traits_from_parent_1' => $fromParent($parentOne),
            'traits_from_parent_2' => $fromParent($parentTwo),
            'visible_traits' => collect($examples)->pluck('phenotype')->filter()->unique()->values()->all(),
            'recessive_traits_carried' => collect($prediction['outcomes'] ?? [])
                ->filter(fn ($outcome) => is_array($outcome) && (
                    str_contains(strtolower((string) ($outcome['inheritance_type'] ?? '')), 'recessive')
                    || ($outcome['category'] ?? null) === 'split_gene'
                ))
                ->pluck('name')
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'sex_linked_inheritance' => collect($sexLinked)
                ->map(fn ($outcome) => [
                    'name' => $outcome['name'] ?? null,
                    'inheritance_type' => $outcome['inheritance_type'] ?? null,
                    'results' => $outcome['results'] ?? [],
                ])
                ->values()
                ->all(),
            'base_color_inheritance' => $baseOutcome ? [
                'name' => $baseOutcome['name'] ?? null,
                'inheritance_type' => $baseOutcome['inheritance_type'] ?? null,
                'parent_1_code' => $baseOutcome['genetic_code_cock'] ?? null,
                'parent_2_code' => $baseOutcome['genetic_code_hen'] ?? null,
                'results' => $baseOutcome['results'] ?? [],
            ] : null,
            'mutation_inheritance' => collect($prediction['outcomes'] ?? [])
                ->filter(fn ($outcome) => is_array($outcome) && ($outcome['category'] ?? null) === 'visual_mutation')
                ->map(fn ($outcome) => [
                    'name' => $outcome['name'] ?? null,
                    'inheritance_type' => $outcome['inheritance_type'] ?? null,
                    'status' => $outcome['status'] ?? null,
                    'results' => $outcome['results'] ?? [],
                ])
                ->values()
                ->all(),
            'overall_genetic_interpretation' => $prediction['message']
                ?? 'RBGIA used stored genetic codes only. Missing genotypes were not invented.',
        ];

        $report = [
            'parent_inheritance' => [
                'parent_1' => $fromParent($parentOne),
                'parent_2' => $fromParent($parentTwo),
            ],
            'base_color_inheritance' => $traits['base_color_inheritance'],
            'visual_mutation_inheritance' => $traits['mutation_inheritance'],
            'split_hidden_gene_inheritance' => collect($prediction['outcomes'] ?? [])
                ->filter(fn ($outcome) => is_array($outcome) && ($outcome['category'] ?? null) === 'split_gene')
                ->values()
                ->all(),
            'sex_linked_inheritance' => $traits['sex_linked_inheritance'],
            'most_likely_phenotype' => collect($examples)->pluck('phenotype')->filter()->first(),
            'most_likely_base_color' => $mostLikelyBase,
            'most_likely_base_color_measure' => $mostLikelyBasePct,
            'likely_visual_mutations' => collect($examples)->pluck('visual_mutations')->flatten()->unique()->values()->all(),
            'likely_split_genes' => collect($examples)->pluck('split_genes')->flatten()->unique()->values()->all(),
            'sex_distribution' => collect($prediction['outcomes'] ?? [])
                ->flatMap(fn ($outcome) => is_array($outcome) ? ($outcome['results'] ?? []) : [])
                ->groupBy(fn ($row) => $row['sex'] ?? 'both')
                ->map(fn ($rows) => $rows->sum(fn ($row) => (float) ($row['probability'] ?? 0)))
                ->all(),
            'genetic_explanation' => $prediction['message'] ?? null,
        ];

        return ['traits' => $traits, 'report' => $report];
    }

    /**
     * @param  array<string, mixed>  $validation
     * @param  array<string, mixed>  $compatibility
     * @param  array<string, mixed>  $prediction
     * @param  array<string, mixed>  $probabilities
     * @param  array<string, mixed>  $gica
     * @param  array<string, mixed>|null  $clutchSimulation
     * @return array<string, mixed>
     */
    private function verification(array $validation, array $compatibility, array $prediction, array $probabilities, array $gica = [], ?array $clutchSimulation = null): array
    {
        $loci = count($prediction['outcomes'] ?? []);
        $calculated = collect($prediction['outcomes'] ?? [])
            ->filter(fn ($outcome) => is_array($outcome) && ($outcome['status'] ?? null) === 'calculated')
            ->count();
        $resultRows = collect($prediction['outcomes'] ?? [])
            ->sum(fn ($outcome) => is_array($outcome) ? count($outcome['results'] ?? []) : 0);

        $jointOutcomes = count($prediction['theoretical_distribution'] ?? []);
        $gicaFactors = count($gica['breakdown'] ?? []);
        $eggs = count($clutchSimulation['eggs'] ?? []);
        $stages = 3;
        $punnettCells = $calculated * 4;

        $rowProbabilitySum = collect($prediction['outcomes'] ?? [])
            ->filter(fn ($outcome) => is_array($outcome) && ($outcome['status'] ?? null) === 'calculated')
            ->map(function (array $outcome) {
                $sum = collect($outcome['results'] ?? [])->sum(fn ($row) => (float) ($row['probability'] ?? 0));

                return abs(1.0 - $sum) < 0.02;
            });

        $normalizedOk = $rowProbabilitySum->filter()->count();
        $confidence = $calculated === 0
            ? 0
            : (int) round(100 * ($normalizedOk / max(1, $calculated)));

        return [
            'method' => self::METHOD,
            'genetic_rule_verification' => ($validation['can_predict'] ?? false)
                ? 'Mendelian expansions used only where both parents had stored genetic_code values for the same locus.'
                : 'Blocking validation findings prevented a complete rule-verified prediction.',
            'data_verification_status' => $compatibility['verification_status']
                ?? (($prediction['provisional'] ?? false) ? 'Needs Verification' : 'Verified records used where available'),
            'scientific_source' => $compatibility['scientific_source'] ?? $compatibility['scientific_basis'] ?? null,
            'calculation_confidence' => ($prediction['provisional'] ?? false)
                ? 'Provisional — one or more selected traits need verification.'
                : 'Deterministic RBGIA fractions from stored genotype codes.',
            'base_color_probabilities' => $probabilities['base_color'] ?? [],
            'sex_probabilities' => $probabilities['sex'] ?? [],
            'mutation_probabilities' => $probabilities['visual_mutations'] ?? [],
            'split_probabilities' => $probabilities['split_hidden_genes'] ?? [],
            'confidence_score' => $confidence,
            'confidence_score_note' => 'Internal consistency of per-locus probability normalization. Not a claim of biological accuracy.',
            'punnett_detail' => $this->punnettDetail($prediction),
            'time_complexity' => 'O(C + P + F + E·S)',
            'time_complexity_this_run' => 'O('.$punnettCells.' + '.$jointOutcomes.' + '.$gicaFactors.' + '.$eggs.'·'.$stages.')',
            'time_complexity_explanation' => 'C = Punnett cells across calculated loci. P = joint offspring combinations. F = GICA factors. E·S = clutch eggs × viability stages. Values are taken from this pair’s stored calculations.',
            'space_complexity' => 'O(C + R + F + E)',
            'space_complexity_this_run' => 'O('.$punnettCells.' + '.$jointOutcomes.' + '.$gicaFactors.' + '.$eggs.')',
            'space_complexity_explanation' => 'C = Punnett cells. R = retained joint rows. F = GICA factor records. E = simulated eggs.',
            'input_size' => [
                'loci_considered' => $loci,
                'loci_calculated' => $calculated,
                'punnett_cells' => $punnettCells,
                'result_rows' => $resultRows,
                'joint_outcomes' => $jointOutcomes,
                'gica_factors' => $gicaFactors,
                'clutch_eggs' => $eggs,
                'clutch_stages' => $stages,
            ],
            'visualization_limitation' => 'AI images are approximate phenotype visualizations only and never modify RBGIA genetics.',
            'scalability' => 'Work scales with locus count, joint product size, GICA factors, and simulated clutch size from the selected parental records.',
        ];
    }

    /**
     * @param  array<string, mixed>  $prediction
     * @param  array<string, mixed>  $probabilities
     * @return array<string, mixed>
     */
    private function geneticAnalysis(array $prediction, array $probabilities): array
    {
        return [
            'status' => $prediction['status'] ?? null,
            'provisional' => $prediction['provisional'] ?? false,
            'message' => $prediction['message'] ?? null,
            'outcomes' => $prediction['outcomes'] ?? [],
            'probability_tables' => $probabilities,
            'source_of_truth' => 'Species-specific stored genetic_code / inheritance_type / phenotype records.',
        ];
    }

    /**
     * @param  array<string, mixed>  $prediction
     * @return list<array<string, mixed>>
     */
    private function punnettDetail(array $prediction): array
    {
        $rows = [];
        foreach ($prediction['outcomes'] ?? [] as $outcome) {
            if (! is_array($outcome) || ($outcome['status'] ?? null) !== 'calculated') {
                continue;
            }
            $rows[] = [
                'locus' => $outcome['name'] ?? null,
                'category' => $outcome['category'] ?? null,
                'inheritance_type' => $outcome['inheritance_type'] ?? null,
                'parent_1_code' => $outcome['genetic_code_cock'] ?? null,
                'parent_2_code' => $outcome['genetic_code_hen'] ?? null,
                'results' => $outcome['results'] ?? [],
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $prediction
     * @param  list<array<string, mixed>>  $examples
     * @return array<string, mixed>
     */
    private function algorithmMeta(array $prediction, array $examples): array
    {
        $loci = count($prediction['outcomes'] ?? []);

        return [
            'name' => 'AGAPORA Rule-Based Genetic Inheritance Analysis',
            'method' => self::METHOD,
            'components' => ['species_compatibility', 'RBGIA', 'GICA', 'reproductive_forecast', 'illustrative_egg_chick_examples'],
            'loci_processed' => $loci,
            'example_cards' => count($examples),
            'deterministic' => true,
            'notes' => [
                'Base-color and mutation alleles come from stored genetic records for the selected species.',
                'Missing parental genotypes are never assumed wild type.',
                'GICA does not modify RBGIA probabilities.',
                'Egg/chick cards are illustrative genetic outcomes, not a biological clutch simulator.',
            ],
        ];
    }
}

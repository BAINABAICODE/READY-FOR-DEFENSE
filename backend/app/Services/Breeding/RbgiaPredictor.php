<?php

namespace App\Services\Breeding;

use App\Models\Bird;
use App\Services\Breeding\Rbgia\GeneticCodeParser;
use App\Services\Breeding\Rbgia\JointOffspringAssembler;
use App\Services\Breeding\Rbgia\LocusPunnett;
use App\Services\Breeding\Rbgia\MendelianLocusResolver;
use App\Services\Breeding\Rbgia\PhenotypeFromGenotype;
use App\Services\Breeding\Rbgia\SplitCarrierHomozygousComputation;

/**
 * AGAPORA RBGIA — deterministic two-parent inheritance from stored genetic records.
 *
 * Pipeline:
 * Parent records → species-aware locus resolution → gametes → Punnett →
 * joint offspring distribution → phenotype mapping.
 *
 * Never invents alleles or wild-type fillers for missing parental genotypes.
 */
class RbgiaPredictor
{
    public function __construct(
        private readonly GeneticCodeParser $parser,
        private readonly LocusPunnett $punnett,
        private readonly JointOffspringAssembler $assembler,
        private readonly PhenotypeFromGenotype $phenotypes,
        private readonly MendelianLocusResolver $mendelian,
        private readonly SplitCarrierHomozygousComputation $splitCarrierHomozygous,
    ) {}

    /**
     * @param  array<string, mixed>  $validation
     * @return array<string, mixed>
     */
    public function predict(Bird $parentOne, Bird $parentTwo, array $validation): array
    {
        if (! ($validation['can_predict'] ?? false)) {
            return [
                'status' => 'blocked',
                'message' => 'RBGIA did not run because the pairing still has a blocking validation error.',
                'confidence' => 'INSUFFICIENT_DATA',
                'parent_profiles' => [
                    'parent_1' => $this->parentProfile($parentOne, 'Parent 1'),
                    'parent_2' => $this->parentProfile($parentTwo, 'Parent 2'),
                ],
                'outcomes' => [],
                'theoretical_distribution' => [],
                'punnett_squares' => [],
            ];
        }

        [$cock, $hen] = $this->resolveCockAndHen($parentOne, $parentTwo);
        $speciesId = (int) (($cock?->species_id) ?: ($hen?->species_id) ?: 0);

        $parentProfiles = [
            'parent_1' => $this->parentProfile($parentOne, 'Parent 1'),
            'parent_2' => $this->parentProfile($parentTwo, 'Parent 2'),
            'cock' => $cock ? $this->parentProfile($cock, 'Cock') : null,
            'hen' => $hen ? $this->parentProfile($hen, 'Hen') : null,
        ];

        if (! $cock || ! $hen) {
            return [
                'status' => 'insufficient_data',
                'message' => 'RBGIA requires one cock and one hen with known sexes for avian ZW inheritance.',
                'confidence' => 'INSUFFICIENT_DATA',
                'parent_profiles' => $parentProfiles,
                'outcomes' => [[
                    'category' => 'chromosomal_sex',
                    'name' => 'Chromosomal sex (ZW)',
                    'status' => 'not_calculated',
                    'reason' => 'Calculation unavailable. Reason: Required parental sex information is missing or parents are not a cock×hen pair.',
                    'results' => [],
                ]],
                'theoretical_distribution' => [],
                'punnett_squares' => [],
            ];
        }

        $outcomes = [];
        $outcomes = array_merge($outcomes, $this->baseColorLoci($cock, $hen, $speciesId));
        $outcomes = array_merge($outcomes, $this->mendelianMutationOutcomes($cock, $hen, $speciesId));
        $outcomes = array_merge($outcomes, $this->chromosomalSexOutcome($cock, $hen, $outcomes));

        $joint = $this->assembler->assemble($outcomes);
        $theoretical = $this->decorateJoint($joint['joint_outcomes'], $speciesId);
        $punnettSquares = $this->punnettSquares($outcomes);

        $confidence = $joint['confidence'];
        if ($this->anyNeedsVerification($parentOne) || $this->anyNeedsVerification($parentTwo)) {
            $confidence = $confidence === 'INSUFFICIENT_DATA' ? $confidence : 'PARTIALLY_DETERMINED';
        }

        return [
            'status' => $joint['status'] === 'insufficient_data' ? 'insufficient_data' : 'completed',
            'message' => 'Each gene is one Mendelian locus: the two alleles segregate, and separate loci assort independently. A recessive visual mutation is a hidden split when only one mutant allele is inherited. Sex-linked alleles are segments of the Z chromosome. Missing genotypes were not invented.',
            'provisional' => $confidence !== 'CONFIRMED',
            'confidence' => $confidence,
            'confidence_notes' => $joint['notes'],
            'parent_profiles' => $parentProfiles,
            'outcomes' => $outcomes,
            'theoretical_distribution' => $theoretical,
            'punnett_squares' => $punnettSquares,
            'methodology' => [
                'name' => 'AGAPORA-RBGIA-v2',
                'steps' => [
                    'parent_records',
                    'species_validation',
                    'genetic_data_resolution',
                    'parental_genotype_construction',
                    'allele_locus_analysis',
                    'gamete_generation',
                    'parent_cross',
                    'offspring_genotype_distribution',
                    'phenotype_distribution',
                ],
                'sex_chromosome_model' => 'avian_ZW',
                'randomness' => false,
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function baseColorLoci(Bird $cock, Bird $hen, int $speciesId): array
    {
        $left = $cock->baseColor;
        $right = $hen->baseColor;

        if (! $left || ! $right) {
            return [[
                'category' => 'base_color',
                'name' => 'Base Color',
                'locus_key' => 'base_color',
                'status' => 'not_calculated',
                'reason' => 'One or both parents have no stored base color. A wild-type genotype was not assumed.',
                'confidence' => 'unknown',
                'results' => [],
            ]];
        }

        if ((int) $left->lovebird_species_id !== (int) $right->lovebird_species_id) {
            return [[
                'category' => 'base_color',
                'name' => 'Base Color',
                'status' => 'not_calculated',
                'reason' => 'Parents reference base-color records from different species datasets. Cross-species allele transfer was not assumed.',
                'results' => [],
            ]];
        }

        $cockGround = $this->mendelian->resolveGround($cock, $this->parser->parseSegments($left->genetic_code));
        $henGround = $this->mendelian->resolveGround($hen, $this->parser->parseSegments($right->genetic_code));
        $cockSegments = $cockGround['segments'];
        $henSegments = $henGround['segments'];
        $allelePhenotypes = array_merge($cockGround['allele_phenotypes'], $henGround['allele_phenotypes']);

        if ($cockSegments === [] || $henSegments === []) {
            return [[
                'category' => 'base_color',
                'name' => $left->name,
                'inheritance_type' => $left->inheritance_type,
                'genetic_code_cock' => $left->genetic_code,
                'genetic_code_hen' => $right->genetic_code,
                'status' => 'not_calculated',
                'reason' => 'Stored base-color genetic_code could not be parsed into diploid allele pairs (or is UNVERIFIED). No genotype was invented from the display name.',
                'results' => [],
            ]];
        }

        $outcomes = [];
        $pairCount = min(count($cockSegments), count($henSegments));
        for ($index = 0; $index < $pairCount; $index++) {
            $cockSeg = $cockSegments[$index];
            $henSeg = $henSegments[$index];
            $hint = $cockSeg['locus_hint'] !== 'locus' ? $cockSeg['locus_hint'] : $henSeg['locus_hint'];
            $name = match ($hint) {
                'dark_factor' => 'Dark factor (D)',
                'ground_color' => 'Ground color ('.$left->series.')',
                default => $left->name.($pairCount > 1 ? ' locus '.($index + 1) : ''),
            };

            $outcome = $this->locusOutcome(
                category: 'base_color',
                name: $name,
                locusKey: $hint,
                inheritanceType: $hint === 'dark_factor' ? 'Intermediate dominant' : ($left->inheritance_type ?: $right->inheritance_type),
                cockCode: $cockSeg['segment'],
                henCode: $henSeg['segment'],
                cockAlleles: $cockSeg['alleles'],
                henAlleles: $henSeg['alleles'],
                verificationStatus: $left->verification_status,
                sexLinked: false,
                speciesId: $speciesId,
                parentContribution: [
                    'parent_cock' => [
                        'record' => $hint === 'ground_color' ? ($cockGround['ground_record'] ?? $left->name) : $left->name,
                        'code' => $cockSeg['segment'],
                        'confidence' => $this->recordConfidence($left->verification_status),
                    ],
                    'parent_hen' => [
                        'record' => $hint === 'ground_color' ? ($henGround['ground_record'] ?? $right->name) : $right->name,
                        'code' => $henSeg['segment'],
                        'confidence' => $this->recordConfidence($right->verification_status),
                    ],
                ],
            );
            if ($hint === 'ground_color' && $allelePhenotypes !== []) {
                $outcome['allele_phenotypes'] = $allelePhenotypes;
            }
            $outcomes[] = $outcome;
        }

        if (count($cockSegments) !== count($henSegments)) {
            $outcomes[] = [
                'category' => 'base_color',
                'name' => 'Base-color locus alignment',
                'status' => 'not_calculated',
                'reason' => 'Parents have a different number of parseable genetic_code segments. Unaligned segments were not invented or force-matched.',
                'results' => [],
            ];
        }

        return $outcomes;
    }

    /**
     * Visual mutations and split genes of the same locus are one cross.
     * A recessive heterozygote is a hidden split. A dominant or incomplete-dominant heterozygote stays visual.
     *
     * @return list<array<string, mixed>>
     */
    private function mendelianMutationOutcomes(Bird $cock, Bird $hen, int $speciesId): array
    {
        $outcomes = [];

        foreach ($this->mendelian->mutationSpecs($cock, $hen) as $spec) {
            if (($spec['status'] ?? null) !== 'calculated') {
                $outcomes[] = [
                    'category' => $spec['category'],
                    'name' => $spec['name'],
                    'locus_key' => $spec['locus_key'],
                    'inheritance_type' => $spec['inheritance_type'],
                    'status' => $spec['status'],
                    'reason' => $spec['reason'] ?? 'Calculation unavailable.',
                    'confidence' => ($spec['status'] ?? null) === 'blocked' ? 'confirmed_rule_block' : 'unknown',
                    'results' => [],
                ];
                continue;
            }

            $outcome = $this->locusOutcome(
                category: $spec['category'],
                name: $spec['name'],
                locusKey: $spec['locus_key'],
                inheritanceType: $spec['inheritance_type'],
                cockCode: $spec['cock_code'],
                henCode: $spec['hen_code'],
                cockAlleles: $spec['cock_alleles'],
                henAlleles: $spec['hen_alleles'],
                verificationStatus: $spec['verification_status'],
                sexLinked: (bool) $spec['sex_linked'],
                speciesId: $speciesId,
                parentContribution: [
                    'parent_cock' => [
                        'record' => $spec['cock_record'],
                        'code' => $spec['cock_code'],
                        'confidence' => $spec['assumed_cock'] ? 'probable' : $this->recordConfidence($spec['verification_status']),
                        'assumed_non_carrier' => $spec['assumed_cock'],
                    ],
                    'parent_hen' => [
                        'record' => $spec['hen_record'],
                        'code' => $spec['hen_code'],
                        'confidence' => $spec['assumed_hen'] ? 'probable' : $this->recordConfidence($spec['verification_status']),
                        'assumed_non_carrier' => $spec['assumed_hen'],
                    ],
                ],
            );
            if ($spec['assumed_cock'] || $spec['assumed_hen']) {
                $outcome['provisional'] = true;
                $outcome['confidence'] = 'probable';
                $outcome['assumption_note'] = 'The parent who does not carry this gene is the documented wild type. A recessive mutant allele from the other parent stays hidden unless the chick inherits two copies, or one Z-linked copy in a hen.';
            }
            $outcomes[] = $outcome;
        }

        return $outcomes;
    }

    /**
     * Avian chromosomal sex segregation (ZZ cock / ZW hen) when both parents have known sexes
     * and no sex-linked locus already tags offspring sex.
     *
     * @param  list<array<string, mixed>>  $outcomes
     * @return list<array<string, mixed>>
     */
    private function chromosomalSexOutcome(Bird $cock, Bird $hen, array $outcomes): array
    {
        if ($cock->sex !== Bird::SEX_COCK || $hen->sex !== Bird::SEX_HEN) {
            return [[
                'category' => 'chromosomal_sex',
                'name' => 'Chromosomal sex (ZW)',
                'locus_key' => 'chromosomal_sex',
                'status' => 'not_calculated',
                'reason' => 'Calculation unavailable. Required parental sex information is missing or not a cock×hen pair.',
                'results' => [],
            ]];
        }

        $sexLinkedCalculated = collect($outcomes)->contains(function ($outcome) {
            return is_array($outcome)
                && ($outcome['status'] ?? null) === 'calculated'
                && $this->isSexLinked($outcome['inheritance_type'] ?? null);
        });

        if ($sexLinkedCalculated) {
            // Sex already determined jointly with sex-linked mutation outcomes.
            return [];
        }

        return [[
            'category' => 'chromosomal_sex',
            'name' => 'Chromosomal sex (ZW)',
            'locus_key' => 'chromosomal_sex',
            'inheritance_type' => 'Avian ZW segregation',
            'genetic_code_cock' => 'Z/Z',
            'genetic_code_hen' => 'Z/W',
            'status' => 'calculated',
            'confidence' => 'confirmed',
            'parent_contribution' => [
                'parent_cock' => ['record' => 'Cock ZZ', 'code' => 'Z/Z', 'confidence' => 'confirmed'],
                'parent_hen' => ['record' => 'Hen ZW', 'code' => 'Z/W', 'confidence' => 'confirmed'],
            ],
            'punnett' => [
                'parent_1_alleles' => ['Z', 'Z'],
                'parent_2_alleles' => ['Z', 'W'],
                'sex_linked' => true,
            ],
            'results' => [
                [
                    'genotype' => 'ZZ',
                    'sex' => 'cock',
                    'count' => 1,
                    'total' => 2,
                    'fraction' => '1/2',
                    'probability' => 0.5,
                    'expression' => 'male',
                    'phenotype' => 'Male / Cock',
                    'inheritance_path' => [
                        'parent_1_gamete_pool' => 'Z from cock',
                        'parent_2_gamete_pool' => 'Z from hen',
                        'combination' => 'ZZ',
                    ],
                    'gamete_combinations' => [['from_cock_Z' => 'Z', 'from_hen_Z' => 'Z', 'sex' => 'cock']],
                ],
                [
                    'genotype' => 'ZW',
                    'sex' => 'hen',
                    'count' => 1,
                    'total' => 2,
                    'fraction' => '1/2',
                    'probability' => 0.5,
                    'expression' => 'female',
                    'phenotype' => 'Female / Hen',
                    'inheritance_path' => [
                        'parent_1_gamete_pool' => 'Z from cock',
                        'parent_2_gamete_pool' => 'W from hen',
                        'combination' => 'ZW',
                    ],
                    'gamete_combinations' => [['from_cock_Z' => 'Z', 'from_hen' => 'W', 'sex' => 'hen']],
                ],
            ],
        ]];
    }

    /**
     * @return array{0: ?Bird, 1: ?Bird}
     */
    private function resolveCockAndHen(Bird $parentOne, Bird $parentTwo): array
    {
        $cock = null;
        $hen = null;

        if ($parentOne->sex === Bird::SEX_COCK) {
            $cock = $parentOne;
        } elseif ($parentTwo->sex === Bird::SEX_COCK) {
            $cock = $parentTwo;
        }

        if ($parentOne->sex === Bird::SEX_HEN) {
            $hen = $parentOne;
        } elseif ($parentTwo->sex === Bird::SEX_HEN) {
            $hen = $parentTwo;
        }

        if ($cock && $hen && $cock->id !== $hen->id) {
            return [$cock, $hen];
        }

        return [null, null];
    }

    /**
     * @param  array{0: string, 1: string}|null  $cockAlleles
     * @param  array{0: string, 1: string}|null  $henAlleles
     * @param  array<string, mixed>  $parentContribution
     * @return array<string, mixed>
     */
    private function locusOutcome(
        string $category,
        string $name,
        string $locusKey,
        ?string $inheritanceType,
        ?string $cockCode,
        ?string $henCode,
        ?array $cockAlleles,
        ?array $henAlleles,
        ?string $verificationStatus,
        bool $sexLinked,
        int $speciesId,
        array $parentContribution,
    ): array {
        if ($cockAlleles === null || $henAlleles === null) {
            return [
                'category' => $category,
                'name' => $name,
                'locus_key' => $locusKey,
                'inheritance_type' => $inheritanceType,
                'genetic_code_cock' => $cockCode,
                'genetic_code_hen' => $henCode,
                'status' => 'not_calculated',
                'reason' => 'The stored genetic code could not be read as a genotype pair. No genotype was generated from the gene name.',
                'confidence' => 'unknown',
                'parent_contribution' => $parentContribution,
                'results' => [],
            ];
        }

        $results = $this->punnett->cross($cockAlleles, $henAlleles, $sexLinked, (string) $inheritanceType);
        foreach ($results as &$row) {
            $mapped = $this->phenotypes->forLocus([
                'category' => $category,
                'name' => $name,
                'locus_key' => $locusKey,
                'genotype' => $row['genotype'],
                'expression' => $row['expression'] ?? null,
            ], $speciesId);
            $row['phenotype'] = $mapped['phenotype'];
            $row['base_color'] = $mapped['base_color'];
            $row['visual_mutations'] = $mapped['visual_mutations'];
            $row['split_hidden'] = $mapped['split_hidden'];
            $row['phenotype_note'] = $mapped['note'];
        }
        unset($row);

        $carrierHomozygous = $this->splitCarrierHomozygous->summarize(
            $cockAlleles,
            $henAlleles,
            $sexLinked,
            (string) $inheritanceType,
            $results,
        );

        return [
            'category' => $category,
            'name' => $name,
            'locus_key' => $locusKey,
            'inheritance_type' => $inheritanceType,
            'genetic_code_cock' => $cockCode,
            'genetic_code_hen' => $henCode,
            'verification_status' => $verificationStatus,
            'status' => 'calculated',
            'confidence' => $this->recordConfidence($verificationStatus),
            'provisional' => $this->needsVerification($verificationStatus),
            'parent_contribution' => $parentContribution,
            'punnett' => [
                'parent_1_alleles' => $cockAlleles,
                'parent_2_alleles' => $henAlleles,
                'sex_linked' => $sexLinked,
                'results' => $results,
            ],
            'results' => $results,
            'carrier_homozygous' => $carrierHomozygous,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $joint
     * @return list<array<string, mixed>>
     */
    private function decorateJoint(array $joint, int $speciesId): array
    {
        return array_map(function (array $row) use ($speciesId) {
            $mapped = $this->phenotypes->forJoint($row['loci'] ?? [], $speciesId);
            $row['phenotype'] = $mapped['phenotype'];
            $row['base_color'] = $mapped['base_color'];
            $row['base_color_genotype'] = $mapped['base_color_genotype'] ?? null;
            $row['dark_factor'] = $mapped['dark_factor'] ?? null;
            $row['visual_mutations'] = $mapped['visual_mutations'];
            $row['split_hidden_genes'] = $mapped['split_hidden'];
            $row['sex_label'] = match ($row['sex'] ?? null) {
                'cock', 'male' => 'Male / Cock',
                'hen', 'female' => 'Female / Hen',
                default => null,
            };
            $row['passed_from_parents'] = $this->passedFromParents($row['loci'] ?? []);
            $row['inherited_from'] = [
                'paths' => $row['inheritance_paths'] ?? [],
                'passed' => $row['passed_from_parents'],
            ];

            return $row;
        }, $joint);
    }

    /**
     * @param  list<array<string, mixed>>  $outcomes
     * @return list<array<string, mixed>>
     */
    private function punnettSquares(array $outcomes): array
    {
        $squares = [];
        foreach ($outcomes as $outcome) {
            if (($outcome['status'] ?? null) !== 'calculated') {
                continue;
            }
            $squares[] = [
                'locus' => $outcome['name'] ?? null,
                'category' => $outcome['category'] ?? null,
                'inheritance_type' => $outcome['inheritance_type'] ?? null,
                'parent_1_alleles' => $outcome['punnett']['parent_1_alleles'] ?? null,
                'parent_2_alleles' => $outcome['punnett']['parent_2_alleles'] ?? null,
                'sex_linked' => $outcome['punnett']['sex_linked'] ?? false,
                'results' => $outcome['results'] ?? [],
            ];
        }

        return $squares;
    }

    /**
     * @return array<string, mixed>
     */
    private function parentProfile(Bird $bird, string $label): array
    {
        $grandparents = [];
        foreach ($bird->grandparents ?? [] as $grandparent) {
            $grandparents[] = [
                'relation' => $grandparent->relation ?? $grandparent->side ?? null,
                'bird_id' => $grandparent->bird_id ?? null,
                'notes' => $grandparent->notes ?? null,
                'confidence' => 'evidence_only',
                'usage' => 'Grandparent rows are retained as evidence only. They do not invent a parental genotype.',
            ];
        }

        return [
            'label' => $label,
            'bird_id' => $bird->bird_id,
            'sex' => $bird->sex,
            'species_id' => $bird->species_id,
            'species' => $bird->species?->common_name,
            'base_color' => $bird->baseColor?->name,
            'base_color_code' => $bird->baseColor?->genetic_code,
            'visual_mutations' => $bird->visualMutations?->pluck('name')->values()->all() ?? [],
            'split_genes' => $bird->splitGenes?->pluck('name')->values()->all() ?? [],
            'grandparents' => $grandparents,
            'confidence' => [
                'base_color' => $this->recordConfidence($bird->baseColor?->verification_status),
                'genotypes_present' => (bool) $bird->baseColor?->genetic_code,
            ],
        ];
    }

    private function recordConfidence(?string $status): string
    {
        if ($status === null || $status === '') {
            return 'unknown';
        }
        if (stripos($status, 'Needs Verification') !== false) {
            return 'probable';
        }
        if (stripos($status, 'Verified') !== false) {
            return 'confirmed';
        }

        return 'probable';
    }

    /**
     * One allele from the cock and one from the hen at each locus.
     * Sex-linked alleles are the Z (or W) chromosome segment that was passed.
     *
     * @param  list<array<string, mixed>>  $loci
     * @return list<array<string, mixed>>
     */
    private function passedFromParents(array $loci): array
    {
        $passed = [];
        foreach ($loci as $locus) {
            if (! is_array($locus)) {
                continue;
            }
            $sexLinked = $this->isSexLinked($locus['inheritance_type'] ?? null)
                || ($locus['category'] ?? null) === 'chromosomal_sex'
                || ($locus['locus_key'] ?? null) === 'chromosomal_sex';
            $passed[] = [
                'locus' => $locus['name'] ?? null,
                'category' => $locus['category'] ?? null,
                'from_cock' => $locus['from_cock'] ?? null,
                'from_hen' => $locus['from_hen'] ?? null,
                'chick_genotype' => $locus['genotype'] ?? null,
                'expression' => $locus['expression'] ?? null,
                'basis' => $sexLinked ? 'chromosomal' : 'mendelian',
            ];
        }

        return $passed;
    }

    private function isSexLinked(?string $inheritanceType): bool
    {
        return $inheritanceType !== null && stripos($inheritanceType, 'Sex-linked') !== false;
    }

    private function needsVerification(?string $status): bool
    {
        return $status !== null && stripos($status, 'Needs Verification') !== false;
    }

    private function anyNeedsVerification(Bird $bird): bool
    {
        $records = collect([$bird->baseColor])
            ->merge($bird->visualMutations ?? [])
            ->merge($bird->splitGenes ?? []);

        return $records->contains(fn ($record) => $record && $this->needsVerification($record->verification_status));
    }
}

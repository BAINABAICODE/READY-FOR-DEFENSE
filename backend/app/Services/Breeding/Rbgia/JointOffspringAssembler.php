<?php

namespace App\Services\Breeding\Rbgia;

/**
 * Combines independent locus distributions into a joint theoretical offspring set.
 * Does not invent outcomes; multiplies only calculated locus probabilities.
 */
class JointOffspringAssembler
{
    private const PROBABILITY_FLOOR = 1e-12;

    /**
     * @param  list<array<string, mixed>>  $locusOutcomes
     * @return array{status: string, confidence: string, joint_outcomes: list<array<string, mixed>>, notes: list<string>}
     */
    public function assemble(array $locusOutcomes): array
    {
        $calculated = array_values(array_filter(
            $locusOutcomes,
            fn ($outcome) => is_array($outcome) && ($outcome['status'] ?? null) === 'calculated' && ($outcome['results'] ?? []) !== [],
        ));
        $incomplete = array_values(array_filter(
            $locusOutcomes,
            fn ($outcome) => is_array($outcome) && in_array($outcome['status'] ?? null, ['not_calculated', 'blocked'], true),
        ));

        $notes = [];
        foreach ($incomplete as $outcome) {
            $notes[] = ($outcome['name'] ?? 'Locus').': '.($outcome['reason'] ?? $outcome['status']);
        }

        if ($calculated === []) {
            return [
                'status' => 'insufficient_data',
                'confidence' => 'INSUFFICIENT_DATA',
                'joint_outcomes' => [],
                'notes' => $notes !== [] ? $notes : ['No shared computable parental genotypes were available.'],
            ];
        }

        $confidence = $incomplete === [] ? 'CONFIRMED' : 'PARTIALLY_DETERMINED';
        $joint = [[
            'probability' => 1.0,
            'sex' => 'both',
            'loci' => [],
            'genotype_parts' => [],
            'expression_parts' => [],
            'categories' => [],
            'inheritance_paths' => [],
        ]];

        foreach ($calculated as $outcome) {
            $next = [];
            foreach ($joint as $prefix) {
                foreach ($outcome['results'] as $row) {
                    if (! is_array($row) || empty($row['genotype'])) {
                        continue;
                    }
                    $sex = $this->combineSex($prefix['sex'], $row['sex'] ?? 'both');
                    if ($sex === null) {
                        continue;
                    }
                    $probability = ((float) $prefix['probability']) * ((float) ($row['probability'] ?? 0));
                    if ($probability < self::PROBABILITY_FLOOR) {
                        continue;
                    }
                    $isChromosomal = ($outcome['category'] ?? null) === 'chromosomal_sex'
                        || ($outcome['locus_key'] ?? null) === 'chromosomal_sex';
                    $genotypeParts = $prefix['genotype_parts'];
                    if (! $isChromosomal) {
                        $genotypeParts[] = ($outcome['name'] ?? 'locus').':'.$row['genotype'];
                    }
                    $gamete = $row['gamete_combinations'][0] ?? [];
                    $next[] = [
                        'probability' => $probability,
                        'sex' => $sex,
                        'loci' => [
                            ...$prefix['loci'],
                            [
                                'category' => $outcome['category'] ?? null,
                                'name' => $outcome['name'] ?? null,
                                'locus_key' => $outcome['locus_key'] ?? null,
                                'inheritance_type' => $outcome['inheritance_type'] ?? null,
                                'genotype' => $row['genotype'],
                                'expression' => $row['expression'] ?? null,
                                'fraction' => $row['fraction'] ?? null,
                                'probability' => $row['probability'] ?? null,
                                'from_cock' => $gamete['from_cock'] ?? $gamete['from_cock_Z'] ?? null,
                                'from_hen' => $gamete['from_hen'] ?? $gamete['from_hen_Z'] ?? null,
                                'allele_phenotypes' => $outcome['allele_phenotypes'] ?? [],
                            ],
                        ],
                        'genotype_parts' => $genotypeParts,
                        'expression_parts' => [...$prefix['expression_parts'], $row['expression'] ?? null],
                        'categories' => [...$prefix['categories'], $outcome['category'] ?? null],
                        'inheritance_paths' => [
                            ...$prefix['inheritance_paths'],
                            [
                                'locus' => $outcome['name'] ?? null,
                                'path' => $row['inheritance_path'] ?? null,
                                'gamete_combinations' => $row['gamete_combinations'] ?? [],
                                'parent_1_code' => $outcome['genetic_code_cock'] ?? null,
                                'parent_2_code' => $outcome['genetic_code_hen'] ?? null,
                            ],
                        ],
                    ];
                }
            }
            $joint = $this->mergeDuplicateJoints($next);
        }

        $normalized = $this->normalize($joint);

        return [
            'status' => 'calculated',
            'confidence' => $confidence,
            'joint_outcomes' => $normalized,
            'notes' => $notes,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function mergeDuplicateJoints(array $rows): array
    {
        $merged = [];
        foreach ($rows as $row) {
            $key = ($row['sex'] ?? 'both').'|'.implode(';', $row['genotype_parts'] ?? []);
            if (! isset($merged[$key])) {
                $merged[$key] = $row;
                continue;
            }
            $merged[$key]['probability'] = ((float) $merged[$key]['probability']) + ((float) $row['probability']);
        }

        return array_values($merged);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function normalize(array $rows): array
    {
        $sum = array_sum(array_map(fn ($row) => (float) ($row['probability'] ?? 0), $rows));
        if ($sum <= 0) {
            return [];
        }

        usort($rows, fn ($a, $b) => ((float) ($b['probability'] ?? 0)) <=> ((float) ($a['probability'] ?? 0)));

        foreach ($rows as &$row) {
            $probability = ((float) $row['probability']) / $sum;
            $row['probability'] = $probability;
            $row['fraction'] = $this->approxFraction($probability);
            $row['genotype'] = implode(' | ', $row['genotype_parts'] ?? []);
            $row['outcome_key'] = md5(($row['sex'] ?? 'both').'|'.$row['genotype']);
        }
        unset($row);

        return $rows;
    }

    private function combineSex(string $left, string $right): ?string
    {
        if ($left === 'both') {
            return $right;
        }
        if ($right === 'both') {
            return $left;
        }
        if ($left === $right) {
            return $left;
        }

        return null;
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
        $gcd = $this->gcd(max($bestNum, 1), $bestDen);

        return ($bestNum / $gcd).'/'.($bestDen / $gcd);
    }

    private function gcd(int $left, int $right): int
    {
        return $right === 0 ? max($left, 1) : $this->gcd($right, $left % $right);
    }
}

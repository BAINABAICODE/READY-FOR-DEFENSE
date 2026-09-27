<?php

namespace App\Services\Breeding\Rbgia;

/**
 * Deterministic gamete × gamete crosses for one locus.
 * Avian Z/W for sex-linked loci; autosomal 2×2 otherwise.
 */
class LocusPunnett
{
    public function __construct(
        private readonly GeneticCodeParser $parser,
    ) {}

    /**
     * @param  array{0: string, 1: string}  $cockAlleles
     * @param  array{0: string, 1: string}  $henAlleles
     * @return list<array<string, mixed>>
     */
    public function cross(array $cockAlleles, array $henAlleles, bool $sexLinked, string $inheritanceType = ''): array
    {
        $results = $sexLinked
            ? $this->sexLinked($cockAlleles, $henAlleles)
            : $this->autosomal($cockAlleles, $henAlleles);

        foreach ($results as &$row) {
            $row['expression'] = $this->expressionClass($row['genotype'], $inheritanceType, $sexLinked, $row['sex'] ?? 'both');
            $row['inheritance_path'] = [
                'parent_1_gamete_pool' => $sexLinked ? 'Z alleles from cock' : 'diploid gametes from cock',
                'parent_2_gamete_pool' => $sexLinked ? 'Z from hen (or W for daughters)' : 'diploid gametes from hen',
                'combination' => $row['genotype'],
            ];
        }
        unset($row);

        return $results;
    }

    /**
     * @param  array{0: string, 1: string}  $cock
     * @param  array{0: string, 1: string}  $hen
     * @return list<array<string, mixed>>
     */
    private function autosomal(array $cock, array $hen): array
    {
        $counts = [];
        $paths = [];
        foreach ($cock as $left) {
            foreach ($hen as $right) {
                $key = $this->parser->pairLabel($left, $right);
                $counts[$key] = ($counts[$key] ?? 0) + 1;
                $paths[$key][] = ['from_cock' => $left, 'from_hen' => $right];
            }
        }

        return $this->fractionRows($counts, 4, 'both', $paths);
    }

    /**
     * Cock ZZ contributes one Z per gamete (each allele equally).
     * Hen ZW: daughters receive W from hen + one Z from cock; sons receive Z from hen + one Z from cock.
     *
     * @param  array{0: string, 1: string}  $cock
     * @param  array{0: string, 1: string}  $hen
     * @return list<array<string, mixed>>
     */
    private function sexLinked(array $cock, array $hen): array
    {
        $henZ = $this->parser->zAllele($hen);
        $sonCounts = [];
        $daughterCounts = [];
        $sonPaths = [];
        $daughterPaths = [];

        foreach ($cock as $cockZ) {
            $son = $this->parser->pairLabel($cockZ, $henZ);
            $sonCounts[$son] = ($sonCounts[$son] ?? 0) + 1;
            $sonPaths[$son][] = ['from_cock_Z' => $cockZ, 'from_hen_Z' => $henZ, 'sex' => 'cock'];

            $daughter = $cockZ.'/W';
            $daughterCounts[$daughter] = ($daughterCounts[$daughter] ?? 0) + 1;
            $daughterPaths[$daughter][] = ['from_cock_Z' => $cockZ, 'from_hen' => 'W', 'sex' => 'hen'];
        }

        $rows = [];
        foreach ($this->fractionRows($sonCounts, 2, 'cock', $sonPaths) as $row) {
            $row['probability'] = $row['probability'] / 2;
            $row['fraction'] = $this->simplifyFraction((int) round($row['probability'] * 4), 4);
            $rows[] = $row;
        }
        foreach ($this->fractionRows($daughterCounts, 2, 'hen', $daughterPaths) as $row) {
            $row['probability'] = $row['probability'] / 2;
            $row['fraction'] = $this->simplifyFraction((int) round($row['probability'] * 4), 4);
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  array<string, int>  $counts
     * @param  array<string, list<array<string, mixed>>>  $paths
     * @return list<array<string, mixed>>
     */
    private function fractionRows(array $counts, int $total, string $sex, array $paths = []): array
    {
        $rows = [];
        foreach ($counts as $genotype => $count) {
            $rows[] = [
                'genotype' => $genotype,
                'sex' => $sex,
                'count' => $count,
                'total' => $total,
                'fraction' => $this->simplifyFraction($count, $total),
                'probability' => $count / $total,
                'gamete_combinations' => $paths[$genotype] ?? [],
            ];
        }

        return $rows;
    }

    private function expressionClass(string $genotype, string $inheritanceType, bool $sexLinked, string $sex): string
    {
        $parts = array_map('trim', explode('/', $genotype));
        $hasW = in_array('W', $parts, true) || in_array('w', $parts, true);
        $mutants = array_values(array_filter($parts, fn ($allele) => $allele !== '' && strcasecmp($allele, 'W') !== 0 && ! str_ends_with($allele, '+')));
        $wild = array_values(array_filter($parts, fn ($allele) => str_ends_with($allele, '+')));

        $type = strtolower($inheritanceType);

        if ($sexLinked && $hasW) {
            return $mutants === [] ? 'hemizygous_wild' : 'visual_hemizygous';
        }

        $incomplete = str_contains($type, 'incomplete')
            || str_contains($type, 'intermediate')
            || str_contains($type, 'partial')
            || str_contains($type, 'co-dominant')
            || str_contains($type, 'codominant');
        $completeDominant = str_contains($type, 'dominant')
            && ! str_contains($type, 'recessive')
            && ! $incomplete;
        $distinctMutants = count($mutants) >= 2 && count(array_unique($mutants)) > 1;

        // Dosage before complete dominance: "Intermediate dominant" and
        // "Incomplete dominant" both contain the word "dominant".
        if ($incomplete && ! str_contains($type, 'recessive')) {
            if ($distinctMutants) {
                return 'visual_compound';
            }
            if (count($mutants) >= 2) {
                return 'visual_double';
            }
            if (count($mutants) === 1 && count($wild) >= 1) {
                return 'visual_single_factor';
            }

            return $mutants === [] ? 'non_carrier' : 'visual';
        }

        if ($completeDominant) {
            if ($distinctMutants) {
                return 'visual_compound';
            }
            if ($mutants !== []) {
                return count($mutants) >= 2 ? 'visual_homozygous' : 'visual_heterozygous';
            }

            return 'non_carrier';
        }

        if ($distinctMutants && $wild === []) {
            return 'visual_compound';
        }

        // Recessive reading for autosomal recessive and Z-linked recessive cocks.
        if (count($mutants) >= 2 && count(array_unique($mutants)) === 1) {
            return 'visual';
        }
        if (count($mutants) >= 1 && count($wild) >= 1) {
            return 'carrier_split';
        }
        if ($mutants === []) {
            return 'non_carrier';
        }

        return 'undetermined_expression';
    }

    private function simplifyFraction(int $count, int $total): string
    {
        $divisor = $this->gcd($count, $total);

        return ($count / $divisor).'/'.($total / $divisor);
    }

    private function gcd(int $left, int $right): int
    {
        return $right === 0 ? max($left, 1) : $this->gcd($right, $left % $right);
    }
}

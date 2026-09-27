<?php

namespace App\Services\Breeding\Rbgia;

/**
 * Mendelian chance that heterozygous split carriers produce a visual homozygous chick.
 * A recessive heterozygote passes the mutant allele in half of its gametes.
 * The chick is visual homozygous only when both parents pass a mutant allele.
 */
class SplitCarrierHomozygousComputation
{
    /**
     * @param  array{0: string, 1: string}  $cockAlleles
     * @param  array{0: string, 1: string}  $henAlleles
     * @param  list<array<string, mixed>>  $results
     * @return array<string, mixed>|null
     */
    public function summarize(array $cockAlleles, array $henAlleles, bool $sexLinked, string $inheritanceType, array $results): ?array
    {
        if (! $this->isRecessive($inheritanceType)) {
            return null;
        }

        $cockCarrier = $this->isHeterozygousCarrier($cockAlleles);
        $henCarrier = ! $sexLinked && $this->isHeterozygousCarrier($henAlleles);
        if (! $cockCarrier && ! $henCarrier) {
            return null;
        }

        $probability = 0.0;
        $genotypes = [];
        $hemizygous = 0.0;
        foreach ($results as $row) {
            if (! is_array($row)) {
                continue;
            }
            $expression = (string) ($row['expression'] ?? '');
            $chance = (float) ($row['probability'] ?? 0);
            if (in_array($expression, ['visual', 'visual_homozygous'], true) && ! $this->hasW((string) ($row['genotype'] ?? ''))) {
                $probability += $chance;
                $genotypes[] = (string) $row['genotype'];
            }
            if ($sexLinked && $expression === 'visual_hemizygous') {
                $hemizygous += $chance;
            }
        }

        $cockPass = $this->mutantDose($cockAlleles);
        $henPass = $sexLinked ? $this->henZMutant($henAlleles) : $this->mutantDose($henAlleles);

        return [
            'applies' => true,
            'cock_heterozygous_carrier' => $cockCarrier,
            'hen_heterozygous_carrier' => $henCarrier,
            'probability' => $probability,
            'fraction' => $this->fraction($probability),
            'formula' => $sexLinked
                ? $this->sexFormula($cockPass, $henPass)
                : $this->autoFormula($cockPass, $henPass),
            'genotypes' => array_values(array_unique(array_filter($genotypes))),
            'hemizygous_visual_probability' => $sexLinked ? $hemizygous : null,
            'hemizygous_visual_fraction' => $sexLinked ? $this->fraction($hemizygous) : null,
            'statement' => $this->statement($cockCarrier, $henCarrier, $sexLinked, $cockPass, $henPass, $probability, $hemizygous),
        ];
    }

    private function isRecessive(string $inheritanceType): bool
    {
        $type = strtolower($inheritanceType);

        return str_contains($type, 'recessive');
    }

    /**
     * @param  array{0: string, 1: string}  $alleles
     */
    private function isHeterozygousCarrier(array $alleles): bool
    {
        $mutants = array_values(array_filter($alleles, fn ($allele) => $this->isMutant($allele)));
        $wild = array_values(array_filter($alleles, fn ($allele) => $this->isWild($allele)));

        return count($mutants) === 1 && count($wild) === 1;
    }

    /**
     * @param  array{0: string, 1: string}  $alleles
     */
    private function mutantDose(array $alleles): float
    {
        $mutants = count(array_filter($alleles, fn ($allele) => $this->isMutant($allele)));

        return $mutants / 2;
    }

    /**
     * @param  array{0: string, 1: string}  $alleles
     */
    private function henZMutant(array $alleles): float
    {
        foreach ($alleles as $allele) {
            if ($this->isMutant($allele)) {
                return 1.0;
            }
        }

        return 0.0;
    }

    private function autoFormula(float $cockPass, float $henPass): string
    {
        return $this->rate($cockPass).' × '.$this->rate($henPass).' = '.$this->fraction($cockPass * $henPass);
    }

    private function sexFormula(float $cockPass, float $henPass): string
    {
        return '1/2 × '.$this->rate($cockPass).' × '.$this->rate($henPass).' = '.$this->fraction(0.5 * $cockPass * $henPass);
    }

    private function statement(bool $cockCarrier, bool $henCarrier, bool $sexLinked, float $cockPass, float $henPass, float $probability, float $hemizygous): string
    {
        $who = match (true) {
            $cockCarrier && $henCarrier => 'Both parents are heterozygous split carriers.',
            $cockCarrier => 'The cock is a heterozygous split carrier.',
            default => 'The hen is a heterozygous split carrier.',
        };

        if ($sexLinked) {
            return $who.' A son is visual homozygous only when he also receives a mutant Z from the hen. '
                .$this->sexFormula($cockPass, $henPass).' of offspring are visual homozygous sons. '
                .$this->fraction($hemizygous).' are visual daughters, who show the one mutant Z they receive from the cock.';
        }

        return $who.' Each heterozygous parent passes the mutant allele in half of its gametes. '
            .'The visual homozygous chance is '.$this->autoFormula($cockPass, $henPass).' of offspring.';
    }

    private function rate(float $value): string
    {
        return $this->fraction($value);
    }

    private function fraction(float $probability): string
    {
        if ($probability <= 1e-9) {
            return '0';
        }
        if (abs($probability - 1) < 1e-9) {
            return '1';
        }

        foreach ([1, 2, 4, 8, 16] as $denominator) {
            $numerator = (int) round($probability * $denominator);
            if (abs($probability - ($numerator / $denominator)) < 1e-6) {
                $divisor = $this->gcd($numerator, $denominator);

                return ($numerator / $divisor).'/'.($denominator / $divisor);
            }
        }

        return rtrim(rtrim(number_format($probability, 4, '.', ''), '0'), '.');
    }

    private function isWild(string $allele): bool
    {
        return str_ends_with(trim($allele), '+');
    }

    private function isMutant(string $allele): bool
    {
        $allele = trim($allele);

        return $allele !== '' && strcasecmp($allele, 'W') !== 0 && ! $this->isWild($allele);
    }

    private function hasW(string $genotype): bool
    {
        foreach (explode('/', $genotype) as $allele) {
            if (strcasecmp(trim($allele), 'W') === 0) {
                return true;
            }
        }

        return false;
    }

    private function gcd(int $left, int $right): int
    {
        return $right === 0 ? max($left, 1) : $this->gcd($right, $left % $right);
    }
}

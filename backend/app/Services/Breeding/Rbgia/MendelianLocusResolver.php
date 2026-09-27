<?php

namespace App\Services\Breeding\Rbgia;

use App\Models\Bird;
use App\Support\GeneticLocus;
use App\Support\SplitGeneCatalog;
use App\Support\VisualMutationCatalog;

/**
 * One Mendelian locus per gene.
 *
 * A visual mutation and a split of the same gene are the same pair of alleles.
 * Recessive heterozygotes stay hidden splits. Dominant and incomplete-dominant
 * heterozygotes stay visual. Sex-linked alleles are read from the Z chromosome.
 */
class MendelianLocusResolver
{
    public function __construct(
        private readonly GeneticCodeParser $parser,
    ) {}

    /**
     * Fold a hidden blue-series split into the ground-color genotype when the
     * stored base color is wild type and can hide that allele.
     *
     * @param  list<array{segment: string, alleles: array{0: string, 1: string}, locus_hint: string}>  $segments
     * @return array{segments: list<array<string, mixed>>, allele_phenotypes: array<string, string>, ground_record: ?string}
     */
    public function resolveGround(Bird $bird, array $segments): array
    {
        $groundIndex = null;
        foreach ($segments as $index => $segment) {
            if (($segment['locus_hint'] ?? null) === 'ground_color') {
                $groundIndex = $index;
                break;
            }
        }

        $labels = [];
        $record = null;
        if ($groundIndex === null) {
            return ['segments' => $segments, 'allele_phenotypes' => [], 'ground_record' => null];
        }

        $baseName = $bird->baseColor?->name;
        $segment = $segments[$groundIndex];
        if ($this->isHomozygous($segment['alleles']) && $baseName) {
            $labels[$segment['alleles'][0]] = $baseName;
        }

        $splits = $this->blSplits($bird);
        if ($splits !== [] && GeneticLocus::canHideBlSplit($segment['segment'])) {
            $mutants = [];
            foreach ($splits as $gene) {
                if ($gene->mutant_allele) {
                    $mutants[$gene->mutant_allele] = SplitGeneCatalog::visualName($gene);
                }
            }

            if (count($mutants) === 1) {
                $gene = $splits[0];
                $alleles = $this->parser->allelePair($gene->heterozygous_genotype ?: $gene->genetic_code);
                if ($alleles !== null) {
                    $label = $this->parser->pairLabel($alleles[0], $alleles[1]);
                    $segments[$groundIndex] = [
                        'segment' => $label,
                        'alleles' => $this->parser->allelePair($label),
                        'locus_hint' => 'ground_color',
                    ];
                    $record = trim(($baseName ? $baseName.', carrying ' : '').$gene->name);
                }
            } elseif (count($mutants) >= 2) {
                $symbols = array_keys($mutants);
                $label = $this->parser->pairLabel($symbols[0], $symbols[1]);
                $segments[$groundIndex] = [
                    'segment' => $label,
                    'alleles' => $this->parser->allelePair($label),
                    'locus_hint' => 'ground_color',
                ];
                $record = trim(($baseName ? $baseName.', carrying ' : '').implode(' + ', array_map(fn ($gene) => $gene->name, $splits)));
            }

            foreach ($mutants as $allele => $name) {
                $labels[$allele] = $name;
            }
            $wild = $splits[0]->wild_type_allele ?? null;
            if (is_string($wild) && $wild !== '' && $baseName) {
                $labels[$wild] = $baseName;
            }
        }

        return [
            'segments' => $segments,
            'allele_phenotypes' => $labels,
            'ground_record' => $record,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function mutationSpecs(Bird $cock, Bird $hen): array
    {
        $groups = [];
        foreach ($cock->visualMutations ?? [] as $mutation) {
            $this->pushVisual($groups, $mutation, 'cock');
        }
        foreach ($hen->visualMutations ?? [] as $mutation) {
            $this->pushVisual($groups, $mutation, 'hen');
        }
        foreach ($cock->splitGenes ?? [] as $gene) {
            $this->pushSplit($groups, $gene, 'cock');
        }
        foreach ($hen->splitGenes ?? [] as $gene) {
            $this->pushSplit($groups, $gene, 'hen');
        }

        $specs = [];
        foreach ($groups as $key => $group) {
            $specs[] = $this->specFor($key, $group);
        }

        usort($specs, fn ($left, $right) => strcmp((string) $left['name'], (string) $right['name']));

        return $specs;
    }

    /**
     * @param  array<string, mixed>  $groups
     */
    private function pushVisual(array &$groups, object $mutation, string $side): void
    {
        $key = VisualMutationCatalog::locusKey($mutation->series ?? null, $mutation->allele ?? null);
        if ($key === null || GeneticLocus::isBlLocus($key)) {
            return;
        }

        $groups[$key]['visuals'][$side][] = $mutation;
        $groups[$key]['inheritance'] = $groups[$key]['inheritance'] ?? ($mutation->inheritance_type ?? null);
        $groups[$key]['name'] = $mutation->name;
        $groups[$key]['verification'] = $groups[$key]['verification'] ?? ($mutation->verification_status ?? null);
        $groups[$key]['has_visual'] = true;
    }

    /**
     * @param  array<string, mixed>  $groups
     */
    private function pushSplit(array &$groups, object $gene, string $side): void
    {
        $key = SplitGeneCatalog::locusKey($gene);
        if ($key === null || GeneticLocus::isBlLocus($key)) {
            return;
        }

        $groups[$key]['splits'][$side][] = $gene;
        $groups[$key]['inheritance'] = $groups[$key]['inheritance'] ?? ($gene->inheritance_type ?? null);
        $groups[$key]['name'] = $groups[$key]['name'] ?? SplitGeneCatalog::visualName($gene);
        $groups[$key]['verification'] = $groups[$key]['verification'] ?? ($gene->verification_status ?? null);
    }

    /**
     * @param  array<string, mixed>  $group
     * @return array<string, mixed>
     */
    private function specFor(string $key, array $group): array
    {
        $inheritance = (string) ($group['inheritance'] ?? '');
        $sexLinked = stripos($inheritance, 'Sex-linked') !== false;
        $hasVisual = (bool) ($group['has_visual'] ?? false);
        $prototype = ($group['visuals']['cock'][0] ?? null)
            ?? ($group['visuals']['hen'][0] ?? null)
            ?? ($group['splits']['cock'][0] ?? null)
            ?? ($group['splits']['hen'][0] ?? null);

        $cock = $this->sideGenotype(
            $group['visuals']['cock'] ?? [],
            $group['splits']['cock'] ?? [],
            Bird::SEX_COCK,
            $sexLinked,
            $prototype,
        );
        $hen = $this->sideGenotype(
            $group['visuals']['hen'] ?? [],
            $group['splits']['hen'] ?? [],
            Bird::SEX_HEN,
            $sexLinked,
            $prototype,
        );

        $base = [
            'category' => $hasVisual ? 'visual_mutation' : 'split_gene',
            'name' => $group['name'] ?? 'Locus',
            'locus_key' => 'mendelian:'.$key,
            'inheritance_type' => $inheritance,
            'sex_linked' => $sexLinked,
            'verification_status' => $group['verification'] ?? null,
        ];

        if ($cock['blocked'] || $hen['blocked']) {
            return [
                ...$base,
                'status' => 'blocked',
                'reason' => $cock['blocked'] ?? $hen['blocked'],
            ];
        }

        if ($cock['code'] === null || $hen['code'] === null || $cock['alleles'] === null || $hen['alleles'] === null) {
            return [
                ...$base,
                'status' => 'not_calculated',
                'reason' => 'Calculation unavailable. Missing parental genotype for this locus, and no documented wild-type/non-carrier allele is stored for the unselected parent.',
            ];
        }

        return [
            ...$base,
            'status' => 'calculated',
            'cock_code' => $cock['code'],
            'hen_code' => $hen['code'],
            'cock_alleles' => $cock['alleles'],
            'hen_alleles' => $hen['alleles'],
            'assumed_cock' => $cock['assumed'],
            'assumed_hen' => $hen['assumed'],
            'cock_record' => $cock['record'],
            'hen_record' => $hen['record'],
        ];
    }

    /**
     * @param  list<object>  $visuals
     * @param  list<object>  $splits
     * @return array{code: ?string, alleles: ?array, assumed: bool, record: string, blocked: ?string}
     */
    private function sideGenotype(array $visuals, array $splits, string $sex, bool $sexLinked, ?object $prototype): array
    {
        if ($sexLinked && $sex === Bird::SEX_HEN && $visuals === [] && $splits !== []) {
            return [
                'code' => null,
                'alleles' => null,
                'assumed' => false,
                'record' => $splits[0]->name ?? 'Split',
                'blocked' => 'Calculation unavailable. A hen cannot be represented as a conventional hidden split for a Z-linked recessive gene under AGAPORA split-gene rules.',
            ];
        }

        if ($visuals !== []) {
            $expressed = $this->expressedVisualCode($visuals, $sex, $sexLinked);
            if ($expressed['blocked']) {
                return [
                    'code' => null,
                    'alleles' => null,
                    'assumed' => false,
                    'record' => $visuals[0]->name ?? 'Visual mutation',
                    'blocked' => $expressed['blocked'],
                ];
            }

            return [
                'code' => $expressed['code'],
                'alleles' => $expressed['code'] ? $this->parser->allelePair($expressed['code']) : null,
                'assumed' => false,
                'record' => count($visuals) === 1 ? (string) $visuals[0]->name : implode(' + ', array_map(fn ($item) => $item->name, $visuals)),
                'blocked' => null,
            ];
        }

        if ($splits !== []) {
            $gene = $splits[0];
            $code = $sexLinked
                ? $this->parser->sexSpecificSegment($gene->genetic_code, $sex)
                : ($this->parser->parseSegments($gene->genetic_code)[0]['segment'] ?? $gene->genetic_code);

            return [
                'code' => $code,
                'alleles' => $code ? $this->parser->allelePair($code) : null,
                'assumed' => false,
                'record' => (string) ($gene->name ?? 'Split'),
                'blocked' => null,
            ];
        }

        $code = $prototype ? $this->documentedNonCarrierCode($prototype, $sex) : null;
        $segment = $code ? $this->sexSegment($code, $sex, $sexLinked) : null;

        return [
            'code' => $segment,
            'alleles' => $segment ? $this->parser->allelePair($segment) : null,
            'assumed' => $segment !== null,
            'record' => 'Documented non-carrier (not selected)',
            'blocked' => null,
        ];
    }

    /**
     * @param  list<object>  $visuals
     * @return array{code: ?string, blocked: ?string}
     */
    private function expressedVisualCode(array $visuals, string $sex, bool $sexLinked): array
    {
        if (count($visuals) === 1 || $this->sameMutantToken($visuals)) {
            $chosen = $visuals[0];
            foreach ($visuals as $visual) {
                if (VisualMutationCatalog::dosageRank($visual->allele ?? null) === 2) {
                    $chosen = $visual;
                }
            }
            $code = $this->recordSegment($chosen->genetic_code ?? null, $sex, $sexLinked);

            return ['code' => $code, 'blocked' => null];
        }

        $symbols = [];
        foreach ($visuals as $visual) {
            $segment = $this->recordSegment($visual->genetic_code ?? null, $sex, $sexLinked);
            $pair = $segment ? $this->parser->allelePair($segment) : null;
            foreach ($pair ?? [] as $allele) {
                if ($allele !== '' && strcasecmp($allele, 'W') !== 0 && ! str_ends_with($allele, '+')) {
                    $symbols[$allele] = $allele;
                }
            }
        }

        if (count($symbols) >= 2 && $sexLinked && $sex === Bird::SEX_HEN) {
            return [
                'code' => null,
                'blocked' => 'Calculation unavailable. A hen has one Z chromosome, so two alleles of a sex-linked locus cannot both be visual.',
            ];
        }

        if (count($symbols) >= 2) {
            $symbols = array_values($symbols);

            return ['code' => $this->parser->pairLabel($symbols[0], $symbols[1]), 'blocked' => null];
        }

        $code = $this->recordSegment($visuals[0]->genetic_code ?? null, $sex, $sexLinked);

        return ['code' => $code, 'blocked' => null];
    }

    /**
     * @param  list<object>  $visuals
     */
    private function sameMutantToken(array $visuals): bool
    {
        $tokens = [];
        foreach ($visuals as $visual) {
            $token = GeneticLocus::tokenFromAlleleText($visual->allele ?? null);
            if ($token) {
                $tokens[$token] = true;
            }
        }

        return count($tokens) <= 1;
    }

    private function recordSegment(?string $code, string $sex, bool $sexLinked): ?string
    {
        if ($sexLinked) {
            return $this->parser->sexSpecificSegment($code, $sex);
        }

        return $this->parser->parseSegments($code)[0]['segment'] ?? $code;
    }

    private function sexSegment(string $code, string $sex, bool $sexLinked): string
    {
        if (! $sexLinked) {
            return $this->parser->parseSegments($code)[0]['segment'] ?? $code;
        }

        return $this->parser->sexSpecificSegment($code, $sex) ?? $code;
    }

    private function documentedNonCarrierCode(object $record, string $sex): ?string
    {
        $sexLinked = stripos((string) ($record->inheritance_type ?? ''), 'Sex-linked') !== false;
        $wild = null;

        if (isset($record->wild_type_allele) && is_string($record->wild_type_allele) && trim($record->wild_type_allele) !== '') {
            $wild = trim($record->wild_type_allele);
        }

        if ($wild === null) {
            $wild = $this->wildAlleleFromGeneticCode($record->genetic_code ?? null);
        }

        if ($wild === null) {
            $mutant = $record->mutant_allele ?? $record->genetic_symbol ?? null;
            if (is_string($mutant) && preg_match('/^[A-Za-z][A-Za-z0-9*_+.-]*$/', trim($mutant)) === 1) {
                $mutant = trim($mutant);
                if (! str_ends_with($mutant, '+') && strcasecmp($mutant, 'UNVERIFIED') !== 0) {
                    $wild = $mutant.'+';
                }
            }
        }

        if (! is_string($wild) || trim($wild) === '') {
            return null;
        }

        $wild = trim($wild);
        if ($sexLinked) {
            return $sex === Bird::SEX_HEN ? $wild.'/W' : $wild.'/'.$wild;
        }

        return $wild.'/'.$wild;
    }

    private function wildAlleleFromGeneticCode(?string $code): ?string
    {
        $segments = $this->parser->parseSegments($code);
        if ($segments === []) {
            return null;
        }

        foreach ($segments as $segment) {
            foreach ($segment['alleles'] as $allele) {
                if (strcasecmp($allele, 'W') === 0) {
                    continue;
                }
                if (str_ends_with($allele, '+')) {
                    return $allele;
                }
            }
        }

        foreach ($segments as $segment) {
            foreach ($segment['alleles'] as $allele) {
                if (strcasecmp($allele, 'W') === 0 || $allele === '') {
                    continue;
                }
                if (! str_ends_with($allele, '+') && preg_match('/^[A-Za-z][A-Za-z0-9*_+.-]*$/', $allele) === 1) {
                    return $allele.'+';
                }
            }
        }

        return null;
    }

    /**
     * @return list<object>
     */
    private function blSplits(Bird $bird): array
    {
        $splits = [];
        foreach ($bird->splitGenes ?? [] as $gene) {
            if (GeneticLocus::isBlLocus(SplitGeneCatalog::locusKey($gene))) {
                $splits[] = $gene;
            }
        }

        return $splits;
    }

    /**
     * @param  array{0: string, 1: string}  $alleles
     */
    private function isHomozygous(array $alleles): bool
    {
        return strcasecmp($alleles[0], $alleles[1]) === 0;
    }
}

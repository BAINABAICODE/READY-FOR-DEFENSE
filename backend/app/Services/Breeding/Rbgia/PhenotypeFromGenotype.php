<?php

namespace App\Services\Breeding\Rbgia;

use App\Models\BaseColor;
use App\Models\VisualMutation;
use App\Support\BaseColorCatalog;
use App\Support\VisualMutationCatalog;
use Illuminate\Support\Collection;

/**
 * Maps computed genotypes to documented phenotype text from AGAPORA datasets.
 * Never invents phenotype wording.
 *
 * Catalog rows are loaded once per species and genotype for this request.
 * Joint offspring reuse those rows instead of querying again for every chick.
 */
class PhenotypeFromGenotype
{
    /** @var array<string, BaseColor|null> */
    private array $baseColors = [];

    /** @var array<string, VisualMutation|null> */
    private array $visualMutations = [];

    /** @var array<int, Collection<int, VisualMutation>> */
    private array $visualMutationsBySpecies = [];
    /**
     * @param  array<string, mixed>  $locusRow
     * @return array{phenotype: ?string, base_color: ?string, visual_mutations: list<string>, split_hidden: list<string>, note: ?string, dark_factor: ?string}
     */
    public function forLocus(array $locusRow, ?int $speciesId = null): array
    {
        $category = $locusRow['category'] ?? null;
        $name = $locusRow['name'] ?? null;
        $genotype = $locusRow['genotype'] ?? null;
        $expression = $locusRow['expression'] ?? null;
        $locusKey = $locusRow['locus_key'] ?? null;

        if ($category === 'chromosomal_sex' || $locusKey === 'chromosomal_sex') {
            return [
                'phenotype' => null,
                'base_color' => null,
                'visual_mutations' => [],
                'split_hidden' => [],
                'dark_factor' => null,
                'note' => 'Sex chromosome outcome; not a phenotype locus.',
            ];
        }

        if ($locusKey === 'dark_factor') {
            return $this->darkFactorPhenotype($expression);
        }

        if ($category === 'base_color' || $locusKey === 'ground_color') {
            return $this->baseColorPhenotype($name, $genotype, $speciesId, $expression, $locusKey);
        }

        if ($category === 'visual_mutation') {
            return $this->visualPhenotype($name, $genotype, $speciesId, $expression);
        }

        if ($category === 'split_gene') {
            return [
                'phenotype' => $expression === 'carrier_split'
                    ? 'Carrier / split state for '.($name ?? 'gene').' (not necessarily visual)'
                    : ($this->isVisualExpression($expression)
                        ? ($name.' visual expression when homozygous/hemizygous')
                        : null),
                'base_color' => null,
                'visual_mutations' => $this->isVisualExpression($expression)
                    ? array_values(array_filter([$name]))
                    : [],
                'split_hidden' => $expression === 'carrier_split' ? array_values(array_filter([$name])) : [],
                'dark_factor' => null,
                'note' => 'Split/hidden status derived from calculated genotype expression class.',
            ];
        }

        return [
            'phenotype' => null,
            'base_color' => null,
            'visual_mutations' => [],
            'split_hidden' => [],
            'dark_factor' => null,
            'note' => 'Not specified in stored phenotype record.',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $loci
     * @return array{phenotype: string, base_color: ?string, visual_mutations: list<string>, split_hidden: list<string>, dark_factor: ?string, base_color_genotype: ?string}
     */
    public function forJoint(array $loci, ?int $speciesId = null): array
    {
        $groundGenotype = null;
        $darkGenotype = null;
        $groundExpression = null;
        $groundLocus = null;
        $visual = [];
        $split = [];
        $parts = [];
        $darkFactor = null;

        foreach ($loci as $locus) {
            $key = $locus['locus_key'] ?? null;
            $category = $locus['category'] ?? null;

            if ($category === 'chromosomal_sex' || $key === 'chromosomal_sex') {
                continue;
            }

            if ($key === 'ground_color') {
                $groundGenotype = $locus['genotype'] ?? null;
                $groundExpression = $locus['expression'] ?? null;
                $groundLocus = $locus;
            }

            if ($key === 'dark_factor') {
                $darkGenotype = $locus['genotype'] ?? null;
            }

            $mapped = $this->forLocus($locus, $speciesId);

            if ($key === 'dark_factor') {
                $darkFactor = $mapped['dark_factor'] ?? null;
                if (! empty($mapped['phenotype'])) {
                    $parts[] = $mapped['phenotype'];
                }
                continue;
            }

            if ($key === 'ground_color') {
                // Combined ground+dark lookup happens after the loop.
                continue;
            }

            foreach ($mapped['visual_mutations'] as $item) {
                $visual[] = $item;
            }
            foreach ($mapped['split_hidden'] as $item) {
                $split[] = $item;
            }
            if (! empty($mapped['phenotype'])) {
                $parts[] = $mapped['phenotype'];
            }
        }

        $combined = $this->resolveCombinedBaseColor($groundGenotype, $darkGenotype, $speciesId);
        $labeled = $this->labeledGround($groundLocus, $groundExpression);
        if (empty($combined['base_color']) && ! empty($labeled['base_color'])) {
            $combined['base_color'] = $labeled['base_color'];
            $combined['phenotype'] = $labeled['phenotype'] ?? $labeled['base_color'];
        }
        foreach ($labeled['splits'] as $name) {
            $split[] = $name;
        }
        if (! empty($combined['phenotype'])) {
            array_unshift($parts, $combined['phenotype']);
        } elseif ($groundExpression === 'carrier_split') {
            array_unshift($parts, 'Carrier / split. The visual mutant color is not expressed.');
        }

        $visual = array_values(array_unique($visual));
        $split = array_values(array_unique($split));
        $phenotype = $parts !== []
            ? implode(' · ', array_unique($parts))
            : 'Not specified in stored phenotype record.';

        return [
            'phenotype' => $phenotype,
            'base_color' => $combined['base_color'],
            'visual_mutations' => $visual,
            'split_hidden' => $split,
            'dark_factor' => $darkFactor,
            'base_color_genotype' => $combined['genotype'],
        ];
    }

    /**
     * @return array{base_color: ?string, phenotype: ?string, genotype: ?string}
     */
    private function resolveCombinedBaseColor(?string $groundGenotype, ?string $darkGenotype, ?int $speciesId): array
    {
        if (! $groundGenotype) {
            return ['base_color' => null, 'phenotype' => null, 'genotype' => null];
        }

        $fullCode = $darkGenotype ? $groundGenotype.'|'.$darkGenotype : $groundGenotype;
        $record = $speciesId
            ? $this->rememberBaseColor(
                'combined:'.$speciesId.'|'.$fullCode,
                function () use ($speciesId, $fullCode, $groundGenotype, $darkGenotype) {
                    $record = BaseColor::query()
                        ->where('lovebird_species_id', $speciesId)
                        ->where(function ($query) use ($fullCode, $groundGenotype, $darkGenotype) {
                            $query->where('genetic_code', $fullCode);
                            if ($darkGenotype) {
                                $query->orWhere('genetic_code', $groundGenotype.'|'.$darkGenotype);
                            }
                            $query->orWhere('genetic_code', 'like', $groundGenotype.'|%');
                            $query->orWhere('genetic_code', $groundGenotype);
                        })
                        ->orderByRaw(
                            'CASE WHEN genetic_code = ? THEN 0 WHEN genetic_code LIKE ? THEN 1 ELSE 2 END',
                            [$fullCode, $groundGenotype.'|'.($darkGenotype ?: '').'%']
                        )
                        ->first();

                    // Prefer exact ground|dark match when multiple rows share the ground segment.
                    if ($darkGenotype) {
                        $exact = BaseColor::query()
                            ->where('lovebird_species_id', $speciesId)
                            ->where('genetic_code', $fullCode)
                            ->first();
                        if ($exact) {
                            $record = $exact;
                        }
                    }

                    return $record;
                },
            )
            : null;

        $payload = BaseColorCatalog::geneticPayload($record);

        return [
            'base_color' => $payload['name'] ?? null,
            'phenotype' => $payload['phenotype'] ?? ($payload['name'] ?? null),
            'genotype' => $fullCode,
        ];
    }

    /**
     * Stored allele names for a ground-color genotype that has no heterozygous base-color row.
     * A recessive heterozygote keeps the wild-type color and hides the mutant color.
     *
     * @param  array<string, mixed>|null  $locus
     * @return array{base_color: ?string, phenotype: ?string, splits: list<string>}
     */
    private function labeledGround(?array $locus, ?string $expression): array
    {
        $empty = ['base_color' => null, 'phenotype' => null, 'splits' => []];
        $labels = is_array($locus['allele_phenotypes'] ?? null) ? $locus['allele_phenotypes'] : [];
        $genotype = $locus['genotype'] ?? null;
        if ($labels === [] || ! is_string($genotype) || $genotype === '') {
            return $empty;
        }

        $wild = [];
        $mutants = [];
        foreach (array_map('trim', explode('/', $genotype)) as $allele) {
            if ($allele === '' || strcasecmp($allele, 'W') === 0) {
                continue;
            }
            if (str_ends_with($allele, '+')) {
                $wild[] = $allele;
            } else {
                $mutants[] = $allele;
            }
        }

        $label = function (string $allele) use ($labels): ?string {
            $name = $labels[$allele] ?? null;

            return is_string($name) && $name !== '' ? $name : null;
        };

        if ($expression === 'carrier_split') {
            $visible = $wild !== [] ? $label($wild[0]) : null;
            $splits = [];
            foreach ($mutants as $mutant) {
                $name = $label($mutant);
                if ($name) {
                    $splits[] = $name;
                }
            }

            return ['base_color' => $visible, 'phenotype' => $visible, 'splits' => $splits];
        }

        if ($expression === 'non_carrier' && $wild !== []) {
            $visible = $label($wild[0]);

            return ['base_color' => $visible, 'phenotype' => $visible, 'splits' => []];
        }

        if ($this->isVisualExpression($expression) && $mutants !== []) {
            $names = [];
            foreach (array_unique($mutants) as $mutant) {
                $name = $label($mutant);
                if ($name) {
                    $names[] = $name;
                }
            }
            $visible = $names === [] ? null : implode(' + ', $names);

            return ['base_color' => $visible, 'phenotype' => $visible, 'splits' => []];
        }

        return $empty;
    }

    /**
     * @return array{phenotype: ?string, base_color: ?string, visual_mutations: list<string>, split_hidden: list<string>, note: ?string, dark_factor: ?string}
     */
    private function darkFactorPhenotype(?string $expression): array
    {
        $label = match ($expression) {
            'visual_double' => 'Double dark factor (DF)',
            'visual_single_factor' => 'Single dark factor (SF)',
            'non_carrier' => 'No dark factor',
            default => null,
        };
        $code = match ($expression) {
            'visual_double' => 'DF',
            'visual_single_factor' => 'SF',
            'non_carrier' => 'none',
            default => null,
        };

        return [
            'phenotype' => $label,
            'base_color' => null,
            'visual_mutations' => [],
            'split_hidden' => [],
            'dark_factor' => $code,
            'note' => $label
                ? 'Dark-factor expression class from calculated D alleles; does not replace ground-color phenotype.'
                : 'Not specified in stored phenotype record.',
        ];
    }

    /**
     * @return array{phenotype: ?string, base_color: ?string, visual_mutations: list<string>, split_hidden: list<string>, note: ?string, dark_factor: ?string}
     */
    private function baseColorPhenotype(?string $name, ?string $genotype, ?int $speciesId, ?string $expression, ?string $locusKey): array
    {
        $record = $this->rememberBaseColor(
            'locus:'.($speciesId ?: 0).'|'.($genotype ?? '').'|'.($name ?? ''),
            function () use ($speciesId, $genotype, $name) {
                $record = null;
                if ($speciesId && $genotype) {
                    $record = BaseColor::query()
                        ->where('lovebird_species_id', $speciesId)
                        ->where(function ($query) use ($genotype) {
                            $query->where('genetic_code', $genotype)
                                ->orWhere('genetic_code', 'like', $genotype.'|%');
                        })
                        ->orderByRaw('CASE WHEN genetic_code = ? THEN 0 ELSE 1 END', [$genotype])
                        ->first();
                }

                if (! $record && $name) {
                    $record = BaseColor::query()
                        ->when($speciesId, fn ($query) => $query->where('lovebird_species_id', $speciesId))
                        ->where('name', $name)
                        ->first();
                }

                return $record;
            },
        );

        $payload = BaseColorCatalog::geneticPayload($record);

        if (! $record && $expression === 'carrier_split') {
            return [
                'phenotype' => 'Carrier / split. The visual mutant color is not expressed.',
                'base_color' => null,
                'visual_mutations' => [],
                'split_hidden' => [],
                'dark_factor' => null,
                'note' => 'No stored base-color row matches this heterozygous genotype.',
            ];
        }

        return [
            'phenotype' => $payload['phenotype'] ?? ($name ? $name.' expression' : null),
            'base_color' => $locusKey === 'dark_factor' ? null : ($payload['name'] ?? $name),
            'visual_mutations' => [],
            'split_hidden' => [],
            'dark_factor' => null,
            'note' => $payload ? null : 'Not specified in stored phenotype record.',
        ];
    }

    /**
     * @return array{phenotype: ?string, base_color: ?string, visual_mutations: list<string>, split_hidden: list<string>, note: ?string, dark_factor: ?string}
     */
    private function visualPhenotype(?string $name, ?string $genotype, ?int $speciesId, ?string $expression): array
    {
        if (in_array($expression, ['non_carrier', 'hemizygous_wild'], true)) {
            return [
                'phenotype' => 'Non-carrier for '.($name ?? 'mutation'),
                'base_color' => null,
                'visual_mutations' => [],
                'split_hidden' => [],
                'dark_factor' => null,
                'note' => 'Wild-type genotype at this locus. The mutation phenotype is not expressed.',
            ];
        }

        $record = $this->rememberVisualMutation(
            ($speciesId ?: 0).'|'.($name ?? ''),
            fn () => VisualMutation::query()
                ->when($speciesId, fn ($query) => $query->where('lovebird_species_id', $speciesId))
                ->when($name, fn ($query) => $query->where('name', $name))
                ->first(),
        );

        $payloadSpeciesId = (int) ($record?->lovebird_species_id ?? 0);
        $payload = VisualMutationCatalog::geneticPayload(
            $record,
            $payloadSpeciesId > 0 ? $this->visualMutationsForSpecies($payloadSpeciesId) : null,
        );
        $isVisual = $this->isVisualExpression($expression);
        $isCarrier = $expression === 'carrier_split';

        return [
            'phenotype' => $isVisual
                ? ($payload['phenotype'] ?? $name)
                : ($isCarrier ? 'Carrier / split for '.($name ?? 'mutation').' (hidden unless documented otherwise)' : ($payload['phenotype'] ?? null)),
            'base_color' => null,
            'visual_mutations' => $isVisual ? array_values(array_filter([$payload['name'] ?? $name])) : [],
            'split_hidden' => $isCarrier ? array_values(array_filter([$payload['name'] ?? $name])) : [],
            'dark_factor' => null,
            'note' => $payload ? null : 'Not specified in stored phenotype record.',
        ];
    }

    /**
     * @param  callable(): (?BaseColor)  $load
     */
    private function rememberBaseColor(string $key, callable $load): ?BaseColor
    {
        if (array_key_exists($key, $this->baseColors)) {
            return $this->baseColors[$key];
        }

        return $this->baseColors[$key] = $load();
    }

    /**
     * @param  callable(): (?VisualMutation)  $load
     */
    private function rememberVisualMutation(string $key, callable $load): ?VisualMutation
    {
        if (array_key_exists($key, $this->visualMutations)) {
            return $this->visualMutations[$key];
        }

        return $this->visualMutations[$key] = $load();
    }

    /**
     * @return Collection<int, VisualMutation>
     */
    private function visualMutationsForSpecies(int $speciesId): Collection
    {
        if (! array_key_exists($speciesId, $this->visualMutationsBySpecies)) {
            $this->visualMutationsBySpecies[$speciesId] = VisualMutationCatalog::forSpecies($speciesId);
        }

        return $this->visualMutationsBySpecies[$speciesId];
    }

    private function isVisualExpression(?string $expression): bool
    {
        return in_array($expression, [
            'visual',
            'visual_homozygous',
            'visual_heterozygous',
            'visual_hemizygous',
            'visual_single_factor',
            'visual_double',
            'visual_compound',
        ], true);
    }
}

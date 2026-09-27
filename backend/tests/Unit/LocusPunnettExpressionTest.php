<?php

namespace Tests\Unit;

use App\Services\Breeding\Rbgia\GeneticCodeParser;
use App\Services\Breeding\Rbgia\LocusPunnett;
use App\Services\Breeding\Rbgia\PhenotypeFromGenotype;
use PHPUnit\Framework\TestCase;

/**
 * Expression classes for the rule book in InheritanceModeSeeder.
 * Genotype probabilities stay Mendelian; these cases lock the phenotype class.
 */
class LocusPunnettExpressionTest extends TestCase
{
    private LocusPunnett $punnett;

    private PhenotypeFromGenotype $phenotypes;

    protected function setUp(): void
    {
        $this->punnett = new LocusPunnett(new GeneticCodeParser);
        $this->phenotypes = new PhenotypeFromGenotype;
    }

    public function test_wild_type_cross_is_non_carrier(): void
    {
        $rows = $this->cross('bl+', 'bl+', 'bl+', 'bl+', false, 'Wild type');

        $this->assertEquals(['bl+/bl+' => 'non_carrier'], $this->classes($rows));
        $this->assertEqualsWithDelta(1.0, $rows[0]['probability'], 0.0001);
    }

    public function test_autosomal_recessive_heterozygote_is_a_split_and_homozygote_is_visual(): void
    {
        $split = $this->cross('blaq', 'blaq', 'bl+', 'bl+', false, 'Autosomal recessive');
        $this->assertEquals(['bl+/blaq' => 'carrier_split'], $this->classes($split));

        $visual = $this->cross('blaq', 'blaq', 'blaq', 'blaq', false, 'Autosomal recessive');
        $this->assertEquals(['blaq/blaq' => 'visual'], $this->classes($visual));
    }

    public function test_incomplete_and_intermediate_dominant_use_dosage_classes(): void
    {
        $singleByNone = $this->cross('D+', 'D', 'D+', 'D+', false, 'Intermediate dominant');
        $this->assertEquals([
            'D+/D+' => 'non_carrier',
            'D+/D' => 'visual_single_factor',
        ], $this->classes($singleByNone));
        $this->assertEqualsWithDelta(0.5, $this->probability($singleByNone, 'D+/D'), 0.0001);

        $singleBySingle = $this->cross('D+', 'D', 'D+', 'D', false, 'Incomplete dominant');
        $this->assertEquals([
            'D+/D+' => 'non_carrier',
            'D+/D' => 'visual_single_factor',
            'D/D' => 'visual_double',
        ], $this->classes($singleBySingle));
        $this->assertEqualsWithDelta(0.25, $this->probability($singleBySingle, 'D+/D+'), 0.0001);
        $this->assertEqualsWithDelta(0.5, $this->probability($singleBySingle, 'D+/D'), 0.0001);
        $this->assertEqualsWithDelta(0.25, $this->probability($singleBySingle, 'D/D'), 0.0001);
    }

    public function test_dark_factor_dosage_maps_to_single_and_double_factor_labels(): void
    {
        $this->assertSame('Single dark factor (SF)', $this->darkFactorLabel('visual_single_factor'));
        $this->assertSame('SF', $this->darkFactorCode('visual_single_factor'));
        $this->assertSame('Double dark factor (DF)', $this->darkFactorLabel('visual_double'));
        $this->assertSame('DF', $this->darkFactorCode('visual_double'));
        $this->assertSame('No dark factor', $this->darkFactorLabel('non_carrier'));
        $this->assertSame('none', $this->darkFactorCode('non_carrier'));
    }

    public function test_complete_dominant_stays_heterozygous_or_homozygous(): void
    {
        $hetero = $this->cross('Pi+', 'Pi', 'Pi+', 'Pi+', false, 'Autosomal dominant');
        $this->assertEquals([
            'Pi+/Pi+' => 'non_carrier',
            'Pi+/Pi' => 'visual_heterozygous',
        ], $this->classes($hetero));
        $this->assertEqualsWithDelta(0.5, $this->probability($hetero, 'Pi+/Pi'), 0.0001);

        $homo = $this->cross('Pi', 'Pi', 'Pi+', 'Pi+', false, 'Autosomal dominant');
        $this->assertEquals(['Pi+/Pi' => 'visual_heterozygous'], $this->classes($homo));

        $bothHomo = $this->cross('Pi', 'Pi', 'Pi', 'Pi', false, 'Autosomal dominant');
        $this->assertEquals(['Pi/Pi' => 'visual_homozygous'], $this->classes($bothHomo));
    }

    public function test_allelic_compound_of_two_recessive_alleles_is_visual(): void
    {
        $rows = $this->cross('blaq', 'blaq', 'bltq', 'bltq', false, 'Allelic compound');

        $this->assertEquals(['blaq/bltq' => 'visual_compound'], $this->classes($rows));
        $this->assertEqualsWithDelta(1.0, $rows[0]['probability'], 0.0001);
    }

    public function test_allelic_compound_parent_crossed_to_wild_type_produces_splits(): void
    {
        $rows = $this->cross('blaq', 'bltq', 'bl+', 'bl+', false, 'Allelic compound');

        $this->assertEquals([
            'bl+/blaq' => 'carrier_split',
            'bl+/bltq' => 'carrier_split',
        ], $this->classes($rows));
        $this->assertEqualsWithDelta(0.5, $this->probability($rows, 'bl+/blaq'), 0.0001);
        $this->assertEqualsWithDelta(0.5, $this->probability($rows, 'bl+/bltq'), 0.0001);
    }

    public function test_sex_linked_recessive_split_cock_uses_zw_expression(): void
    {
        $rows = $this->cross('ino+', 'ino', 'ino+', 'W', true, 'Sex-linked recessive');

        $this->assertEquals([
            'ino+/ino+' => 'non_carrier',
            'ino+/ino' => 'carrier_split',
            'ino+/W' => 'hemizygous_wild',
            'ino/W' => 'visual_hemizygous',
        ], $this->classes($rows));
        foreach (['ino+/ino+', 'ino+/ino', 'ino+/W', 'ino/W'] as $genotype) {
            $this->assertEqualsWithDelta(0.25, $this->probability($rows, $genotype), 0.0001);
        }
    }

    public function test_sex_linked_incomplete_dominant_single_factor_cock_is_visual(): void
    {
        $rows = $this->cross('Grw+', 'Grw', 'Grw+', 'W', true, 'Sex-linked incomplete dominant');

        $this->assertEquals([
            'Grw+/Grw+' => 'non_carrier',
            'Grw+/Grw' => 'visual_single_factor',
            'Grw+/W' => 'hemizygous_wild',
            'Grw/W' => 'visual_hemizygous',
        ], $this->classes($rows));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cross(
        string $cockA,
        string $cockB,
        string $henA,
        string $henB,
        bool $sexLinked,
        string $inheritanceType,
    ): array {
        return $this->punnett->cross([$cockA, $cockB], [$henA, $henB], $sexLinked, $inheritanceType);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, string>
     */
    private function classes(array $rows): array
    {
        $classes = [];
        foreach ($rows as $row) {
            $classes[$row['genotype']] = $row['expression'];
        }
        ksort($classes);

        return $classes;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function probability(array $rows, string $genotype): float
    {
        foreach ($rows as $row) {
            if ($row['genotype'] === $genotype) {
                return (float) $row['probability'];
            }
        }

        $this->fail("Missing genotype {$genotype}");
    }

    private function darkFactorLabel(string $expression): ?string
    {
        return $this->phenotypes->forLocus([
            'locus_key' => 'dark_factor',
            'genotype' => 'D+/D',
            'expression' => $expression,
        ])['phenotype'];
    }

    private function darkFactorCode(string $expression): ?string
    {
        return $this->phenotypes->forLocus([
            'locus_key' => 'dark_factor',
            'genotype' => 'D+/D',
            'expression' => $expression,
        ])['dark_factor'];
    }
}

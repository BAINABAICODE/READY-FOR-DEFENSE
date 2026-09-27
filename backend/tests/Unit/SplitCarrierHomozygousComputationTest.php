<?php

namespace Tests\Unit;

use App\Services\Breeding\Rbgia\GeneticCodeParser;
use App\Services\Breeding\Rbgia\LocusPunnett;
use App\Services\Breeding\Rbgia\SplitCarrierHomozygousComputation;
use PHPUnit\Framework\TestCase;

class SplitCarrierHomozygousComputationTest extends TestCase
{
    private LocusPunnett $punnett;

    private SplitCarrierHomozygousComputation $computation;

    protected function setUp(): void
    {
        $this->punnett = new LocusPunnett(new GeneticCodeParser);
        $this->computation = new SplitCarrierHomozygousComputation;
    }

    public function test_two_autosomal_split_carriers_produce_one_quarter_visual_homozygous(): void
    {
        $summary = $this->summarize(['dil+', 'dil'], ['dil+', 'dil'], false, 'Autosomal recessive');

        $this->assertNotNull($summary);
        $this->assertTrue($summary['cock_heterozygous_carrier']);
        $this->assertTrue($summary['hen_heterozygous_carrier']);
        $this->assertEqualsWithDelta(0.25, $summary['probability'], 0.0001);
        $this->assertSame('1/4', $summary['fraction']);
        $this->assertSame('1/2 × 1/2 = 1/4', $summary['formula']);
        $this->assertSame(['dil/dil'], $summary['genotypes']);
    }

    public function test_split_carrier_crossed_to_visual_homozygote_is_one_half(): void
    {
        $summary = $this->summarize(['dil', 'dil'], ['dil+', 'dil'], false, 'Autosomal recessive');

        $this->assertEqualsWithDelta(0.5, $summary['probability'], 0.0001);
        $this->assertSame('1 × 1/2 = 1/2', $summary['formula']);
        $this->assertFalse($summary['cock_heterozygous_carrier']);
        $this->assertTrue($summary['hen_heterozygous_carrier']);
    }

    public function test_split_carrier_crossed_to_wild_type_produces_no_visual_homozygote(): void
    {
        $summary = $this->summarize(['dil+', 'dil'], ['dil+', 'dil+'], false, 'Autosomal recessive');

        $this->assertEqualsWithDelta(0.0, $summary['probability'], 0.0001);
        $this->assertSame('1/2 × 0 = 0', $summary['formula']);
    }

    public function test_a_pair_with_no_split_carrier_is_left_out(): void
    {
        $this->assertNull($this->summarize(['dil', 'dil'], ['dil', 'dil'], false, 'Autosomal recessive'));
        $this->assertNull($this->summarize(['Ed+', 'Ed'], ['Ed+', 'Ed'], false, 'Incomplete dominant'));
    }

    public function test_sex_linked_split_cock_needs_the_hens_mutant_z_for_a_homozygous_son(): void
    {
        $normalHen = $this->summarize(['op+', 'op'], ['op+', 'W'], true, 'Sex-linked recessive');
        $this->assertEqualsWithDelta(0.0, $normalHen['probability'], 0.0001);
        $this->assertEqualsWithDelta(0.25, $normalHen['hemizygous_visual_probability'], 0.0001);
        $this->assertSame('1/2 × 1/2 × 0 = 0', $normalHen['formula']);

        $visualHen = $this->summarize(['op+', 'op'], ['op', 'W'], true, 'Sex-linked recessive');
        $this->assertEqualsWithDelta(0.25, $visualHen['probability'], 0.0001);
        $this->assertEqualsWithDelta(0.25, $visualHen['hemizygous_visual_probability'], 0.0001);
        $this->assertSame('1/2 × 1/2 × 1 = 1/4', $visualHen['formula']);
    }

    /**
     * @param  array{0: string, 1: string}  $cock
     * @param  array{0: string, 1: string}  $hen
     * @return array<string, mixed>|null
     */
    private function summarize(array $cock, array $hen, bool $sexLinked, string $inheritanceType): ?array
    {
        $results = $this->punnett->cross($cock, $hen, $sexLinked, $inheritanceType);

        return $this->computation->summarize($cock, $hen, $sexLinked, $inheritanceType, $results);
    }
}

<?php

namespace Tests\Unit;

use App\Models\BaseColor;
use App\Models\SplitGene;
use App\Models\VisualMutation;
use App\Support\BaseColorCatalog;
use App\Support\GeneticLocus;
use App\Support\SplitGeneCatalog;
use App\Support\VisualMutationCatalog;
use Tests\TestCase;

class SplitGeneCombinationTest extends TestCase
{
    public function test_green_can_hide_one_blue_gene_and_mutant_blue_cannot(): void
    {
        $this->assertTrue(GeneticLocus::canHideBlSplit('bl+/bl+|D+/D+'));
        $this->assertTrue(GeneticLocus::canHideBlSplit('bl+/bl+|D+/D'));
        $this->assertFalse(GeneticLocus::canHideBlSplit('bl1/bl1|D+/D+'));
        $this->assertFalse(GeneticLocus::canHideBlSplit('bl1/bl2|D+/D+'));
        $this->assertFalse(GeneticLocus::canHideBlSplit('blaq/bltq|D+/D+'));
    }

    public function test_different_loci_can_be_hidden_together(): void
    {
        $blue1 = $this->gene(1, 'Split Blue1', 'Base-color locus bl', 'bl1', true, true, 'Blue1');
        $dilute = $this->gene(2, 'Split Dilute', 'Eumelanin distribution', 'dil', true, true, 'Dilute');
        $opaline = $this->gene(3, 'Split Opaline', 'Z-linked distribution', 'op', true, false, 'Opaline');
        $green = $this->color('Green', 'bl+/bl+|D+/D+', 'Green');

        $this->assertNull(SplitGeneCatalog::selectionMessage(collect([$blue1, $dilute, $opaline]), 'cock', $green));
        $this->assertNull(SplitGeneCatalog::selectionMessage(collect([$blue1]), 'hen', $green));
    }

    public function test_one_locus_can_hide_only_one_allele(): void
    {
        $nsl = $this->gene(1, 'Split NSL Ino', 'a locus', 'a', true, true, 'NSL Ino, called lutino on green');
        $pastel = $this->gene(2, 'Split Pastel', 'a locus', 'a*pa', true, true, 'Pastel');

        $this->assertStringContainsString(
            'only one hidden allele',
            (string) SplitGeneCatalog::selectionMessage(collect([$nsl, $pastel])),
        );
    }

    public function test_a_visual_mutation_cannot_also_be_a_split(): void
    {
        $splitOpaline = $this->gene(1, 'Split Opaline', 'Z-linked distribution', 'op', true, false, 'Opaline');
        $splitPastel = $this->gene(2, 'Split Pastel', 'a locus', 'a*pa', true, true, 'Pastel');
        $visualOpaline = $this->mutation('Opaline', 'Sex-linked', 'op. Distribution mutation.');
        $visualNsl = $this->mutation('NSL Ino', 'a locus', 'a. Autosomal.');

        $this->assertStringContainsString(
            'already visual',
            (string) SplitGeneCatalog::selectionMessage(collect([$splitOpaline]), 'cock', null, collect([$visualOpaline])),
        );
        $this->assertStringContainsString(
            'already visual',
            (string) SplitGeneCatalog::selectionMessage(collect([$splitPastel]), 'cock', null, collect([$visualNsl])),
        );
        $this->assertStringContainsString(
            'stays hidden',
            (string) VisualMutationCatalog::splitConflictMessage(collect([$visualOpaline]), collect([$splitOpaline])),
        );
    }

    public function test_blue_series_color_cannot_hide_a_blue_gene(): void
    {
        $splitBlue = $this->gene(1, 'Split Blue1', 'Base-color locus bl', 'bl1', true, true, 'Blue1');
        $blue = $this->color('Blue1', 'bl1/bl1|D+/D+', 'Blue');
        $green = $this->color('Green', 'bl+/bl+|D+/D+', 'Green');

        $this->assertNull(SplitGeneCatalog::selectionMessage(collect([$splitBlue]), 'cock', $green));
        $this->assertStringContainsString(
            'green-series',
            (string) SplitGeneCatalog::selectionMessage(collect([$splitBlue]), 'cock', $blue),
        );
        $this->assertStringContainsString(
            'green-series',
            (string) BaseColorCatalog::incompatibleMessage($blue, collect([$splitBlue])),
        );
        $this->assertNull(BaseColorCatalog::incompatibleMessage($green, collect([$splitBlue])));
    }

    public function test_a_hen_cannot_hide_a_sex_linked_gene(): void
    {
        $opaline = $this->gene(1, 'Split Opaline', 'Z-linked distribution', 'op', true, false, 'Opaline');

        $this->assertNull(SplitGeneCatalog::selectionMessage(collect([$opaline]), 'cock'));
        $this->assertStringContainsString(
            'hen',
            (string) SplitGeneCatalog::selectionMessage(collect([$opaline]), 'hen'),
        );
    }

    private function gene(
        int $id,
        string $name,
        string $category,
        string $mutant,
        bool $cock,
        bool $hen,
        string $whenVisual,
    ): SplitGene {
        $gene = new SplitGene;
        $gene->id = $id;
        $gene->forceFill([
            'name' => $name,
            'genetic_category' => $category,
            'mutant_allele' => $mutant,
            'genetic_symbol' => $mutant,
            'cock_can_split' => $cock,
            'hen_can_split' => $hen,
            'phenotype_when_visual' => $whenVisual,
        ]);

        return $gene;
    }

    private function mutation(string $name, string $series, string $allele): VisualMutation
    {
        $mutation = new VisualMutation;
        $mutation->forceFill([
            'name' => $name,
            'series' => $series,
            'allele' => $allele,
        ]);

        return $mutation;
    }

    private function color(string $name, string $code, string $series): BaseColor
    {
        $color = new BaseColor;
        $color->forceFill([
            'name' => $name,
            'genetic_code' => $code,
            'series' => $series,
        ]);

        return $color;
    }
}

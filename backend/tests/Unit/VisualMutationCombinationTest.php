<?php

namespace Tests\Unit;

use App\Models\VisualMutation;
use App\Support\VisualMutationCatalog;
use Tests\TestCase;

class VisualMutationCombinationTest extends TestCase
{
    public function test_only_named_pairs_are_linked(): void
    {
        $mutations = collect([
            $this->mutation(1, 'NSL Ino', 'a locus', 'a. Autosomal.', 'Near-total eumelanin loss.'),
            $this->mutation(2, 'Dark Eyed Clear', 'a locus', 'a*dec. Allele of a.', 'Decino is NSL Ino plus this allele, a combination, not a separate mutation.'),
            $this->mutation(3, 'Pastel', 'a locus', 'a*pa. Allele of a.', 'Pastelino is this allele with NSL Ino, not a separate mutation.'),
            $this->mutation(4, 'Opaline', 'Sex-linked', 'op.', 'Opaline NSL ino is a combination, not a third allele.', 'Sex-linked recessive'),
            $this->mutation(5, 'Cinnamon', 'Sex-linked', 'cin.', 'Eumelanin is brown rather than black.', 'Sex-linked recessive'),
        ]);

        $combinations = VisualMutationCatalog::combinationNamesById($mutations);

        $this->assertEqualsCanonicalizing(['Dark Eyed Clear', 'Pastel', 'Opaline'], $combinations[1]);
        $this->assertEqualsCanonicalizing(['NSL Ino'], $combinations[2]);
        $this->assertEqualsCanonicalizing(['NSL Ino'], $combinations[4]);
        $this->assertSame([], $combinations[5]);
    }

    public function test_different_loci_can_be_combined(): void
    {
        $nsl = $this->mutation(1, 'NSL Ino', 'a locus', 'a. Autosomal.', 'Near-total eumelanin loss.');
        $dec = $this->mutation(2, 'Dark Eyed Clear', 'a locus', 'a*dec. Allele of a.', 'Decino is NSL Ino plus this allele, a combination, not a separate mutation.');
        $opaline = $this->mutation(4, 'Opaline', 'Sex-linked', 'op.', 'Opaline NSL ino is a combination, not a third allele.', 'Sex-linked recessive');
        $cinnamon = $this->mutation(5, 'Cinnamon', 'Sex-linked', 'cin.', 'Eumelanin is brown rather than black.', 'Sex-linked recessive');
        $dilute = $this->mutation(6, 'Dilute', 'Eumelanin distribution', 'dil. Autosomal.', 'Eumelanin is diluted.');

        $this->assertNull(VisualMutationCatalog::selectionMessage(collect([$opaline, $dilute])));
        $this->assertNull(VisualMutationCatalog::selectionMessage(collect([$cinnamon, $opaline])));
        $this->assertNull(VisualMutationCatalog::selectionMessage(collect([$nsl, $opaline])));
        $this->assertNull(VisualMutationCatalog::selectionMessage(collect([$nsl, $dec])));
        $this->assertNull(VisualMutationCatalog::selectionMessage(collect([$nsl, $dec, $opaline, $dilute])));
    }

    public function test_a_locus_accepts_a_compound_and_rejects_a_third_allele(): void
    {
        $nsl = $this->mutation(1, 'NSL Ino', 'a locus', 'a. Autosomal.', 'Near-total eumelanin loss.');
        $dec = $this->mutation(2, 'Dark Eyed Clear', 'a locus', 'a*dec. Allele of a.', 'Decino is a combination.');
        $pastel = $this->mutation(3, 'Pastel', 'a locus', 'a*pa. Allele of a.', 'Pastelino is a combination.');

        $this->assertNull(VisualMutationCatalog::selectionMessage(collect([$nsl, $pastel])));
        $this->assertStringContainsString(
            'only two alleles of this locus',
            (string) VisualMutationCatalog::selectionMessage(collect([$nsl, $dec, $pastel])),
        );
    }

    public function test_single_and_double_factor_of_one_mutation_cannot_both_be_selected(): void
    {
        $violet = $this->mutation(1, 'Violet', 'Incomplete dominant', 'one V. Feather structure.', 'One V gives a violet wash.');
        $doubleViolet = $this->mutation(2, 'Double Violet', 'Incomplete dominant', 'two V. Same allele.', 'Two V is stronger.');

        $this->assertStringContainsString(
            'single-factor or the double-factor',
            (string) VisualMutationCatalog::selectionMessage(collect([$violet, $doubleViolet])),
        );
    }

    public function test_a_hen_has_only_one_copy_of_a_sex_linked_locus(): void
    {
        $ino = $this->mutation(1, 'SL Ino', 'Sex-linked ino locus', 'ino. Sex-linked.', 'Near-total eumelanin loss.', 'Sex-linked recessive');
        $pallid = $this->mutation(2, 'Pallid', 'Sex-linked ino locus', 'ino*pd. Allele of ino.', 'Partial ino.', 'Sex-linked recessive');
        $greywingDf = $this->mutation(3, 'SL Greywing DF', 'Sex-linked', 'two Grw. No separate DF allele.', 'Homozygous male.', 'Sex-linked incomplete dominant');

        $this->assertNull(VisualMutationCatalog::selectionMessage(collect([$ino, $pallid]), 'cock'));
        $this->assertStringContainsString(
            'one Z chromosome',
            (string) VisualMutationCatalog::selectionMessage(collect([$ino, $pallid]), 'hen'),
        );
        $this->assertNull(VisualMutationCatalog::selectionMessage(collect([$greywingDf]), 'cock'));
        $this->assertStringContainsString(
            'double-factor form',
            (string) VisualMutationCatalog::selectionMessage(collect([$greywingDf]), 'hen'),
        );
    }

    private function mutation(
        int $id,
        string $name,
        string $series,
        string $allele,
        string $phenotype,
        string $inheritance = 'Autosomal recessive',
    ): VisualMutation {
        $mutation = new VisualMutation;
        $mutation->id = $id;
        $mutation->forceFill([
            'name' => $name,
            'series' => $series,
            'allele' => $allele,
            'phenotype' => $phenotype,
            'inheritance_type' => $inheritance,
        ]);

        return $mutation;
    }
}

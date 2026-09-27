<?php

namespace Tests\Unit;

use App\Models\BaseColor;
use App\Models\Bird;
use App\Models\SplitGene;
use App\Models\VisualMutation;
use App\Services\Breeding\Rbgia\GeneticCodeParser;
use App\Services\Breeding\Rbgia\LocusPunnett;
use App\Services\Breeding\Rbgia\MendelianLocusResolver;
use Tests\TestCase;

class MendelianLocusResolverTest extends TestCase
{
    private MendelianLocusResolver $resolver;

    private LocusPunnett $punnett;

    protected function setUp(): void
    {
        parent::setUp();
        $parser = new GeneticCodeParser;
        $this->resolver = new MendelianLocusResolver($parser);
        $this->punnett = new LocusPunnett($parser);
    }

    public function test_visual_and_split_of_the_same_gene_are_one_locus(): void
    {
        $cock = $this->bird(Bird::SEX_COCK, 'bl+/bl+|D+/D+', 'Green', [
            $this->visual('Dilute', 'Eumelanin distribution', 'dil. Autosomal. Not sex-linked.', 'dil/dil', 'Autosomal recessive'),
        ], [
            $this->split('Split Blue2', 'Base-color locus bl', 'bl2', 'bl+', 'bl+/bl2', 'Blue2', 'Autosomal recessive'),
        ]);
        $hen = $this->bird(Bird::SEX_HEN, 'bl2/bl2|D+/D+', 'Blue2', [
            $this->visual('Opaline', 'Sex-linked', 'op. Distribution mutation, not a bl allele.', 'op/op|op/W', 'Sex-linked recessive'),
            $this->visual('NSL Ino', 'a locus', 'a. Autosomal. Not SL ino.', 'a/a', 'Autosomal recessive'),
        ], [
            $this->split('Split Dilute', 'Eumelanin distribution', 'dil', 'dil+', 'dil+/dil', 'Dilute', 'Autosomal recessive'),
        ]);

        $specs = $this->resolver->mutationSpecs($cock, $hen);
        $byName = [];
        foreach ($specs as $spec) {
            $byName[$spec['name']] = $spec;
        }

        $this->assertArrayNotHasKey('Split Dilute', $byName);
        $this->assertArrayNotHasKey('Split Blue2', $byName);
        $this->assertSame('calculated', $byName['Dilute']['status']);
        $this->assertSame('visual_mutation', $byName['Dilute']['category']);
        $this->assertSame(['dil', 'dil'], $byName['Dilute']['cock_alleles']);
        $this->assertSame(['dil+', 'dil'], $byName['Dilute']['hen_alleles']);

        $dilute = $this->punnett->cross(
            $byName['Dilute']['cock_alleles'],
            $byName['Dilute']['hen_alleles'],
            false,
            'Autosomal recessive',
        );
        $this->assertEquals([
            'dil+/dil' => 'carrier_split',
            'dil/dil' => 'visual',
        ], $this->classes($dilute));
        $this->assertEqualsWithDelta(0.5, $this->probability($dilute, 'dil/dil'), 0.0001);

        $this->assertSame(['op+', 'op+'], $byName['Opaline']['cock_alleles']);
        $this->assertSame(['op', 'W'], $byName['Opaline']['hen_alleles']);
        $opaline = $this->punnett->cross(
            $byName['Opaline']['cock_alleles'],
            $byName['Opaline']['hen_alleles'],
            true,
            'Sex-linked recessive',
        );
        $this->assertSame('carrier_split', $this->classes($opaline)['op+/op']);
        $this->assertSame('hemizygous_wild', $this->classes($opaline)['op+/W']);

        $this->assertSame(['a+', 'a+'], $byName['NSL Ino']['cock_alleles']);
        $this->assertSame(['a', 'a'], $byName['NSL Ino']['hen_alleles']);
    }

    public function test_hidden_blue_split_replaces_a_wild_type_ground_color(): void
    {
        $cock = $this->bird(Bird::SEX_COCK, 'bl+/bl+|D+/D+', 'Green', [], [
            $this->split('Split Blue2', 'Base-color locus bl', 'bl2', 'bl+', 'bl+/bl2', 'Blue2', 'Autosomal recessive'),
        ]);
        $hen = $this->bird(Bird::SEX_HEN, 'bl2/bl2|D+/D+', 'Blue2', [], []);

        $cockGround = $this->resolver->resolveGround($cock, (new GeneticCodeParser)->parseSegments('bl+/bl+|D+/D+'));
        $henGround = $this->resolver->resolveGround($hen, (new GeneticCodeParser)->parseSegments('bl2/bl2|D+/D+'));

        $this->assertSame('bl+/bl2', $cockGround['segments'][0]['segment']);
        $this->assertSame('Green', $cockGround['allele_phenotypes']['bl+']);
        $this->assertSame('Blue2', $cockGround['allele_phenotypes']['bl2']);
        $this->assertSame('bl2/bl2', $henGround['segments'][0]['segment']);
        $this->assertSame('Blue2', $henGround['allele_phenotypes']['bl2']);

        $rows = $this->punnett->cross(
            $cockGround['segments'][0]['alleles'],
            $henGround['segments'][0]['alleles'],
            false,
            'Autosomal recessive',
        );
        $this->assertEquals([
            'bl+/bl2' => 'carrier_split',
            'bl2/bl2' => 'visual',
        ], $this->classes($rows));
    }

    public function test_incomplete_dominant_heterozygote_stays_visual(): void
    {
        $rows = $this->punnett->cross(['Ed+', 'Ed'], ['Ed+', 'Ed+'], false, 'Incomplete dominant');

        $this->assertSame('visual_single_factor', $this->classes($rows)['Ed+/Ed']);
        $this->assertArrayNotHasKey('carrier_split', array_flip($this->classes($rows)));
    }

    public function test_a_sex_linked_split_on_a_hen_is_blocked(): void
    {
        $cock = $this->bird(Bird::SEX_COCK, 'bl+/bl+|D+/D+', 'Green', [], []);
        $hen = $this->bird(Bird::SEX_HEN, 'bl+/bl+|D+/D+', 'Green', [], [
            $this->split('Split Opaline', 'Sex-linked', 'op', 'op+', 'op+/op', 'Opaline', 'Sex-linked recessive'),
        ]);

        $specs = $this->resolver->mutationSpecs($cock, $hen);

        $this->assertCount(1, $specs);
        $this->assertSame('blocked', $specs[0]['status']);
    }

    /**
     * @param  list<VisualMutation>  $visuals
     * @param  list<SplitGene>  $splits
     */
    private function bird(string $sex, string $code, string $colorName, array $visuals, array $splits): Bird
    {
        $color = new BaseColor;
        $color->forceFill([
            'name' => $colorName,
            'genetic_code' => $code,
            'series' => 'Green',
            'inheritance_type' => 'Autosomal recessive',
        ]);
        $bird = new Bird;
        $bird->forceFill(['sex' => $sex, 'species_id' => 2]);
        $bird->setRelation('baseColor', $color);
        $bird->setRelation('visualMutations', collect($visuals));
        $bird->setRelation('splitGenes', collect($splits));
        $bird->setRelation('grandparents', collect());

        return $bird;
    }

    private function visual(string $name, string $series, string $allele, string $code, string $inheritance): VisualMutation
    {
        $mutation = new VisualMutation;
        $mutation->forceFill([
            'name' => $name,
            'series' => $series,
            'allele' => $allele,
            'genetic_code' => $code,
            'inheritance_type' => $inheritance,
        ]);

        return $mutation;
    }

    private function split(
        string $name,
        string $category,
        string $mutant,
        string $wild,
        string $code,
        string $whenVisual,
        string $inheritance,
    ): SplitGene {
        $gene = new SplitGene;
        $gene->forceFill([
            'name' => $name,
            'genetic_category' => $category,
            'mutant_allele' => $mutant,
            'wild_type_allele' => $wild,
            'genetic_symbol' => $mutant,
            'genetic_code' => $code,
            'heterozygous_genotype' => $code,
            'phenotype_when_visual' => $whenVisual,
            'inheritance_type' => $inheritance,
        ]);

        return $gene;
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

        return 0.0;
    }
}

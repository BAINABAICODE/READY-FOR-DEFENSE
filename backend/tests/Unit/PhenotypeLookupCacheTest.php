<?php

namespace Tests\Unit;

use App\Models\BaseColor;
use App\Models\VisualMutation;
use App\Services\Breeding\Rbgia\PhenotypeFromGenotype;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PhenotypeLookupCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_joint_outcomes_reuse_catalog_rows(): void
    {
        DB::table('lovebird_species')->insert([
            'id' => 1,
            'common_name' => 'Peach-faced Lovebird',
            'scientific_name' => 'Agapornis roseicollis',
            'species_group' => 'eye-ring',
            'has_eye_ring' => false,
        ]);

        BaseColor::query()->create([
            'lovebird_species_id' => 1,
            'name' => 'Green',
            'genetic_code' => 'bl+/bl+|D+/D+',
            'phenotype' => 'Green body',
            'computable' => true,
        ]);

        VisualMutation::query()->create([
            'lovebird_species_id' => 1,
            'name' => 'Lutino',
            'phenotype' => 'Yellow body, red eyes',
            'computable' => true,
            'sort_order' => 1,
        ]);

        $loci = [
            [
                'locus_key' => 'ground_color',
                'category' => 'base_color',
                'name' => 'Green',
                'genotype' => 'bl+/bl+',
                'expression' => 'non_carrier',
            ],
            [
                'locus_key' => 'dark_factor',
                'category' => 'base_color',
                'name' => 'Dark factor (D)',
                'genotype' => 'D+/D+',
                'expression' => 'non_carrier',
            ],
            [
                'locus_key' => 'ino',
                'category' => 'visual_mutation',
                'name' => 'Lutino',
                'genotype' => 'ino/ino',
                'expression' => 'visual',
            ],
        ];

        $mapper = new PhenotypeFromGenotype;

        DB::flushQueryLog();
        DB::enableQueryLog();
        $first = $mapper->forJoint($loci, 1);
        $afterFirst = count(DB::getQueryLog());

        $second = $mapper->forJoint($loci, 1);
        $third = $mapper->forJoint($loci, 1);

        $this->assertSame($first, $second);
        $this->assertSame($first, $third);
        $this->assertSame('Green', $first['base_color']);
        $this->assertSame(['Lutino'], $first['visual_mutations']);
        $this->assertSame($afterFirst, count(DB::getQueryLog()));
        $this->assertGreaterThan(0, $afterFirst);
    }
}

<?php

namespace Tests\Unit;

use App\Support\HeadToTailPhenotypeDataset;
use App\Support\VisualMutationDataset;
use PHPUnit\Framework\TestCase;

class HeadToTailPhenotypeDatasetTest extends TestCase
{
    public function test_every_species_has_a_unique_head_to_tail_identity(): void
    {
        $identities = HeadToTailPhenotypeDataset::speciesIdentities();
        $this->assertCount(9, $identities);

        $necks = [];
        $heads = [];
        foreach ($identities as $id => $row) {
            foreach (HeadToTailPhenotypeDataset::REGIONS as $region) {
                $this->assertNotSame('', trim((string) $row[$region]), "Species {$id} is missing {$region}");
            }
            $necks[] = $row['neck'];
            $heads[] = $row['head'];
        }

        $this->assertCount(count($necks), array_unique($necks));
        $this->assertCount(count($heads), array_unique($heads));
        $this->assertStringContainsString('yellow collar', strtolower($identities[3]['neck']));
        $this->assertStringContainsString('black collar', strtolower($identities[9]['neck']));
        $this->assertStringContainsString('no collar', strtolower($identities[1]['neck']));
        $this->assertStringContainsString('peach', strtolower($identities[1]['head']));
        $this->assertStringContainsString('black', strtolower($identities[3]['head']));
    }

    public function test_masked_ino_keeps_the_yellow_collar_and_sets_red_eyes(): void
    {
        $map = HeadToTailPhenotypeDataset::compose(3, ['NSL Ino']);

        $this->assertNotNull($map);
        $this->assertStringContainsString('Red', $map['eyes']);
        $this->assertStringContainsString('Yellow', $map['head']);
        $this->assertStringContainsString('Yellow collar', $map['neck']);
        $this->assertStringContainsString('Yellow', $map['body']);
        $this->assertContains('NSL Ino', $map['visual_mutations']);
    }

    public function test_peach_faced_orange_face_changes_only_the_head(): void
    {
        $wild = HeadToTailPhenotypeDataset::compose(1, []);
        $orange = HeadToTailPhenotypeDataset::compose(1, ['Orange Face']);

        $this->assertNotNull($wild);
        $this->assertNotNull($orange);
        $this->assertStringContainsString('Orange', $orange['head']);
        $this->assertSame($wild['neck'], $orange['neck']);
        $this->assertSame($wild['rump'], $orange['rump']);
    }

    public function test_every_stored_visual_mutation_has_an_overlay(): void
    {
        $records = VisualMutationDataset::records(dirname(__DIR__, 2).'/database/data/visual-mutations');
        $overlays = HeadToTailPhenotypeDataset::mutationOverlays();
        $missing = [];

        foreach ($records as $record) {
            $key = HeadToTailPhenotypeDataset::normalizeName($record['name']);
            if (! isset($overlays[$key])) {
                $missing[] = $record['scientific_name'].' · '.$record['name'];
            }
        }

        $this->assertSame([], $missing, 'Missing head-to-tail overlays: '.implode(', ', $missing));
    }

    public function test_ino_covers_violet_and_blue_hides_orange_face(): void
    {
        $inoViolet = HeadToTailPhenotypeDataset::compose(3, ['NSL Ino', 'Violet']);
        $this->assertNotNull($inoViolet);
        $this->assertNotContains('Violet', $inoViolet['visual_mutations']);
        $this->assertStringContainsString('covers the violet', strtolower($inoViolet['pigment_notes']));

        $blueOrange = HeadToTailPhenotypeDataset::compose(1, ['Orange Face'], 'Blue');
        $this->assertNotNull($blueOrange);
        $this->assertNotContains('Orange Face', $blueOrange['visual_mutations']);
        $this->assertStringContainsString('not a visible mutation', strtolower($blueOrange['pigment_notes']));
    }

    public function test_every_visual_mutation_has_a_unique_head_to_tail_phenotype(): void
    {
        $records = VisualMutationDataset::records(dirname(__DIR__, 2).'/database/data/visual-mutations');
        $this->assertNotEmpty($records);

        $signatures = [];
        foreach (HeadToTailPhenotypeDataset::speciesIdentities() as $identity) {
            $signatures[HeadToTailPhenotypeDataset::signature($identity)] = $identity['scientific_name'];
        }

        $diluteBodies = [];
        foreach ($records as $record) {
            $speciesId = HeadToTailPhenotypeDataset::resolveSpeciesId($record['scientific_name']);
            $map = HeadToTailPhenotypeDataset::uniqueForMutation($speciesId, $record['name']);
            $label = $record['scientific_name'].' · '.$record['name'];

            $this->assertNotNull($map, $label);
            $palette = HeadToTailPhenotypeDataset::colorPalette();
            foreach (HeadToTailPhenotypeDataset::REGIONS as $region) {
                $value = trim((string) ($map[$region] ?? ''));
                $this->assertArrayHasKey($value, $palette, "{$label} {$region} is not a color");
            }

            $signature = (string) $map['phenotype_signature'];
            $this->assertSame(HeadToTailPhenotypeDataset::signature($map), $signature);
            $this->assertArrayNotHasKey($signature, $signatures, $label.' repeats '.($signatures[$signature] ?? ''));
            $signatures[$signature] = $label;

            if ($record['name'] === 'Dilute') {
                $diluteBodies[$record['scientific_name']] = $map['body'];
            }
        }

        $this->assertGreaterThan(1, count($diluteBodies));
        $this->assertCount(count($diluteBodies), array_unique($diluteBodies));
        $this->assertSame('Pale grass green', $diluteBodies['Agapornis roseicollis']);
        $this->assertSame('Soft green', $diluteBodies['Agapornis lilianae']);
        $this->assertSame('Light green', $diluteBodies['Agapornis nigrigenis']);
    }
}

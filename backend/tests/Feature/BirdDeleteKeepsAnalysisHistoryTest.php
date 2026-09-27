<?php

namespace Tests\Feature;

use App\Models\Bird;
use App\Models\BirdPair;
use App\Models\BreedingPrediction;
use App\Models\ComputationResult;
use App\Models\User;
use App\Services\Auth\AuthTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BirdDeleteKeepsAnalysisHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_bird_keeps_the_saved_analysis_and_parent_snapshot(): void
    {
        $user = User::factory()->create();
        DB::table('lovebird_species')->insert([
            'id' => 2,
            'common_name' => "Fischer's lovebird",
            'scientific_name' => 'Agapornis fischeri',
            'species_group' => 'eye-ring',
            'has_eye_ring' => true,
        ]);

        $cock = Bird::query()->forceCreate([
            'user_id' => $user->id,
            'bird_id' => 'thesis2',
            'age_months' => 13,
            'species_id' => 2,
            'sex' => Bird::SEX_COCK,
        ]);
        $hen = Bird::query()->forceCreate([
            'user_id' => $user->id,
            'bird_id' => 'thesis1',
            'age_months' => 16,
            'species_id' => 2,
            'sex' => Bird::SEX_HEN,
        ]);

        $parentOne = [
            'bird_id' => 'thesis2',
            'age_months' => 13,
            'sex' => 'cock',
            'sex_label' => 'Cock — Male',
            'species' => ['id' => 2, 'common_name' => "Fischer's lovebird"],
            'base_color' => ['name' => 'Green'],
            'visual_mutations' => [['name' => 'Opaline'], ['name' => 'Dilute']],
            'split_genes' => [],
        ];
        $parentTwo = [
            'bird_id' => 'thesis1',
            'age_months' => 16,
            'sex' => 'hen',
            'sex_label' => 'Hen — Female',
            'species' => ['id' => 2, 'common_name' => "Fischer's lovebird"],
            'base_color' => ['name' => 'Green'],
            'visual_mutations' => [['name' => 'Euwing DF']],
            'split_genes' => [],
        ];

        $pair = BirdPair::query()->forceCreate([
            'user_id' => $user->id,
            'parent_1_bird_id' => $cock->id,
            'parent_2_bird_id' => $hen->id,
            'species_1_id' => 2,
            'species_2_id' => 2,
            'pairing_status' => 'analyzed',
        ]);

        $snapshot = [
            'parent_1' => $parentOne,
            'parent_2' => $parentTwo,
            'captured_at' => '2026-09-25T18:10:00+00:00',
        ];
        $stored = ['message' => 'Recorded clutch outcomes'];

        $computation = ComputationResult::query()->forceCreate([
            'user_id' => $user->id,
            'bird_pair_id' => $pair->id,
            'status' => 'completed',
            'compatibility_result' => ['label' => 'Same-Species Pair'],
            'genetic_compatibility' => [],
            'overall_compatibility' => ['label' => 'Same-Species Pair'],
            'warnings' => [],
            'offspring_probability' => [],
            'predicted_base_colors' => [],
            'predicted_visual_mutations' => [],
            'predicted_split_genes' => [],
            'predicted_phenotypes' => [],
            'rbgia_data' => $stored,
            'genetic_calculation' => [],
            'scientific_information' => [],
            'parent_snapshot' => $snapshot,
            'offspring_visualizations' => [],
            'result_presentation' => ['summary' => 'Saved at analysis time'],
        ]);

        $prediction = BreedingPrediction::query()->forceCreate([
            'user_id' => $user->id,
            'bird_pair_id' => $pair->id,
            'computation_result_id' => $computation->id,
            'parent_1_bird_id' => 'thesis2',
            'parent_2_bird_id' => 'thesis1',
            'status' => 'completed',
            'parent_1' => $parentOne,
            'parent_2' => $parentTwo,
            'validation' => ['compatibility' => ['label' => 'Same-Species Pair', 'compatibility_status' => 'SAME_SPECIES']],
            'prediction' => $stored,
        ]);
        $computation->update(['prediction_id' => $prediction->id]);

        $token = app(AuthTokenService::class)->issue($user);

        $this->withToken($token)
            ->deleteJson('/api/birds/'.$hen->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('birds', ['id' => $hen->id]);
        $this->assertDatabaseHas('birds', ['id' => $cock->id, 'bird_id' => 'thesis2']);
        $this->assertDatabaseHas('bird_pairs', [
            'id' => $pair->id,
            'parent_1_bird_id' => $cock->id,
            'parent_2_bird_id' => null,
        ]);
        $this->assertDatabaseHas('breeding_predictions', [
            'id' => $prediction->id,
            'parent_1_bird_id' => 'thesis2',
            'parent_2_bird_id' => 'thesis1',
        ]);
        $this->assertDatabaseHas('computation_results', ['id' => $computation->id]);

        $this->withToken($token)
            ->getJson('/api/birds')
            ->assertOk()
            ->assertJsonMissing(['bird_id' => 'thesis1'])
            ->assertJsonFragment(['bird_id' => 'thesis2']);

        $this->withToken($token)
            ->getJson('/api/predictions')
            ->assertOk()
            ->assertJsonPath('data.0.parent_1_bird_id', 'thesis2')
            ->assertJsonPath('data.0.parent_2_bird_id', 'thesis1')
            ->assertJsonPath('data.0.parent_2.bird_id', 'thesis1')
            ->assertJsonPath('data.0.parent_2.visual_mutations.0.name', 'Euwing DF')
            ->assertJsonPath('data.0.parent_1.visual_mutations.0.name', 'Opaline');

        $this->withToken($token)
            ->getJson('/api/computation-results/'.$computation->id)
            ->assertOk()
            ->assertJsonPath('data.parent_snapshot.parent_2.bird_id', 'thesis1')
            ->assertJsonPath('data.parent_snapshot.parent_2.base_color.name', 'Green')
            ->assertJsonPath('data.result_presentation.summary', 'Saved at analysis time')
            ->assertJsonPath('data.rbgia_data.message', 'Recorded clutch outcomes');
    }
};

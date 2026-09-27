<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeadToTailPhenotype extends Model
{
    public const KIND_SPECIES = 'species_identity';

    public const KIND_MUTATION = 'visual_mutation';

    protected $fillable = [
        'kind',
        'lovebird_species_id',
        'visual_mutation_id',
        'mutation_name',
        'eyes',
        'head',
        'neck',
        'body',
        'wings',
        'rump',
        'tail',
        'pigment_notes',
        'source',
        'phenotype_signature',
    ];

    protected function casts(): array
    {
        return [
            'lovebird_species_id' => 'integer',
            'visual_mutation_id' => 'integer',
        ];
    }

    public function species(): BelongsTo
    {
        return $this->belongsTo(LovebirdSpecies::class, 'lovebird_species_id');
    }

    public function visualMutation(): BelongsTo
    {
        return $this->belongsTo(VisualMutation::class);
    }

    /**
     * @return array<string, string|null>
     */
    public function regionMap(): array
    {
        return [
            'eyes' => $this->eyes,
            'head' => $this->head,
            'neck' => $this->neck,
            'body' => $this->body,
            'wings' => $this->wings,
            'rump' => $this->rump,
            'tail' => $this->tail,
        ];
    }
}

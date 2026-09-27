<?php

namespace App\Services\Breeding;

use App\Models\Bird;
use App\Models\BreedingSafetyRule;
use App\Models\SpeciesBreedingCompatibility;
use App\Support\BaseColorCatalog;
use App\Support\GeneticLocus;
use App\Support\MutationGroundApplicability;
use App\Support\SplitGeneCatalog;
use App\Support\VisualMutationCatalog;
use Illuminate\Support\Collection;

class BreedingPairValidator
{
    /**
     * @return array<string, mixed>
     */
    public function validate(Bird $parentOne, Bird $parentTwo): array
    {
        $findings = [];

        $sameRecord = $parentOne->id && $parentTwo->id && (int) $parentOne->id === (int) $parentTwo->id;
        $sameCode = trim((string) $parentOne->bird_id) !== ''
            && strcasecmp(trim((string) $parentOne->bird_id), trim((string) $parentTwo->bird_id)) === 0;

        if ($sameRecord || $sameCode) {
            $findings[] = $this->finding(
                'error',
                'same_bird',
                'Parent 1 and Parent 2 cannot be the same bird.',
            );
        }

        $this->validateSex($parentOne, $parentTwo, $findings);
        $compatibility = $this->compatibility($parentOne, $parentTwo, $findings);
        $this->validateAge($parentOne, 'Parent 1', $findings);
        $this->validateAge($parentTwo, 'Parent 2', $findings);
        $this->validateGenetics($parentOne, 'Parent 1', $findings);
        $this->validateGenetics($parentTwo, 'Parent 2', $findings);
        $this->validateSharedRecessives($parentOne, $parentTwo, $findings);
        $this->validatePedigree($parentOne, $parentTwo, $findings);

        $findings[] = $this->finding(
            'information',
            'breeding_safety',
            'Breeding Safety: Genetic compatibility does not guarantee reproductive success, healthy offspring, or safe breeding. Health, nutrition, physical condition, environment, reproductive condition, and veterinary assessment are also important.',
        );

        $errors = array_values(array_filter($findings, fn (array $item) => $item['level'] === 'error'));
        $warnings = array_values(array_filter($findings, fn (array $item) => $item['level'] === 'warning'));
        $information = array_values(array_filter($findings, fn (array $item) => $item['level'] === 'information'));

        return [
            'can_predict' => $errors === [] && ($compatibility['prediction_allowed'] ?? false),
            'compatibility' => $compatibility,
            'errors' => $errors,
            'warnings' => $warnings,
            'information' => $information,
            'parents' => [
                'parent_1' => $this->parentSummary($parentOne),
                'parent_2' => $this->parentSummary($parentTwo),
            ],
        ];
    }

    /**
     * @param  list<array<string, string>>  $findings
     */
    private function validateSex(Bird $parentOne, Bird $parentTwo, array &$findings): void
    {
        $sexes = [strtolower((string) $parentOne->sex), strtolower((string) $parentTwo->sex)];

        if (in_array('', $sexes, true) || in_array('null', $sexes, true)) {
            $findings[] = $this->finding(
                'error',
                'missing_sex',
                'Both parents must have a known sex before breeding can be calculated.',
            );

            return;
        }

        if ($sexes[0] === $sexes[1]) {
            $findings[] = $this->finding(
                'error',
                'same_sex',
                'A breeding pair requires one Cock (Male) and one Hen (Female).',
            );
        }
    }

    /**
     * @param  list<array<string, string>>  $findings
     * @return array<string, mixed>
     */
    private function compatibility(Bird $parentOne, Bird $parentTwo, array &$findings): array
    {
        if (! $parentOne->species_id || ! $parentTwo->species_id) {
            $findings[] = $this->finding('error', 'missing_species', 'Both parents must have a documented species.');

            return $this->undocumentedCompatibility(null, $parentOne, $parentTwo, false);
        }

        if ((int) $parentOne->species_id === (int) $parentTwo->species_id) {
            $findings[] = $this->finding(
                'information',
                'same_species',
                'Same-Species Pair. Normal species-specific breeding analysis can continue.',
            );

            return [
                'compatibility_status' => SpeciesBreedingCompatibility::SAME_SPECIES,
                'label' => 'Same-Species Pair',
                'breeding_type' => 'same_species',
                'fertility_status' => null,
                'risk_level' => null,
                'warning_message' => null,
                'scientific_basis' => 'Parents share the same stored species record. This does not by itself document fertility or reproductive success.',
                'scientific_source' => null,
                'verification_status' => null,
                'notes' => null,
                'prediction_allowed' => true,
                'species_1_id' => $parentOne->species_id,
                'species_2_id' => $parentTwo->species_id,
            ];
        }

        [$low, $high] = SpeciesBreedingCompatibility::pairKey(
            (int) $parentOne->species_id,
            (int) $parentTwo->species_id,
        );

        $record = SpeciesBreedingCompatibility::query()
            ->where('species_low_id', $low)
            ->where('species_high_id', $high)
            ->first();

        if (! $record) {
            $findings[] = $this->finding(
                'warning',
                'not_documented',
                'Compatibility Not Verified. No reliable cross-breeding evidence is stored for this species pair. A definitive breeding prediction cannot be produced.',
            );

            return $this->undocumentedCompatibility($record, $parentOne, $parentTwo, false);
        }

        $status = $record->compatibility_status;
        $payload = [
            'compatibility_status' => $status,
            'label' => $this->statusLabel($status),
            'breeding_type' => $record->breeding_type,
            'fertility_status' => $record->fertility_status,
            'risk_level' => $record->risk_level,
            'warning_message' => $record->warning_message,
            'scientific_basis' => $record->scientific_basis,
            'scientific_source' => $record->scientific_source,
            'verification_status' => $record->verification_status,
            'notes' => $record->notes,
            'prediction_allowed' => false,
            'species_1_id' => $parentOne->species_id,
            'species_2_id' => $parentTwo->species_id,
        ];

        if ($status === SpeciesBreedingCompatibility::UNSUPPORTED) {
            $findings[] = $this->finding(
                'error',
                'unsupported_pairing',
                $record->warning_message ?: 'Unsupported Pairing. This species combination is not treated as a supported breeding pair.',
            );
        } elseif ($status === SpeciesBreedingCompatibility::DOCUMENTED_HYBRID) {
            $findings[] = $this->finding(
                'warning',
                'documented_hybrid',
                $record->warning_message ?: 'Documented Hybrid Pairing. Offspring are hybrids and should not be treated as purebred offspring.',
            );
        } elseif ($status === SpeciesBreedingCompatibility::LIMITED_OR_UNCERTAIN) {
            $findings[] = $this->finding(
                'warning',
                'limited_evidence',
                $record->warning_message ?: 'Limited Evidence. The compatibility of this species combination is not sufficiently verified for a definitive breeding prediction.',
            );
        } else {
            $findings[] = $this->finding(
                'warning',
                'not_documented',
                $record->warning_message ?: 'Compatibility Not Verified. The stored record does not establish a definitive cross-breeding result.',
            );
        }

        if ($record->verification_status && stripos($record->verification_status, 'Needs Verification') !== false) {
            $findings[] = $this->finding(
                'warning',
                'unverified_compatibility',
                'One or more selected genetic traits have not been scientifically verified in the AGAPORA database. This prediction should be treated as provisional.',
            );
        }

        return $payload;
    }

    /**
     * @param  list<array<string, string>>  $findings
     */
    private function validateAge(Bird $bird, string $label, array &$findings): void
    {
        $rule = BreedingSafetyRule::query()
            ->where('lovebird_species_id', $bird->species_id)
            ->where('verification_status', 'not like', '%Needs Verification%')
            ->first();

        if (! $rule || $bird->age_months === null) {
            $findings[] = $this->finding(
                'information',
                'age_not_documented',
                "{$label}: No documented breeding-age range is stored for this species. Age is recorded, but it is not used to claim breeding readiness or infertility.",
            );

            return;
        }

        if ($rule->minimum_age_months !== null && $bird->age_months < $rule->minimum_age_months) {
            $findings[] = $this->finding(
                'warning',
                'age_below_minimum',
                $rule->warning_below_minimum ?: 'This bird may be too young for breeding. Verify its age and breeding readiness before pairing.',
            );
        } elseif (
            ($rule->recommended_min_months !== null && $bird->age_months < $rule->recommended_min_months)
            || ($rule->recommended_max_months !== null && $bird->age_months > $rule->recommended_max_months)
        ) {
            $findings[] = $this->finding(
                'warning',
                'age_outside_range',
                $rule->warning_outside_range ?: 'This bird may be outside the recommended breeding age range. Verify its health and breeding condition before breeding.',
            );
        }
    }

    /**
     * @param  list<array<string, string>>  $findings
     */
    private function validateGenetics(Bird $bird, string $label, array &$findings): void
    {
        $missing = [];

        if (! $bird->base_color_id) {
            $missing[] = 'base color';
        } elseif ($bird->baseColor && (int) $bird->baseColor->lovebird_species_id !== (int) $bird->species_id) {
            $findings[] = $this->finding(
                'error',
                'invalid_base_color',
                "{$label}: This base color is not documented for the selected species.",
            );
        } elseif ($bird->baseColor) {
            $colorMessage = BaseColorCatalog::incompatibleMessage($bird->baseColor, $bird->splitGenes);
            if ($colorMessage) {
                $findings[] = $this->finding('error', 'invalid_base_color', "{$label}: {$colorMessage}");
            }
        }

        $mutationIds = $bird->visualMutations->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($mutationIds !== []) {
            $message = VisualMutationCatalog::incompatibleMessage(
                $mutationIds,
                (int) $bird->species_id,
                $bird->sex,
                $bird->splitGenes,
            );
            if ($message) {
                $speciesMismatch = str_contains($message, 'belong to the selected species');
                $findings[] = $this->finding(
                    'error',
                    $speciesMismatch ? 'invalid_visual_mutation' : 'invalid_mutation_combination',
                    $speciesMismatch
                        ? "{$label}: This visual mutation is not documented for the selected species."
                        : "{$label}: {$message}",
                );
            }

            $groundMessage = MutationGroundApplicability::firstForMutations($bird->baseColor, $bird->visualMutations);
            if ($groundMessage) {
                $findings[] = $this->finding(
                    'error',
                    'inapplicable_visual_mutation',
                    "{$label}: {$groundMessage}",
                );
            }
        }

        $geneIds = $bird->splitGenes->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($geneIds !== []) {
            $message = SplitGeneCatalog::incompatibleMessage(
                $geneIds,
                (int) $bird->species_id,
                $bird->sex,
                $bird->baseColor,
                $bird->visualMutations,
            );
            if ($message) {
                $findings[] = $this->finding(
                    'error',
                    'invalid_split_gene',
                    str_contains(strtolower($message), 'hen') || str_contains(strtolower($message), 'cock')
                        ? "{$label}: The selected sex-linked gene information is inconsistent with the bird's sex."
                        : "{$label}: {$message}",
                );
            }
        }

        foreach ($bird->visualMutations as $mutation) {
            if ($this->needsVerification($mutation->verification_status)) {
                $findings[] = $this->finding(
                    'warning',
                    'unverified_mutation',
                    "{$label}: One or more selected genetic traits have not been scientifically verified in the AGAPORA database. This prediction should be treated as provisional.",
                );
                break;
            }
        }

        foreach (collect([$bird->baseColor])->merge($bird->splitGenes) as $record) {
            if ($record && $this->needsVerification($record->verification_status)) {
                $findings[] = $this->finding(
                    'warning',
                    'unverified_gene',
                    "{$label}: One or more selected genetic traits have not been scientifically verified in the AGAPORA database. This prediction should be treated as provisional.",
                );
                break;
            }
        }

        if ($missing !== []) {
            $findings[] = $this->finding(
                'warning',
                'incomplete_genetics',
                "{$label}: Genetic information is incomplete. Missing: ".implode(', ', $missing).'. The prediction may be less reliable. Missing genotype information is not invented.',
            );
        }
    }

    /**
     * @param  list<array<string, string>>  $findings
     */
    private function validateSharedRecessives(Bird $parentOne, Bird $parentTwo, array &$findings): void
    {
        if ((int) $parentOne->species_id !== (int) $parentTwo->species_id) {
            return;
        }

        $left = $this->recessiveSymbols($parentOne);
        $right = $this->recessiveSymbols($parentTwo);
        $shared = array_values(array_intersect(array_keys($left), array_keys($right)));

        foreach ($shared as $symbol) {
            $findings[] = $this->finding(
                'information',
                'shared_recessive',
                "Both parents carry this recessive gene ({$left[$symbol]}). The offspring may inherit and express the trait depending on the resulting genotype.",
            );
        }
    }

    /**
     * @param  list<array<string, string>>  $findings
     */
    private function validatePedigree(Bird $parentOne, Bird $parentTwo, array &$findings): void
    {
        $oneComplete = $this->hasCompleteLinkedPedigree($parentOne);
        $twoComplete = $this->hasCompleteLinkedPedigree($parentTwo);

        if (! $oneComplete || ! $twoComplete) {
            $findings[] = $this->finding(
                'information',
                'incomplete_pedigree',
                'Pedigree information is incomplete. Relatedness cannot be reliably determined.',
            );

            return;
        }

        $shared = array_intersect($this->ancestorKeys($parentOne), $this->ancestorKeys($parentTwo));
        if ($shared !== []) {
            $findings[] = $this->finding(
                'warning',
                'close_relationship',
                'Close-Relationship Warning: These birds appear to share a close documented ancestor. Breeding closely related birds can increase the risk of inherited problems.',
            );
        }
    }

    private function hasCompleteLinkedPedigree(Bird $bird): bool
    {
        if ($bird->grandparents->count() < count(Bird::GRANDPARENT_ROLES)) {
            return false;
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function ancestorKeys(Bird $bird): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    private function recessiveSymbols(Bird $bird): array
    {
        $symbols = [];

        foreach ($bird->visualMutations as $mutation) {
            $type = (string) $mutation->inheritance_type;
            if (stripos($type, 'recessive') === false || stripos($type, 'dominant') !== false) {
                continue;
            }
            $token = GeneticLocus::tokenFromAlleleText($mutation->allele);
            if ($token) {
                $symbols[$token] = $mutation->name;
            }
        }

        foreach ($bird->splitGenes as $gene) {
            if (strcasecmp((string) $gene->inheritance_type, 'Autosomal recessive') !== 0
                && stripos((string) $gene->inheritance_type, 'Sex-linked recessive') === false) {
                continue;
            }
            if ($gene->mutant_allele) {
                $symbols[$gene->mutant_allele] = $symbols[$gene->mutant_allele] ?? $gene->name;
            }
        }

        return $symbols;
    }

    /**
     * @return array<string, mixed>
     */
    private function parentSummary(Bird $bird): array
    {
        return [
            'id' => $bird->id,
            'bird_id' => $bird->bird_id,
            'age_months' => $bird->age_months,
            'sex' => $bird->sex,
            'sex_label' => $bird->sex === Bird::SEX_HEN ? 'Hen — Female' : ($bird->sex === Bird::SEX_COCK ? 'Cock — Male' : null),
            'species' => $bird->species ? [
                'id' => $bird->species->id,
                'common_name' => $bird->species->common_name,
                'scientific_name' => $bird->species->scientific_name,
            ] : null,
            'base_color' => BaseColorCatalog::geneticPayload($bird->baseColor),
            'visual_mutations' => $bird->visualMutations
                ->map(fn ($mutation) => VisualMutationCatalog::geneticPayload($mutation))
                ->filter()
                ->values()
                ->all(),
            'split_genes' => $bird->splitGenes
                ->map(fn ($gene) => SplitGeneCatalog::geneticPayload($gene))
                ->filter()
                ->values()
                ->all(),
            'grandparents_recorded' => $bird->grandparents->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function undocumentedCompatibility(mixed $record, Bird $parentOne, Bird $parentTwo, bool $allowed): array
    {
        return [
            'compatibility_status' => SpeciesBreedingCompatibility::NOT_DOCUMENTED,
            'label' => 'Compatibility Not Verified',
            'breeding_type' => null,
            'fertility_status' => null,
            'risk_level' => null,
            'warning_message' => 'No reliable evidence has been established in the AGAPORA database for this species pair.',
            'scientific_basis' => null,
            'scientific_source' => null,
            'verification_status' => 'Needs Verification',
            'notes' => 'Absence of a stored pair is not treated as evidence that the species can crossbreed.',
            'prediction_allowed' => $allowed,
            'species_1_id' => $parentOne->species_id,
            'species_2_id' => $parentTwo->species_id,
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            SpeciesBreedingCompatibility::SAME_SPECIES => 'Same-Species Pair',
            SpeciesBreedingCompatibility::DOCUMENTED_HYBRID => 'Documented Hybrid Pairing',
            SpeciesBreedingCompatibility::LIMITED_OR_UNCERTAIN => 'Limited Evidence',
            SpeciesBreedingCompatibility::UNSUPPORTED => 'Unsupported Pairing',
            default => 'Compatibility Not Verified',
        };
    }

    private function needsVerification(?string $status): bool
    {
        return $status !== null && stripos($status, 'Needs Verification') !== false;
    }

    /**
     * @return array<string, string>
     */
    private function finding(string $level, string $code, string $message): array
    {
        return [
            'level' => $level,
            'code' => $code,
            'message' => $message,
        ];
    }
}

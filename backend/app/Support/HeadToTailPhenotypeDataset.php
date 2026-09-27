<?php

namespace App\Support;

/**
 * Species identity maps and visual-mutation overlays for head-to-tail looks.
 * Core order: Eyes, Head, Body, Wings, Rump, Tail. Neck is the species landmark.
 * Overlays change only documented pigment effects; missing alleles are not invented.
 */
class HeadToTailPhenotypeDataset
{
    public const REGIONS = ['eyes', 'head', 'neck', 'body', 'wings', 'rump', 'tail'];

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function speciesIdentities(): array
    {
        return [
            1 => [
                'species_id' => 1,
                'scientific_name' => 'Agapornis roseicollis',
                'common_name' => 'Rosy-faced lovebird',
                'alternate_names' => 'Peach-faced lovebird',
                'eyes' => 'Dark brown/black, no eye ring',
                'head' => 'Peach-pink face, extended onto the throat',
                'neck' => 'Green nape, no collar',
                'body' => 'Bright grass-green',
                'wings' => 'Green',
                'rump' => 'Bright sky-blue',
                'tail' => 'Green with blue',
                'pigment_notes' => 'Peach face and sky-blue rump identify A. roseicollis. No white eye ring and no yellow collar.',
                'source' => 'Forshaw, Parrots of the World; MUTAVI A. roseicollis wild-type description',
            ],
            2 => [
                'species_id' => 2,
                'scientific_name' => 'Agapornis fischeri',
                'common_name' => "Fischer's lovebird",
                'alternate_names' => null,
                'eyes' => 'Dark brown/black with a white eye ring',
                'head' => 'Orange-red face, no black mask',
                'neck' => 'Yellow-olive nape, no yellow collar',
                'body' => 'Bright green',
                'wings' => 'Green',
                'rump' => 'Blue',
                'tail' => 'Green',
                'pigment_notes' => 'Orange face plus white eye ring, without a black mask or yellow collar.',
                'source' => 'Forshaw, Parrots of the World; AGASSCOM / MUTAVI A. fischeri catalogs',
            ],
            3 => [
                'species_id' => 3,
                'scientific_name' => 'Agapornis personatus',
                'common_name' => 'Yellow-collared lovebird',
                'alternate_names' => 'Masked lovebird',
                'eyes' => 'Dark brown/black with a white eye ring',
                'head' => 'Black/brown mask',
                'neck' => 'Bright yellow collar',
                'body' => 'Green with a yellow breast',
                'wings' => 'Green',
                'rump' => 'Blue',
                'tail' => 'Green',
                'pigment_notes' => 'Black mask and yellow collar identify A. personatus (masked / yellow-collared).',
                'source' => 'Forshaw, Parrots of the World; AGASSCOM / MUTAVI A. personatus catalogs',
            ],
            4 => [
                'species_id' => 4,
                'scientific_name' => 'Agapornis nigrigenis',
                'common_name' => 'Black-cheeked lovebird',
                'alternate_names' => null,
                'eyes' => 'Dark brown/black with a white eye ring',
                'head' => 'Blackish-brown cheeks and forehead',
                'neck' => 'Greenish-yellow, not a bold yellow collar',
                'body' => 'Green',
                'wings' => 'Green',
                'rump' => 'Blue',
                'tail' => 'Green',
                'pigment_notes' => 'Black cheeks without the masked yellow collar.',
                'source' => 'Forshaw, Parrots of the World; IUCN/BirdLife A. nigrigenis',
            ],
            5 => [
                'species_id' => 5,
                'scientific_name' => 'Agapornis lilianae',
                'common_name' => "Lilian's lovebird",
                'alternate_names' => 'Nyasa lovebird',
                'eyes' => 'Dark brown/black with a white eye ring',
                'head' => 'Salmon-peach face, no black mask',
                'neck' => 'Green, no yellow collar',
                'body' => 'Green',
                'wings' => 'Green',
                'rump' => 'Blue',
                'tail' => 'Green',
                'pigment_notes' => 'Salmon face and white eye ring, no black mask or yellow collar.',
                'source' => 'Forshaw, Parrots of the World; IUCN/BirdLife A. lilianae',
            ],
            6 => [
                'species_id' => 6,
                'scientific_name' => 'Agapornis taranta',
                'common_name' => 'Black-winged lovebird',
                'alternate_names' => 'Abyssinian lovebird',
                'eyes' => 'Dark brown/black, no eye ring',
                'head' => 'Male red forehead; female green head',
                'neck' => 'Green nape, no collar band',
                'body' => 'Green',
                'wings' => 'Black flight feathers',
                'rump' => 'Green, not blue',
                'tail' => 'Green',
                'pigment_notes' => 'Black flights and a green rump identify A. taranta. Male red forehead.',
                'source' => 'Forshaw, Parrots of the World; AGASSCOM A. taranta',
            ],
            7 => [
                'species_id' => 7,
                'scientific_name' => 'Agapornis pullarius',
                'common_name' => 'Red-headed lovebird',
                'alternate_names' => 'Red-faced lovebird',
                'eyes' => 'Dark brown/black, no eye ring',
                'head' => 'Vivid scarlet-red face',
                'neck' => 'Slender green neck, no collar',
                'body' => 'Green',
                'wings' => 'Green',
                'rump' => 'Blue',
                'tail' => 'Green',
                'pigment_notes' => 'Scarlet face on a slender non-eye-ring bird.',
                'source' => 'Forshaw, Parrots of the World; AGASSCOM A. pullarius',
            ],
            8 => [
                'species_id' => 8,
                'scientific_name' => 'Agapornis canus',
                'common_name' => 'Grey-headed lovebird',
                'alternate_names' => 'Madagascar lovebird',
                'eyes' => 'Dark brown/black, no eye ring',
                'head' => 'Male pearl-grey head; female green head',
                'neck' => 'Grey fading into green',
                'body' => 'Green',
                'wings' => 'Green',
                'rump' => 'Green',
                'tail' => 'Green',
                'pigment_notes' => 'Grey head in the male identifies A. canus.',
                'source' => 'Forshaw, Parrots of the World; AGASSCOM A. canus',
            ],
            9 => [
                'species_id' => 9,
                'scientific_name' => 'Agapornis swindernianus',
                'common_name' => 'Black-collared lovebird',
                'alternate_names' => "Swindern's lovebird",
                'eyes' => 'Dark brown/black, no eye ring',
                'head' => 'Green',
                'neck' => 'Black collar band',
                'body' => 'Green',
                'wings' => 'Green',
                'rump' => 'Red-orange',
                'tail' => 'Green',
                'pigment_notes' => 'Black neck band and red-orange rump identify A. swindernianus.',
                'source' => 'Forshaw, Parrots of the World; IUCN/BirdLife A. swindernianus',
            ],
        ];
    }

    /**
     * Partial overlays keyed by normalized mutation name. Omitted regions keep the species map.
     *
     * @return array<string, array<string, string>>
     */
    public static function mutationOverlays(): array
    {
        return [
            'cinnamon' => [
                'eyes' => 'Dark brown; chicks red-eyed for a few days, then the eye darkens',
                'body' => 'Yellow-green (brown eumelanin)',
                'wings' => 'Brown-green',
                'pigment_notes' => 'Eumelanin is brown rather than black. Green-series birds look yellow-green.',
            ],
            'sl ino' => [
                'eyes' => 'Red',
                'head' => 'Yellow (eumelanin lost)',
                'body' => 'Yellow on green (lutino); white on blue (albino)',
                'wings' => 'Yellow / clear',
                'rump' => 'Pale yellow or white',
                'tail' => 'Yellow / pale',
                'pigment_notes' => 'Near-total eumelanin loss. On green this is lutino. Albino is this same allele on a blue ground.',
            ],
            'nsl ino' => [
                'eyes' => 'Red',
                'head' => 'Yellow (eumelanin lost)',
                'body' => 'Yellow on green (lutino); white on blue (albino)',
                'wings' => 'Yellow / clear',
                'rump' => 'Pale yellow or white',
                'tail' => 'Yellow / pale',
                'pigment_notes' => 'Near-total eumelanin loss and red eyes. On green this is lutino. Albino is this same allele on a blue ground.',
            ],
            'pallid' => [
                'eyes' => 'Dark, with reduced eumelanin',
                'body' => 'Diluted green (eumelanin reduced, not absent)',
                'wings' => 'Diluted green-brown',
                'pigment_notes' => 'Partial ino. Eumelanin is reduced, not absent.',
            ],
            'pale' => [
                'eyes' => 'Dark',
                'body' => 'Pale / reduced eumelanin',
                'wings' => 'Pale',
                'pigment_notes' => 'Sex-linked pale. Not pale fallow and not pale headed.',
            ],
            'opaline' => [
                'head' => 'Mask / face pigment spreads over the head',
                'rump' => 'Rump pattern changes (pigment redistributed)',
                'pigment_notes' => 'Distribution mutation. The mask spreads over the head and the rump pattern changes.',
            ],
            'violet' => [
                'body' => 'Violet wash, strongest where structural blue is visible',
                'rump' => 'Violet wash on the rump',
                'pigment_notes' => 'One V gives a violet wash. Not a separate visible color with SL Ino or NSL Ino, because ino covers the wash.',
            ],
            'double violet' => [
                'body' => 'Strong violet wash',
                'rump' => 'Strong violet on the rump',
                'pigment_notes' => 'Same violet mutation, homozygous. Stronger violet than one V.',
            ],
            'pale headed' => [
                'head' => 'Reduced red / peach mask',
                'pigment_notes' => 'One Ph reduces the red mask. Not a visible face color on a psittacin-free blue ground.',
            ],
            'pale headed df' => [
                'head' => 'Strongly reduced / clear face',
                'pigment_notes' => 'Same pale-headed mutation, homozygous. Stronger facial effect than one Ph.',
            ],
            'grey factor' => [
                'body' => 'Greyed / darkened plumage',
                'wings' => 'Greyed green or blue',
                'rump' => 'Greyed structural color',
                'pigment_notes' => 'Greys and darkens the plumage. One Gf.',
            ],
            'grey factor df' => [
                'body' => 'Strong grey',
                'wings' => 'Strong grey',
                'rump' => 'Grey',
                'pigment_notes' => 'Same grey-factor mutation, homozygous. Stronger grey than one Gf.',
            ],
            'bronze fallow' => [
                'eyes' => 'Red / dark red',
                'body' => 'Brown eumelanin, limited reduction',
                'wings' => 'Brownish flights',
                'pigment_notes' => 'Autosomal fallow. Brown eumelanin and red eyes.',
            ],
            'pale fallow' => [
                'eyes' => 'Red',
                'body' => 'Yellowish, strong eumelanin reduction',
                'wings' => 'Pale yellowish',
                'pigment_notes' => 'Autosomal pale fallow. Strong eumelanin reduction, yellowish body, red eyes.',
            ],
            'dun fallow' => [
                'eyes' => 'Reddish',
                'body' => 'Dun / brownish fallow',
                'wings' => 'Dun-brown',
                'pigment_notes' => 'Autosomal dun fallow. Symbol df is dun fallow, not dark factor.',
            ],
            'dilute' => [
                'body' => 'Diluted / reduced eumelanin',
                'wings' => 'Diluted',
                'rump' => 'Diluted structural color',
                'pigment_notes' => 'Even quantitative eumelanin reduction. Not pastel.',
            ],
            'marbled' => [
                'body' => 'Uneven eumelanin reduction',
                'wings' => 'Marbled / uneven dilution',
                'pigment_notes' => 'Uneven eumelanin reduction. Not dilute and not edged.',
            ],
            'dm jade' => [
                'eyes' => 'Born red-eyed; the eye later darkens',
                'body' => 'Jade / reduced eumelanin, stronger in females',
                'wings' => 'Reduced eumelanin',
                'pigment_notes' => 'Eumelanin reduction that is stronger in females. Legs stay nearer grey than a dilute.',
            ],
            'recessive pied' => [
                'head' => 'Clear patches on an otherwise colored bird',
                'body' => 'Leucistic pied patches',
                'wings' => 'Clear and colored patches',
                'tail' => 'Pied patches possible',
                'pigment_notes' => 'Leucistic pied. Visual only when homozygous. Not dark-eyed clear.',
            ],
            'dominant pied' => [
                'head' => 'Pied clear patches',
                'body' => 'Pied with one Pi',
                'wings' => 'Pied patches',
                'pigment_notes' => 'Pied with one Pi. Not recessive pied.',
            ],
            'orange face' => [
                'head' => 'Orange face',
                'pigment_notes' => 'Facial psittacin becomes orange. Not a visible face color on a psittacin-free blue ground.',
            ],
            'dark eyed clear' => [
                'eyes' => 'Dark',
                'head' => 'Clear / yellow-white',
                'body' => 'Clear plumage',
                'wings' => 'Clear',
                'rump' => 'Clear / pale',
                'tail' => 'Clear / pale',
                'pigment_notes' => 'Clear plumage with dark eyes. Not NSL Ino.',
            ],
            'pastel' => [
                'eyes' => 'Dark',
                'body' => 'Pastel / partial eumelanin reduction',
                'wings' => 'Pastel-diluted',
                'pigment_notes' => 'Partial eumelanin reduction with dark eyes. Not dilute.',
            ],
            'dominant edged' => [
                'wings' => 'Edged / spangled pattern',
                'body' => 'Eumelanin reduced toward an edged pattern',
                'pigment_notes' => 'Eumelanin is reduced to an edged or spangled pattern.',
            ],
            'dominant edged df' => [
                'head' => 'Smaller mask / reduced face pigment',
                'body' => 'Stronger melanin loss than one Ed',
                'wings' => 'Strong edged / spangled pattern',
                'pigment_notes' => 'Same edged mutation, homozygous. Stronger melanin loss and a smaller mask than one Ed.',
            ],
            'euwing' => [
                'body' => 'Body melanin reduced relative to the wings',
                'wings' => 'Wings keep more melanin than the body',
                'pigment_notes' => 'Melanin is retained more strongly on the wings than on the body.',
            ],
            'euwing df' => [
                'body' => 'Stronger body-to-wing contrast than one Ew',
                'wings' => 'Wings keep melanin; body paler',
                'pigment_notes' => 'Same euwing mutation, homozygous. Stronger contrast than one Ew.',
            ],
            'misty' => [
                'body' => 'Misted / slightly dulled color',
                'wings' => 'Misted',
                'pigment_notes' => 'Incomplete dominant misting of the colour. One Mt.',
            ],
            'misty df' => [
                'body' => 'Stronger misting / faded olive-green',
                'wings' => 'Stronger misting',
                'pigment_notes' => 'Same misty mutation, homozygous. Stronger effect than one Mt.',
            ],
            'slaty' => [
                'body' => 'Slaty / steel wash',
                'wings' => 'Steel wash',
                'rump' => 'Slaty structural color',
                'pigment_notes' => 'Slaty or steel wash. Not grey factor.',
            ],
            'sl greywing' => [
                'wings' => 'Greywing / partial eumelanin reduction',
                'body' => 'Partially reduced eumelanin',
                'pigment_notes' => 'Partial eumelanin reduction. Sex-linked incomplete dominant greywing.',
            ],
            'sl greywing df' => [
                'wings' => 'Stronger greywing than one Grw',
                'body' => 'Stronger eumelanin reduction than one Grw',
                'pigment_notes' => 'Same SL greywing mutation, homozygous male. Stronger reduction than one Grw.',
            ],
            'dominant reduced' => [
                'body' => 'Variable eumelanin reduction',
                'wings' => 'Variable reduction, not a pied pattern',
                'pigment_notes' => 'Variable eumelanin reduction, not a pied pattern and not misty.',
            ],
        ];
    }

    /**
     * @param  list<string>  $mutationNames
     * @return array<string, mixed>|null
     */
    public static function compose(?int $speciesId, array $mutationNames = [], mixed $baseColor = null, mixed $sex = null): ?array
    {
        $identity = self::identityFor($speciesId);
        if ($identity === null) {
            return null;
        }

        $map = self::regionSlice($identity);
        $notes = array_filter([(string) ($identity['pigment_notes'] ?? '')]);
        $visibleMutations = [];

        $baseName = self::baseColorName($baseColor);
        if ($baseName !== null) {
            $map = self::applyBaseColor($map, $baseName, $speciesId);
        }

        $hasIno = self::hasIno($mutationNames);
        $isBlue = self::isBlueGroundName($baseName);

        foreach ($mutationNames as $name) {
            $key = self::normalizeName((string) $name);
            if ($key === '') {
                continue;
            }
            if ($hasIno && ($key === 'violet' || $key === 'double violet')) {
                $notes[] = 'Ino covers the violet wash, so violet is not a separate visible color.';
                continue;
            }
            if ($isBlue && ($key === 'orange face' || $key === 'pale headed' || $key === 'pale headed df')) {
                $notes[] = $name.' is not a visible mutation on a blue ground.';
                continue;
            }

            $overlay = self::mutationOverlays()[$key] ?? null;
            if ($overlay === null) {
                continue;
            }

            $overlay = self::adjustOverlayForSpecies($speciesId, $key, $overlay, $isBlue, $sex);
            $map = self::applyOverlay($map, $overlay);
            if (! empty($overlay['pigment_notes'])) {
                $notes[] = $overlay['pigment_notes'];
            }
            $visibleMutations[] = (string) $name;
        }

        $map['pigment_notes'] = implode(' ', array_unique($notes));
        $map['source'] = 'Species identity plus stored visual-mutation pigment maps.';
        $map['species_id'] = $speciesId;
        $map['scientific_name'] = $identity['scientific_name'] ?? null;
        $map['common_name'] = $identity['common_name'] ?? null;
        $map['visual_mutations'] = $visibleMutations;
        $map['kind'] = $visibleMutations === [] ? 'species_identity' : 'visual_mutation';

        return $map;
    }

    /**
     * One stored color for each region of this mutation on this species.
     *
     * @return array<string, mixed>|null
     */
    public static function uniqueForMutation(?int $speciesId, string $mutationName): ?array
    {
        $map = self::compose($speciesId, [$mutationName]);
        if ($map === null || trim($mutationName) === '') {
            return $map;
        }

        $colors = self::mutationColors($speciesId, $mutationName);
        if ($colors === null) {
            return $map;
        }

        foreach (self::REGIONS as $region) {
            $map[$region] = $colors[$region];
        }

        $map['source'] = 'Unique head-to-tail color for this visual mutation on this species.';
        $map['phenotype_signature'] = self::signature($map);

        return $map;
    }

    /**
     * @return array<string, string>
     */
    public static function colorPalette(): array
    {
        return [
            'Dark brown' => '#4A3428',
            'Red-brown' => '#8C4A32',
            'Dark red' => '#8E2430',
            'Red' => '#D64545',
            'Peach' => '#F3B183',
            'Orange' => '#F07820',
            'Orange-red' => '#E25822',
            'Salmon' => '#F08B78',
            'Scarlet' => '#E23B3B',
            'Black' => '#1C1C1C',
            'Black-brown' => '#3D2B24',
            'Brown' => '#8A5A32',
            'Yellow' => '#F0D040',
            'Pale yellow' => '#F6E7A8',
            'Cream' => '#F3E6C4',
            'White' => '#F7F7F5',
            'Olive' => '#8A8A32',
            'Yellow-olive' => '#A89A3A',
            'Yellow-green' => '#C5D15A',
            'Green' => '#3C9A45',
            'Grass green' => '#62B84A',
            'Bright green' => '#2FBF55',
            'Pale green' => '#A8D48A',
            'Pale grass green' => '#B7D97A',
            'Pale bright green' => '#8ED98A',
            'Light green' => '#C5E6A8',
            'Soft green' => '#9FCB8A',
            'Sage' => '#8EAE78',
            'Jade' => '#6FBF8A',
            'Mint' => '#A8E0C0',
            'Khaki' => '#C4B483',
            'Dun' => '#A89070',
            'Lime' => '#C6E05A',
            'Chartreuse' => '#D4E157',
            'Olive brown' => '#8A7A3A',
            'Grey-green' => '#7D8A72',
            'Grey' => '#8E8E8E',
            'Pale grey' => '#D5D5D0',
            'Dark grey' => '#5C5C5C',
            'Slate' => '#5E6A78',
            'Steel' => '#6A7A8A',
            'Sky blue' => '#6EC4F0',
            'Pale sky blue' => '#B9E3F8',
            'Blue' => '#3A78D8',
            'Pale blue' => '#9EC4F0',
            'Blue-green' => '#3A9A8A',
            'Turquoise' => '#3AABB8',
            'Violet' => '#7A52C8',
            'Deep violet' => '#542E96',
            'Grey-blue' => '#7A8AA8',
            'Charcoal' => '#3A3A3A',
            'Green-white' => '#E7F2E4',
            'Cream-green' => '#E4E8C8',
            'Light orange' => '#F6B089',
            'Red-orange' => '#E25B2A',
        ];
    }

    /**
     * @param  array<string, mixed>  $map
     */
    public static function signature(array $map): string
    {
        $parts = [];
        foreach (self::REGIONS as $region) {
            $parts[] = self::normalizeName((string) ($map[$region] ?? ''));
        }

        return sha1(implode('|', $parts));
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function identityFor(?int $speciesId): ?array
    {
        if ($speciesId === null) {
            return null;
        }

        return self::speciesIdentities()[$speciesId] ?? null;
    }

    public static function resolveSpeciesId(mixed $species): ?int
    {
        if (is_int($species) || (is_string($species) && ctype_digit($species))) {
            $id = (int) $species;

            return self::identityFor($id) ? $id : null;
        }

        if (is_array($species)) {
            if (isset($species['id']) && self::identityFor((int) $species['id'])) {
                return (int) $species['id'];
            }
            $fromName = self::idFromName($species['scientific_name'] ?? $species['common_name'] ?? $species['name'] ?? null);
            if ($fromName !== null) {
                return $fromName;
            }
        }

        if (is_object($species)) {
            if (isset($species->id) && self::identityFor((int) $species->id)) {
                return (int) $species->id;
            }
            return self::idFromName($species->scientific_name ?? $species->common_name ?? $species->name ?? null);
        }

        if (is_string($species)) {
            return self::idFromName($species);
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    public static function overlaysPayload(): array
    {
        $payload = [];
        foreach (self::mutationOverlays() as $key => $overlay) {
            $payload[$key] = $overlay;
        }

        return $payload;
    }

    /**
     * @return list<string>
     */
    public static function knownMutationNames(): array
    {
        return array_keys(self::mutationOverlays());
    }

    private static function idFromName(mixed $name): ?int
    {
        $key = self::normalizeName((string) $name);
        if ($key === '') {
            return null;
        }

        $aliases = [
            'agapornis roseicollis' => 1,
            'rosy-faced lovebird' => 1,
            'peach-faced lovebird' => 1,
            'peach faced lovebird' => 1,
            'agapornis fischeri' => 2,
            "fischer's lovebird" => 2,
            'fischers lovebird' => 2,
            'agapornis personatus' => 3,
            'yellow-collared lovebird' => 3,
            'yellow collared lovebird' => 3,
            'masked lovebird' => 3,
            'agapornis nigrigenis' => 4,
            'black-cheeked lovebird' => 4,
            'black cheeked lovebird' => 4,
            'agapornis lilianae' => 5,
            "lilian's lovebird" => 5,
            'lilians lovebird' => 5,
            'nyasa lovebird' => 5,
            'agapornis taranta' => 6,
            'black-winged lovebird' => 6,
            'black winged lovebird' => 6,
            'abyssinian lovebird' => 6,
            'agapornis pullarius' => 7,
            'red-headed lovebird' => 7,
            'red headed lovebird' => 7,
            'red-faced lovebird' => 7,
            'agapornis canus' => 8,
            'grey-headed lovebird' => 8,
            'grey headed lovebird' => 8,
            'madagascar lovebird' => 8,
            'agapornis swindernianus' => 9,
            'black-collared lovebird' => 9,
            'black collared lovebird' => 9,
            "swindern's lovebird" => 9,
        ];

        return $aliases[$key] ?? null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, string>
     */
    private static function regionSlice(array $row): array
    {
        $slice = [];
        foreach (self::REGIONS as $region) {
            $slice[$region] = (string) ($row[$region] ?? '');
        }

        return $slice;
    }

    /**
     * @param  array<string, string>  $map
     * @param  array<string, string>  $overlay
     * @return array<string, string>
     */
    private static function applyOverlay(array $map, array $overlay): array
    {
        foreach (self::REGIONS as $region) {
            if (! empty($overlay[$region])) {
                $map[$region] = $overlay[$region];
            }
        }

        return $map;
    }

    /**
     * @param  array<string, string>  $overlay
     * @return array<string, string>
     */
    private static function adjustOverlayForSpecies(?int $speciesId, string $key, array $overlay, bool $isBlue, mixed $sex): array
    {
        $isIno = $key === 'sl ino' || $key === 'nsl ino';
        $isCinnamon = $key === 'cinnamon';
        $isFallow = in_array($key, ['bronze fallow', 'pale fallow', 'dun fallow'], true);

        if ($isIno && $isBlue) {
            $overlay['body'] = 'White (albino — same ino allele on a blue ground)';
            $overlay['wings'] = 'White';
            $overlay['rump'] = 'White';
            $overlay['tail'] = 'White';
        }

        if ($isIno) {
            $overlay['neck'] = match ($speciesId) {
                3 => 'Yellow collar remains (psittacin)',
                9 => 'Black collar fades (eumelanin lost)',
                1 => 'Green nape, no collar',
                2 => 'Pale nape, no yellow collar',
                4 => 'Pale greenish-yellow, no bold collar',
                5 => 'Pale green, no yellow collar',
                8 => 'Grey neck fades',
                default => $overlay['neck'] ?? '',
            };
            $overlay['head'] = match ($speciesId) {
                1 => 'Pale yellow (peach face fades)',
                2 => 'Yellow (orange face fades); white eye ring remains',
                3 => 'Yellow (black mask lost); white eye ring remains',
                4 => 'Yellow (black cheeks fade); white eye ring remains',
                5 => 'Yellow (salmon face fades); white eye ring remains',
                6 => 'Pale yellow (red forehead fades)',
                7 => 'Yellow (scarlet face fades)',
                8 => 'Pale yellow (grey head fades)',
                9 => 'Yellow-green (eumelanin lost)',
                default => $overlay['head'] ?? 'Yellow (eumelanin lost)',
            };
            $overlay['eyes'] = match ($speciesId) {
                2, 3, 4, 5 => 'Red with a white eye ring',
                default => 'Red',
            };
            if ($speciesId === 6) {
                $overlay['wings'] = $isBlue ? 'White (black flights fade)' : 'Pale yellow (black flights fade)';
                $overlay['rump'] = $isBlue ? 'White' : 'Pale yellow (green rump fades)';
            }
            if ($speciesId === 9) {
                $overlay['rump'] = $isBlue ? 'White (red-orange rump fades)' : 'Pale yellow (red-orange rump fades)';
            }
        }

        if ($isCinnamon) {
            $overlay['head'] = match ($speciesId) {
                3 => 'Brown mask (cinnamon eumelanin)',
                4 => 'Brown cheeks (cinnamon eumelanin)',
                default => $overlay['head'] ?? '',
            };
            if ($speciesId === 3) {
                $overlay['neck'] = 'Bright yellow collar';
            }
            if ($speciesId === 6) {
                $overlay['wings'] = 'Brownish flight feathers';
            }
            if ($speciesId === 9) {
                $overlay['neck'] = 'Brown collar band (cinnamon eumelanin)';
            }
        }

        if ($isFallow && $speciesId === 6) {
            $overlay['wings'] = 'Brownish rather than black flights';
            $overlay['head'] = 'Male red forehead is lighter';
        }

        if ($key === 'orange face' && $speciesId === 1) {
            $overlay['head'] = 'Orange face (peach becomes orange)';
        }

        if ($key === 'pale headed' && $speciesId === 1) {
            $overlay['head'] = 'Reduced peach mask';
        }

        if ($key === 'pale headed df' && $speciesId === 1) {
            $overlay['head'] = 'Strongly reduced / clear peach face';
        }

        if ($key === 'dominant edged df' && in_array($speciesId, [2, 3, 4, 5], true)) {
            $overlay['head'] = 'Smaller mask than one Ed';
        }

        if ($key === 'dm jade' && self::isFemale($sex)) {
            $overlay['body'] = 'Jade / stronger eumelanin reduction in females';
        }

        if ($speciesId === 6 && ($key === 'misty df')) {
            $overlay['body'] = 'Faded olive-green with a light brown wash';
        }

        if (in_array($speciesId, [2, 3, 4, 5], true) && isset($overlay['eyes']) && ! str_contains(strtolower($overlay['eyes']), 'eye ring')) {
            if (! $isIno && preg_match('/^dark/i', $overlay['eyes']) === 1) {
                $overlay['eyes'] = rtrim($overlay['eyes'], '.').' with a white eye ring';
            }
        }

        return $overlay;
    }

    /**
     * @param  array<string, string>  $map
     * @return array<string, string>
     */
    private static function applyBaseColor(array $map, string $baseName, ?int $speciesId): array
    {
        $key = self::normalizeName($baseName);
        if ($key === '' || $key === 'green' || str_contains($key, 'wild')) {
            return $map;
        }

        if (preg_match('/turquoise|cobalt|mauve|seagreen|olive aqua|olive|aqua|\bblue\b|dark green/i', $baseName, $hit) === 1) {
            $label = ucfirst(strtolower($hit[0]));
            $map['body'] = $label;
            if (! preg_match('/ino|lutino|albino/i', $map['body'])) {
                $map['wings'] = $label;
            }
        }

        if (self::isBlueGroundName($baseName)) {
            if ($speciesId === 1) {
                $map['head'] = 'No peach face (psittacin-free blue ground)';
            }
            if ($speciesId === 3) {
                $map['neck'] = 'Collar fades (yellow collar is psittacin)';
                $map['body'] = $map['body'] === 'Green with a yellow breast'
                    ? 'Blue (yellow breast fades)'
                    : $map['body'];
            }
        }

        return $map;
    }

    /**
     * @param  list<string>  $mutationNames
     */
    private static function hasIno(array $mutationNames): bool
    {
        foreach ($mutationNames as $name) {
            $key = self::normalizeName((string) $name);
            if ($key === 'sl ino' || $key === 'nsl ino') {
                return true;
            }
        }

        return false;
    }

    private static function isBlueGroundName(?string $name): bool
    {
        if ($name === null || $name === '') {
            return false;
        }

        return (bool) preg_match('/\b(blue|cobalt|mauve)\b/i', $name);
    }

    private static function baseColorName(mixed $baseColor): ?string
    {
        if (is_string($baseColor) && trim($baseColor) !== '') {
            return trim($baseColor);
        }
        if (is_array($baseColor)) {
            $name = $baseColor['name'] ?? $baseColor['base_color'] ?? null;

            return is_string($name) && trim($name) !== '' ? trim($name) : null;
        }

        return null;
    }

    private static function isFemale(mixed $sex): bool
    {
        $value = strtolower((string) $sex);

        return $value === 'hen' || $value === 'female';
    }

    /**
     * @return array<string, string>|null
     */
    private static function mutationColors(?int $speciesId, string $mutationName): ?array
    {
        $colors = self::speciesColors($speciesId);
        if ($colors === null) {
            return null;
        }

        $key = self::normalizeName($mutationName);
        $patch = self::mutationColorPatch($speciesId, $key, $colors);
        foreach ($patch as $region => $color) {
            $colors[$region] = $color;
        }

        return $colors;
    }

    /**
     * @return array<string, string>|null
     */
    private static function speciesColors(?int $speciesId): ?array
    {
        return match ($speciesId) {
            1 => ['eyes' => 'Dark brown', 'head' => 'Peach', 'neck' => 'Green', 'body' => 'Grass green', 'wings' => 'Green', 'rump' => 'Sky blue', 'tail' => 'Blue-green'],
            2 => ['eyes' => 'Dark brown', 'head' => 'Orange-red', 'neck' => 'Yellow-olive', 'body' => 'Bright green', 'wings' => 'Green', 'rump' => 'Blue', 'tail' => 'Green'],
            3 => ['eyes' => 'Dark brown', 'head' => 'Black', 'neck' => 'Yellow', 'body' => 'Green', 'wings' => 'Green', 'rump' => 'Blue', 'tail' => 'Green'],
            4 => ['eyes' => 'Dark brown', 'head' => 'Black-brown', 'neck' => 'Yellow-green', 'body' => 'Green', 'wings' => 'Green', 'rump' => 'Blue', 'tail' => 'Green'],
            5 => ['eyes' => 'Dark brown', 'head' => 'Salmon', 'neck' => 'Green', 'body' => 'Green', 'wings' => 'Green', 'rump' => 'Blue', 'tail' => 'Green'],
            6 => ['eyes' => 'Dark brown', 'head' => 'Red', 'neck' => 'Green', 'body' => 'Green', 'wings' => 'Black', 'rump' => 'Green', 'tail' => 'Green'],
            7 => ['eyes' => 'Dark brown', 'head' => 'Scarlet', 'neck' => 'Green', 'body' => 'Green', 'wings' => 'Green', 'rump' => 'Blue', 'tail' => 'Green'],
            8 => ['eyes' => 'Dark brown', 'head' => 'Grey', 'neck' => 'Grey', 'body' => 'Green', 'wings' => 'Green', 'rump' => 'Green', 'tail' => 'Green'],
            9 => ['eyes' => 'Dark brown', 'head' => 'Green', 'neck' => 'Black', 'body' => 'Green', 'wings' => 'Green', 'rump' => 'Red-orange', 'tail' => 'Green'],
            default => null,
        };
    }

    /**
     * @param  array<string, string>  $colors
     * @return array<string, string>
     */
    private static function mutationColorPatch(?int $speciesId, string $key, array $colors): array
    {
        $inoHead = match ($speciesId) {
            1, 6, 8 => 'Pale yellow',
            9 => 'Yellow-green',
            default => 'Yellow',
        };
        $inoNeck = match ($speciesId) {
            1, 6, 7 => $colors['neck'],
            2 => 'Pale yellow',
            3 => 'Yellow',
            4 => 'Pale green',
            5 => 'Soft green',
            8 => 'Pale grey',
            9 => 'Pale yellow',
            default => 'Pale yellow',
        };
        $diluteBody = match ($speciesId) {
            1 => 'Pale grass green',
            2 => 'Pale bright green',
            4 => 'Light green',
            5 => 'Soft green',
            6 => 'Sage',
            default => 'Pale green',
        };
        $diluteRump = match ($colors['rump']) {
            'Sky blue' => 'Pale sky blue',
            'Blue' => 'Pale blue',
            'Red-orange' => 'Light orange',
            'Green' => 'Pale green',
            default => 'Pale blue',
        };

        return match ($key) {
            'cinnamon' => [
                'eyes' => 'Red-brown',
                'head' => in_array($speciesId, [3, 4], true) ? 'Brown' : $colors['head'],
                'neck' => $speciesId === 9 ? 'Brown' : $colors['neck'],
                'body' => 'Yellow-green',
                'wings' => $speciesId === 6 ? 'Brown' : 'Olive brown',
            ],
            'sl ino', 'nsl ino' => [
                'eyes' => 'Red',
                'head' => $inoHead,
                'neck' => $inoNeck,
                'body' => 'Yellow',
                'wings' => $speciesId === 6 ? 'Pale yellow' : 'Yellow',
                'rump' => 'Pale yellow',
                'tail' => 'Pale yellow',
            ],
            'pallid' => [
                'body' => 'Light green',
                'wings' => 'Olive brown',
            ],
            'pale' => [
                'body' => 'Soft green',
                'wings' => 'Pale green',
            ],
            'opaline' => [
                'rump' => match ($colors['rump']) {
                    'Sky blue' => 'Turquoise',
                    'Blue' => 'Blue-green',
                    'Red-orange' => 'Orange',
                    default => 'Olive',
                },
            ],
            'violet' => [
                'body' => 'Violet',
                'rump' => 'Violet',
            ],
            'double violet' => [
                'body' => 'Deep violet',
                'rump' => 'Deep violet',
            ],
            'pale headed' => [
                'head' => $speciesId === 1 ? 'Cream' : 'Pale yellow',
            ],
            'pale headed df' => [
                'head' => 'White',
            ],
            'grey factor' => [
                'body' => 'Grey-green',
                'wings' => 'Grey-green',
                'rump' => 'Grey-blue',
            ],
            'grey factor df' => [
                'body' => 'Grey',
                'wings' => 'Grey',
                'rump' => 'Grey',
            ],
            'bronze fallow' => [
                'eyes' => 'Dark red',
                'head' => $speciesId === 6 ? 'Salmon' : $colors['head'],
                'body' => 'Khaki',
                'wings' => 'Brown',
            ],
            'pale fallow' => [
                'eyes' => 'Red',
                'head' => $speciesId === 6 ? 'Salmon' : $colors['head'],
                'body' => 'Pale yellow',
                'wings' => $speciesId === 6 ? 'Brown' : 'Pale yellow',
            ],
            'dun fallow' => [
                'eyes' => 'Red-brown',
                'body' => 'Dun',
                'wings' => 'Brown',
            ],
            'dilute' => [
                'body' => $diluteBody,
                'wings' => $speciesId === 6 ? 'Charcoal' : 'Pale green',
                'rump' => $diluteRump,
            ],
            'marbled' => [
                'body' => 'Lime',
                'wings' => 'Lime',
            ],
            'dm jade' => [
                'eyes' => 'Red-brown',
                'body' => 'Jade',
                'wings' => 'Pale green',
            ],
            'recessive pied' => [
                'head' => 'White',
                'body' => 'White',
                'wings' => 'White',
                'tail' => 'White',
            ],
            'dominant pied' => [
                'head' => 'Cream',
                'body' => 'Cream-green',
                'wings' => 'Pale green',
            ],
            'orange face' => [
                'head' => 'Orange',
            ],
            'dark eyed clear' => [
                'head' => 'Cream',
                'body' => 'Yellow',
                'wings' => 'Yellow',
                'rump' => 'Pale yellow',
                'tail' => 'Pale yellow',
            ],
            'pastel' => [
                'body' => 'Chartreuse',
                'wings' => 'Yellow-green',
            ],
            'dominant edged' => [
                'body' => 'Mint',
                'wings' => 'Mint',
            ],
            'dominant edged df' => [
                'head' => in_array($speciesId, [2, 3, 4, 5], true) ? 'Cream' : $colors['head'],
                'body' => 'Pale yellow',
                'wings' => 'Cream',
            ],
            'euwing' => [
                'body' => 'Pale green',
            ],
            'euwing df' => [
                'body' => 'Cream',
            ],
            'misty' => [
                'body' => 'Grey-green',
                'wings' => $speciesId === 6 ? 'Charcoal' : 'Grey-green',
            ],
            'misty df' => [
                'body' => $speciesId === 6 ? 'Khaki' : 'Olive',
                'wings' => $speciesId === 6 ? 'Dark grey' : 'Olive',
            ],
            'slaty' => [
                'body' => 'Steel',
                'wings' => 'Steel',
                'rump' => 'Slate',
            ],
            'sl greywing' => [
                'body' => 'Pale green',
                'wings' => 'Grey',
            ],
            'sl greywing df' => [
                'body' => 'Light green',
                'wings' => 'Dark grey',
            ],
            'dominant reduced' => [
                'body' => 'Yellow-green',
                'wings' => 'Yellow-green',
            ],
            default => [],
        };
    }

    public static function normalizeName(string $name): string
    {
        $value = strtolower(trim($name));
        $value = str_replace(['’', '`'], "'", $value);
        $value = preg_replace("/[^a-z0-9*+\s'-]+/", ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }
}

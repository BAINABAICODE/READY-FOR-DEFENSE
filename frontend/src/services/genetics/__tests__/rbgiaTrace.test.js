import { describe, expect, it } from 'vitest'
import { buildRbgiaTrace, groupVisualOutcomes, sumProbabilities } from '../index'

/** Shapes copied from a live GET /api/computation-results/{id} response (values, not invented). */
const storedGreywing = [
  { genotype: 'Grw+/Grw+', sex: 'cock', probability: 0.25, expression: 'non_carrier', phenotype: 'Wild' },
  { genotype: 'Grw+/Grw', sex: 'cock', probability: 0.25, expression: 'visual_single_factor', phenotype: 'SL Greywing SF' },
  { genotype: 'Grw+/W', sex: 'hen', probability: 0.25, expression: 'hemizygous_wild', phenotype: 'Wild' },
  { genotype: 'Grw/W', sex: 'hen', probability: 0.25, expression: 'visual_hemizygous', phenotype: 'SL Greywing' },
]

const result = {
  parent_snapshot: {
    parent_1: { bird_id: 'TEST 3', sex: 'hen', grandparents_recorded: 1, visual_mutations: [{ name: 'Dilute' }], split_genes: [{ name: 'Split Pale Fallow' }] },
    parent_2: { bird_id: 'TEST 2', sex: 'cock', grandparents_recorded: 0, visual_mutations: [{ name: 'Opaline' }], split_genes: [] },
  },
  rbgia_data: {
    theoretical_distribution: [
      { probability: 0.25, fraction: '1/4', sex: 'cock', sex_label: 'Male / Cock', genotype: 'Dark factor (D):D+/D+ | SL Greywing:Grw+/Grw+', base_color: 'Green', visual_mutations: [], split_hidden_genes: [], loci: [{ name: 'Dark factor (D)', probability: 0.5, fraction: '1/2' }, { name: 'SL Greywing', probability: 0.5, fraction: '1/2' }], outcome_key: 'a' },
      { probability: 0.25, fraction: '1/4', sex: 'cock', sex_label: 'Male / Cock', genotype: 'Dark factor (D):D+/D | SL Greywing:Grw+/Grw+', base_color: 'Dark Green', visual_mutations: [], split_hidden_genes: [], loci: [{ name: 'Dark factor (D)', probability: 0.5, fraction: '1/2' }, { name: 'SL Greywing', probability: 0.5, fraction: '1/2' }], outcome_key: 'b' },
      { probability: 0.25, fraction: '1/4', sex: 'hen', sex_label: 'Female / Hen', genotype: 'Dark factor (D):D+/D+ | SL Greywing:Grw/W', base_color: 'Green', visual_mutations: ['SL Greywing'], split_hidden_genes: [], loci: [{ name: 'Dark factor (D)', probability: 0.5, fraction: '1/2' }, { name: 'SL Greywing', probability: 0.5, fraction: '1/2' }], outcome_key: 'c' },
      { probability: 0.25, fraction: '1/4', sex: 'hen', sex_label: 'Female / Hen', genotype: 'Dark factor (D):D+/D | SL Greywing:Grw/W', base_color: 'Dark Green', visual_mutations: ['SL Greywing'], split_hidden_genes: [], loci: [{ name: 'Dark factor (D)', probability: 0.5, fraction: '1/2' }, { name: 'SL Greywing', probability: 0.5, fraction: '1/2' }], outcome_key: 'd' },
    ],
  },
  egg_outcomes: [{ outcome_key: 'c', egg_number: 3, image: { image_url: 'https://example.test/c.png' } }],
  result_presentation: {
    species_compatibility: { same_species: true, status: 'SAME_SPECIES' },
    data_confidence: { level: 'CONFIRMED', notes: [] },
    algorithm: { method: 'AGAPORA-RBGIA-GICA-v3', deterministic: true },
    genetic_analysis: {
      outcomes: [
        {
          category: 'base_color', name: 'Dark factor (D)', locus_key: 'dark_factor', inheritance_type: 'Intermediate dominant', status: 'calculated',
          parent_contribution: { parent_cock: { record: 'Green', code: 'D+/D+' }, parent_hen: { record: 'Dark Green', code: 'D+/D' } },
          punnett: { parent_1_alleles: ['D+', 'D+'], parent_2_alleles: ['D+', 'D'], sex_linked: false },
          results: [
            { genotype: 'D+/D+', sex: 'both', probability: 0.5, expression: 'non_carrier', phenotype: 'No dark factor' },
            { genotype: 'D+/D', sex: 'both', probability: 0.5, expression: 'visual_single_factor', phenotype: 'Single dark factor (SF)' },
          ],
        },
        {
          category: 'visual_mutation', name: 'SL Greywing', locus_key: 'visual:43', inheritance_type: 'Sex-linked incomplete dominant', status: 'calculated',
          parent_contribution: { parent_cock: { record: 'SL Greywing', code: 'Grw+/Grw' }, parent_hen: { record: 'Documented non-carrier (not selected)', code: 'Grw+/W', assumed_non_carrier: true } },
          punnett: { parent_1_alleles: ['Grw+', 'Grw'], parent_2_alleles: ['Grw+', 'W'], sex_linked: true },
          results: storedGreywing,
        },
      ],
    },
  },
}

describe('buildRbgiaTrace on a live-shaped payload', () => {
  const trace = buildRbgiaTrace(result)

  it('reconstructs every locus and verifies it against the stored results', () => {
    expect(trace.loci).toHaveLength(2)
    trace.loci.forEach((locus) => expect(locus.verified).toBe(true))
    expect(trace.summary.allVerified).toBe(true)
  })

  it('identifies cock/hen slots from the parent snapshot', () => {
    expect(trace.roles.cock).toEqual({ slot: 'Parent 2', birdId: 'TEST 2' })
    expect(trace.roles.hen).toEqual({ slot: 'Parent 1', birdId: 'TEST 3' })
  })

  it('builds a ZW square for the sex-linked locus with sons and daughters', () => {
    const greywing = trace.loci.find((l) => l.name === 'SL Greywing')
    expect(greywing.sexLinked).toBe(true)
    expect(greywing.square.cells.filter((c) => c.sex === 'cock')).toHaveLength(2)
    expect(greywing.square.cells.filter((c) => c.sex === 'hen')).toHaveLength(2)
    expect(greywing.chromosomes.cock.karyotype).toBe('ZZ')
    expect(greywing.chromosomes.hen.karyotype).toBe('ZW')
    expect(greywing.outcomes.map((o) => o.sexLinkedClass).sort()).toEqual(['non-carrier male', 'non-visual female', 'visual female', 'visual male'].sort())
  })

  it('joint outcomes sum to exactly 100% and carry the product-of-loci formula', () => {
    expect(sumProbabilities(trace.joint.rows)).toBeCloseTo(1, 12)
    trace.joint.rows.forEach((row) => expect(row.productMatches).toBe(true))
    expect(trace.joint.rows[0].formula).toBe('1/2 × 1/2 = 1/4')
  })

  it('links stored egg images to the matching outcome without altering genetics', () => {
    const withImage = trace.joint.rows.find((r) => r.id === 'c')
    expect(withImage.imageUrl).toBe('https://example.test/c.png')
    expect(withImage.probability).toBe(0.25)
  })

  it('groups visual outcomes and keeps totals at 100%', () => {
    const grouped = groupVisualOutcomes(trace.joint.rows, { includeSex: false })
    expect(sumProbabilities(grouped)).toBeCloseTo(1, 12)
    expect(grouped[0].rank).toBe(1)
  })

  it('produces a clutch interpretation with ranges that never exceed the clutch size', () => {
    trace.clutch.forEach((clutch) => clutch.rows.forEach((row) => {
      expect(row.low).toBeGreaterThanOrEqual(0)
      expect(row.high).toBeLessThanOrEqual(clutch.size)
      expect(row.low).toBeLessThanOrEqual(row.high)
    }))
  })
})

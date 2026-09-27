import { describe, expect, it } from 'vitest'
import { buildComplexityReport } from '../complexityAnalyzer'
import { buildRbgiaTrace } from '../rbgiaTrace'

const storedGreywing = [
  { genotype: 'Grw+/Grw+', sex: 'cock', probability: 0.25, expression: 'non_carrier', phenotype: 'Wild' },
  { genotype: 'Grw+/Grw', sex: 'cock', probability: 0.25, expression: 'visual_single_factor', phenotype: 'SL Greywing SF' },
  { genotype: 'Grw+/W', sex: 'hen', probability: 0.25, expression: 'hemizygous_wild', phenotype: 'Wild' },
  { genotype: 'Grw/W', sex: 'hen', probability: 0.25, expression: 'visual_hemizygous', phenotype: 'SL Greywing' },
]

const result = {
  rbgia_data: {
    theoretical_distribution: [
      { probability: 0.25, fraction: '1/4', sex: 'cock', genotype: 'a', loci: [{ name: 'Dark factor (D)', probability: 0.5 }, { name: 'SL Greywing', probability: 0.5 }], outcome_key: 'a' },
      { probability: 0.25, fraction: '1/4', sex: 'cock', genotype: 'b', loci: [{ name: 'Dark factor (D)', probability: 0.5 }, { name: 'SL Greywing', probability: 0.5 }], outcome_key: 'b' },
      { probability: 0.25, fraction: '1/4', sex: 'hen', genotype: 'c', loci: [{ name: 'Dark factor (D)', probability: 0.5 }, { name: 'SL Greywing', probability: 0.5 }], outcome_key: 'c' },
      { probability: 0.25, fraction: '1/4', sex: 'hen', genotype: 'd', loci: [{ name: 'Dark factor (D)', probability: 0.5 }, { name: 'SL Greywing', probability: 0.5 }], outcome_key: 'd' },
    ],
  },
  result_presentation: {
    gica: {
      score: 82,
      breakdown: [
        { key: 'species', factor: 'Species Compatibility', max_points: 25, points: 25 },
        { key: 'mutation', factor: 'Mutation Compatibility', max_points: 20, points: 16 },
      ],
    },
    clutch_simulation: {
      clutch_size: 2,
      eggs: [
        { egg_number: 1, status: 'living_chick', stages: [{}, {}, {}] },
        { egg_number: 2, status: 'unfertilized', stages: [{}] },
      ],
    },
    genetic_analysis: {
      outcomes: [
        {
          category: 'base_color', name: 'Dark factor (D)', locus_key: 'dark_factor', inheritance_type: 'Intermediate dominant', status: 'calculated',
          punnett: { parent_1_alleles: ['D+', 'D+'], parent_2_alleles: ['D+', 'D'], sex_linked: false },
          results: [
            { genotype: 'D+/D+', sex: 'both', probability: 0.5 },
            { genotype: 'D+/D', sex: 'both', probability: 0.5 },
          ],
        },
        {
          category: 'visual_mutation', name: 'SL Greywing', locus_key: 'visual:43', inheritance_type: 'Sex-linked incomplete dominant', status: 'calculated',
          punnett: { parent_1_alleles: ['Grw+', 'Grw'], parent_2_alleles: ['Grw+', 'W'], sex_linked: true },
          results: storedGreywing,
        },
      ],
    },
  },
}

describe('buildComplexityReport', () => {
  const report = buildComplexityReport(result, buildRbgiaTrace(result))

  it('counts Punnett work from each calculated locus', () => {
    const dark = report.calculations.find((row) => row.name === 'Dark factor (D)')
    const greywing = report.calculations.find((row) => row.name === 'SL Greywing')
    expect(dark.timeOps).toBe(4)
    expect(dark.unique).toBe(2)
    expect(greywing.timeOps).toBe(4)
    expect(greywing.unique).toBe(4)
    expect(report.variables.L).toBe(2)
    expect(report.variables.C).toBe(8)
  })

  it('uses the product of per-locus outcomes for joint time and retained rows for space', () => {
    const joint = report.calculations.find((row) => row.id === 'joint')
    expect(joint.productBound).toBe(8)
    expect(joint.spaceUnits).toBe(4)
    expect(report.time.substituted).toBe('O(8 + 8 + 2 + 2·3)')
    expect(report.space.substituted).toBe('O(8 + 4 + 2 + 2)')
  })

  it('includes every GICA factor and clutch egg from this result', () => {
    expect(report.variables.F).toBe(2)
    expect(report.variables.E).toBe(2)
    expect(report.variables.S).toBe(3)
    expect(report.calculations.some((row) => row.id === 'gica' && row.timeOps === 2)).toBe(true)
    expect(report.calculations.some((row) => row.id === 'clutch' && row.timeOps === 4)).toBe(true)
  })
})

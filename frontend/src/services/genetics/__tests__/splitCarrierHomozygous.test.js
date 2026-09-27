import { describe, expect, it } from 'vitest'
import { homozygousFromSplitCarriers } from '../splitCarrierHomozygous'

describe('homozygous visual chance from split carriers', () => {
  it('gives one quarter when both parents are autosomal split carriers', () => {
    const summary = homozygousFromSplitCarriers({
      cockAlleles: ['dil+', 'dil'],
      henAlleles: ['dil+', 'dil'],
      sexLinked: false,
      inheritanceType: 'Autosomal recessive',
      results: [
        { genotype: 'dil+/dil+', expression: 'non_carrier', probability: 0.25 },
        { genotype: 'dil+/dil', expression: 'carrier_split', probability: 0.5 },
        { genotype: 'dil/dil', expression: 'visual', probability: 0.25 },
      ],
    })

    expect(summary.fraction).toBe('1/4')
    expect(summary.formula).toBe('1/2 × 1/2 = 1/4')
    expect(summary.probability).toBeCloseTo(0.25)
  })

  it('keeps a sex-linked split cock at zero homozygous sons when the hen is wild type', () => {
    const summary = homozygousFromSplitCarriers({
      cockAlleles: ['op+', 'op'],
      henAlleles: ['op+', 'W'],
      sexLinked: true,
      inheritanceType: 'Sex-linked recessive',
      results: [
        { genotype: 'op+/op+', expression: 'non_carrier', probability: 0.25, sex: 'cock' },
        { genotype: 'op+/op', expression: 'carrier_split', probability: 0.25, sex: 'cock' },
        { genotype: 'op+/W', expression: 'hemizygous_wild', probability: 0.25, sex: 'hen' },
        { genotype: 'op/W', expression: 'visual_hemizygous', probability: 0.25, sex: 'hen' },
      ],
    })

    expect(summary.probability).toBe(0)
    expect(summary.hemizygous_visual_fraction).toBe('1/4')
    expect(summary.formula).toBe('1/2 × 1/2 × 0 = 0')
  })

  it('skips incomplete dominant heterozygotes because they are visual, not split carriers', () => {
    expect(homozygousFromSplitCarriers({
      cockAlleles: ['Ed+', 'Ed'],
      henAlleles: ['Ed+', 'Ed'],
      sexLinked: false,
      inheritanceType: 'Incomplete dominant',
      results: [{ genotype: 'Ed/Ed', expression: 'visual_double', probability: 0.25 }],
    })).toBeNull()
  })
})

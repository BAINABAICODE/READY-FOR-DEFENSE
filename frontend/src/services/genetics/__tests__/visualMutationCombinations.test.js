import { describe, expect, it } from 'vitest'
import { keepCombinableMutationIds, visualCombinationBlock } from '../visualMutationCombinations.js'

const cinnamon = { id: 1, name: 'Cinnamon', dosage_key: null, locus_key: null, inheritance_type: 'Sex-linked recessive' }
const opaline = { id: 2, name: 'Opaline', dosage_key: null, locus_key: null, inheritance_type: 'Sex-linked recessive' }
const nslIno = { id: 3, name: 'NSL Ino', dosage_key: null, locus_key: 'series:a locus', inheritance_type: 'Autosomal recessive' }
const dec = { id: 4, name: 'Dark Eyed Clear', dosage_key: null, locus_key: 'series:a locus', inheritance_type: 'Autosomal recessive' }
const pastel = { id: 5, name: 'Pastel', dosage_key: null, locus_key: 'series:a locus', inheritance_type: 'Autosomal recessive' }
const violet = { id: 6, name: 'Violet', dosage_key: 'V', dosage_rank: 1, locus_key: null, inheritance_type: 'Incomplete dominant' }
const doubleViolet = { id: 7, name: 'Double Violet', dosage_key: 'V', dosage_rank: 2, locus_key: null, inheritance_type: 'Incomplete dominant' }
const slIno = { id: 8, name: 'SL Ino', dosage_key: null, locus_key: 'series:Sex-linked ino locus', inheritance_type: 'Sex-linked recessive' }
const pallid = { id: 9, name: 'Pallid', dosage_key: null, locus_key: 'series:Sex-linked ino locus', inheritance_type: 'Sex-linked recessive' }
const dilute = { id: 10, name: 'Dilute', dosage_key: null, locus_key: null, inheritance_type: 'Autosomal recessive' }
const greywingDf = { id: 11, name: 'SL Greywing DF', dosage_key: 'Grw', dosage_rank: 2, locus_key: null, inheritance_type: 'Sex-linked incomplete dominant' }

describe('visualCombinationBlock', () => {
  it('allows mutations on different loci, including opaline and dilute', () => {
    expect(visualCombinationBlock(dilute, [opaline])).toBe('')
    expect(visualCombinationBlock(cinnamon, [opaline])).toBe('')
    expect(visualCombinationBlock(opaline, [nslIno])).toBe('')
    expect(visualCombinationBlock(dec, [nslIno])).toBe('')
    expect(visualCombinationBlock(opaline, [nslIno, dec, dilute])).toBe('')
  })

  it('blocks a third allele of one locus and both doses of one mutation', () => {
    expect(visualCombinationBlock(pastel, [nslIno, dec])).toMatch(/only two alleles of this locus/)
    expect(visualCombinationBlock(doubleViolet, [violet])).toMatch(/single-factor or the double-factor/)
  })

  it('gives a hen one Z chromosome', () => {
    expect(visualCombinationBlock(pallid, [slIno], 'cock')).toBe('')
    expect(visualCombinationBlock(pallid, [slIno], 'hen')).toMatch(/one Z chromosome/)
    expect(visualCombinationBlock(greywingDf, [], 'cock')).toBe('')
    expect(visualCombinationBlock(greywingDf, [], 'hen')).toMatch(/double-factor form/)
    expect(keepCombinableMutationIds([slIno.id, pallid.id], [slIno, pallid], 'hen')).toEqual([slIno.id])
  })
})

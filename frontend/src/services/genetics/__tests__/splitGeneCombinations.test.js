import { describe, expect, it } from 'vitest'
import { baseColorBlock, keepCombinableGeneIds, splitGeneBlock, visualSplitBlock } from '../splitGeneCombinations.js'

const green = { id: 1, name: 'Green', series: 'Green', genetic_code: 'bl+/bl+|D+/D+' }
const darkGreen = { id: 2, name: 'Dark Green', series: 'Green', genetic_code: 'bl+/bl+|D+/D' }
const blue1 = { id: 3, name: 'Blue1', series: 'Blue', genetic_code: 'bl1/bl1|D+/D+' }
const teal = { id: 4, name: 'Teal', series: 'Turquoise / Other Blue-Series Forms', genetic_code: 'bl1/bl2|D+/D+' }

const splitBlue1 = {
  id: 11,
  name: 'Split Blue1',
  genetic_category: 'Base-color locus bl',
  mutant_allele: 'bl1',
  hen_can_split: true,
  cock_can_split: true,
  phenotype_when_visual: 'Blue1',
}
const splitBlue2 = {
  id: 12,
  name: 'Split Blue2',
  genetic_category: 'Base-color locus bl',
  mutant_allele: 'bl2',
  hen_can_split: true,
  cock_can_split: true,
  phenotype_when_visual: 'Blue2',
}
const splitDilute = {
  id: 13,
  name: 'Split Dilute',
  genetic_category: 'Eumelanin distribution',
  mutant_allele: 'dil',
  hen_can_split: true,
  cock_can_split: true,
  phenotype_when_visual: 'Dilute',
}
const splitOpaline = {
  id: 14,
  name: 'Split Opaline',
  genetic_category: 'Z-linked distribution',
  mutant_allele: 'op',
  hen_can_split: false,
  cock_can_split: true,
  phenotype_when_visual: 'Opaline',
}
const splitPastel = {
  id: 15,
  name: 'Split Pastel',
  genetic_category: 'a locus',
  mutant_allele: 'a*pa',
  hen_can_split: true,
  cock_can_split: true,
  phenotype_when_visual: 'Pastel',
}

const visualOpaline = { id: 21, name: 'Opaline', series: 'Sex-linked', allele: 'op. Distribution mutation.' }
const visualNsl = { id: 22, name: 'NSL Ino', series: 'a locus', allele: 'a. Autosomal.' }
const visualDilute = { id: 23, name: 'Dilute', series: 'Eumelanin distribution', allele: 'dil. Autosomal.' }

describe('splitGeneBlock', () => {
  it('allows hidden genes on different loci, including green split blue', () => {
    expect(splitGeneBlock(splitBlue1, [splitDilute, splitOpaline], { sex: 'cock', baseColor: green })).toBe('')
    expect(splitGeneBlock(splitBlue1, [], { sex: 'hen', baseColor: darkGreen })).toBe('')
    expect(splitGeneBlock(splitDilute, [splitOpaline], { sex: 'cock', visualMutations: [visualNsl] })).toBe('')
  })

  it('blocks two hidden alleles of one gene and a hen sex-linked split', () => {
    expect(splitGeneBlock(splitBlue2, [splitBlue1], { baseColor: green })).toMatch(/only one hidden allele/)
    expect(splitGeneBlock(splitOpaline, [], { sex: 'hen' })).toMatch(/hen/)
  })

  it('blocks a split that is already visual or already used by a blue-series color', () => {
    expect(splitGeneBlock(splitOpaline, [], { sex: 'cock', visualMutations: [visualOpaline] })).toMatch(/already visual/)
    expect(splitGeneBlock(splitPastel, [], { visualMutations: [visualNsl] })).toMatch(/already visual/)
    expect(splitGeneBlock(splitBlue1, [], { baseColor: blue1 })).toMatch(/green-series/)
    expect(splitGeneBlock(splitBlue1, [], { baseColor: teal })).toMatch(/green-series/)
  })
})

describe('baseColorBlock and visualSplitBlock', () => {
  it('keeps green-series colors with a blue split and blocks mutant blue colors', () => {
    expect(baseColorBlock(green, [splitBlue1])).toBe('')
    expect(baseColorBlock(darkGreen, [splitBlue1])).toBe('')
    expect(baseColorBlock(blue1, [splitBlue1])).toMatch(/green-series/)
    expect(baseColorBlock(teal, [splitDilute])).toBe('')
  })

  it('keeps a visual mutation hidden when that gene is stored as a split', () => {
    expect(visualSplitBlock(visualOpaline, [splitOpaline])).toMatch(/stays hidden/)
    expect(visualSplitBlock(visualDilute, [splitOpaline])).toBe('')
    expect(keepCombinableGeneIds([splitOpaline.id, splitDilute.id], [splitOpaline, splitDilute], {
      sex: 'cock',
      visualMutations: [visualOpaline],
    })).toEqual([splitDilute.id])
  })
})

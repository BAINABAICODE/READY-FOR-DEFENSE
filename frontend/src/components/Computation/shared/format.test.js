import { describe, expect, it } from 'vitest'
import { breedingOutcome, geneInheritanceRows, outcomeBaseColor, outcomeSplit, outcomeVisual } from './format'

describe('outcome Mendelian answers', () => {
  it('names an inherited color, visual mutation, and hidden split', () => {
    const row = {
      base_color: 'Blue2',
      visual_mutations: ['Dilute'],
      split_hidden_genes: ['NSL Ino', 'Blue2'],
    }
    expect(outcomeBaseColor(row)).toBe('Blue2')
    expect(outcomeVisual(row)).toBe('Dilute')
    expect(outcomeSplit(row)).toBe('split NSL Ino, split Blue2')
    expect(breedingOutcome({ ...row, sex: 'hen' })).toBe('Dilute Blue2 hen, split NSL Ino, split Blue2')
  })

  it('treats a chick with no expressed mutation and no hidden allele as a calculated result', () => {
    const row = {
      passed_from_parents: [
        { locus: 'Ground color (Green)', category: 'base_color', chick_genotype: 'bl+/bl2' },
      ],
    }
    expect(outcomeBaseColor(row)).toBe('bl+/bl2')
    expect(outcomeVisual(row)).toBe('Normal')
    expect(outcomeSplit(row)).toBe('Not split')
    expect(breedingOutcome({ ...row, sex: 'cock' })).toBe('bl+/bl2 cock, not split')
  })

  it('counts each hidden gene on its own so the graph is not eight copies of 12.5%', () => {
    const chicks = [
      { sex: 'cock', base_color: 'Green', visual_mutations: [], split_hidden_genes: ['Dilute', 'NSL Ino', 'Opaline', 'Blue2'], probability: 0.125 },
      { sex: 'hen', base_color: 'Green', visual_mutations: [], split_hidden_genes: ['Dilute', 'NSL Ino', 'Blue2'], probability: 0.125 },
      { sex: 'cock', base_color: 'Green', visual_mutations: ['Dilute'], split_hidden_genes: ['NSL Ino', 'Opaline', 'Blue2'], probability: 0.125 },
      { sex: 'hen', base_color: 'Green', visual_mutations: ['Dilute'], split_hidden_genes: ['NSL Ino', 'Blue2'], probability: 0.125 },
      { sex: 'cock', base_color: 'Blue2', visual_mutations: [], split_hidden_genes: ['Dilute', 'NSL Ino', 'Opaline'], probability: 0.125 },
      { sex: 'hen', base_color: 'Blue2', visual_mutations: [], split_hidden_genes: ['Dilute', 'NSL Ino'], probability: 0.125 },
      { sex: 'cock', base_color: 'Blue2', visual_mutations: ['Dilute'], split_hidden_genes: ['NSL Ino', 'Opaline'], probability: 0.125 },
      { sex: 'hen', base_color: 'Blue2', visual_mutations: ['Dilute'], split_hidden_genes: ['NSL Ino'], probability: 0.125 },
    ]

    const byName = (rows) => Object.fromEntries(rows.map((row) => [row.trait, row.probability]))
    expect(byName(geneInheritanceRows(chicks, 'base_color'))).toEqual({ Green: 0.5, Blue2: 0.5 })
    expect(byName(geneInheritanceRows(chicks, 'visual_mutation'))).toEqual({ Dilute: 0.5, Normal: 0.5 })
    expect(byName(geneInheritanceRows(chicks, 'split_gene'))).toEqual({
      'split NSL Ino': 1,
      'split Dilute': 0.5,
      'split Opaline': 0.5,
      'split Blue2': 0.5,
    })
    expect(byName(geneInheritanceRows(chicks, 'sex'))).toEqual({ Cock: 0.5, Hen: 0.5 })
  })
})

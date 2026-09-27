import { describe, expect, it } from 'vitest'
import {
  aggregateCells,
  buildCompatibilityModel,
  buildGametes,
  buildLocusTrace,
  buildPunnettSquare,
  classifyCompatibility,
  classifyExpression,
  collectValidationFindings,
  describeSpeciesPairing,
  parseGenotype,
  resolveInheritanceMode,
  sumProbabilities,
} from '../index'

function outcomesByGenotype(cells) {
  return Object.fromEntries(aggregateCells(cells).map((row) => [`${row.sex}:${row.genotype}`, row.probability]))
}

describe('Test 1 — autosomal recessive G/B × B/B', () => {
  it('yields G/B = 50% and B/B = 50%', () => {
    const square = buildPunnettSquare(['G', 'B'], ['B', 'B'])
    expect(square.total).toBe(4)
    const outcomes = outcomesByGenotype(square.cells)
    expect(outcomes['both:G/B']).toBeCloseTo(0.5, 10)
    expect(outcomes['both:B/B']).toBeCloseTo(0.5, 10)
    expect(sumProbabilities(aggregateCells(square.cells))).toBeCloseTo(1, 10)
  })

  it('records both gamete paths for G/B', () => {
    const rows = aggregateCells(buildPunnettSquare(['G', 'B'], ['B', 'B']).cells)
    const gb = rows.find((r) => r.genotype === 'G/B')
    expect(gb.count).toBe(2)
    expect(gb.fraction).toBe('1/2')
    expect(gb.paths.every((p) => p.fromCock === 'G' && p.fromHen === 'B')).toBe(true)
  })
})

describe('Test 2 — two heterozygous parents G/B × G/B', () => {
  it('yields 25% / 50% / 25%', () => {
    const outcomes = outcomesByGenotype(buildPunnettSquare(['G', 'B'], ['G', 'B']).cells)
    expect(outcomes['both:G/G']).toBeCloseTo(0.25, 10)
    expect(outcomes['both:G/B']).toBeCloseTo(0.5, 10)
    expect(outcomes['both:B/B']).toBeCloseTo(0.25, 10)
  })
})

describe('Test 3 — homozygous dominant G/G × G/G', () => {
  it('yields G/G = 100% with a 1/1 fraction', () => {
    const rows = aggregateCells(buildPunnettSquare(['G', 'G'], ['G', 'G']).cells)
    expect(rows).toHaveLength(1)
    expect(rows[0].genotype).toBe('G/G')
    expect(rows[0].probability).toBe(1)
    expect(rows[0].fraction).toBe('1/1')
  })
})

describe('Test 4 — sex-linked Z+/Zino cock × Zino/W hen (avian ZW)', () => {
  const square = buildPunnettSquare(['ino+', 'ino'], ['ino', 'W'], { sexLinked: true })
  const rows = aggregateCells(square.cells)
  const byKey = Object.fromEntries(rows.map((r) => [`${r.sex}:${r.genotype}`, r]))

  it('produces four equally likely sex-specific outcomes', () => {
    expect(rows).toHaveLength(4)
    rows.forEach((r) => expect(r.probability).toBeCloseTo(0.25, 10))
    expect(sumProbabilities(rows)).toBeCloseTo(1, 10)
  })

  it('classifies carrier male, visual male, non-visual female, visual female correctly', () => {
    const mode = resolveInheritanceMode('Sex-linked recessive')
    expect(classifyExpression(byKey['cock:ino+/ino'].genotype, mode)).toBe('carrier_split')
    expect(classifyExpression(byKey['cock:ino/ino'].genotype, mode)).toBe('visual')
    expect(classifyExpression(byKey['hen:ino+/W'].genotype, mode)).toBe('hemizygous_wild')
    expect(classifyExpression(byKey['hen:ino/W'].genotype, mode)).toBe('visual_hemizygous')
  })

  it('hen gametes are Z and W at 50% each; cock gametes are two Z alleles', () => {
    const hen = buildGametes(['ino', 'W'])
    expect(hen.gametes.find((g) => g.isW).probability).toBe(0.5)
    const cock = buildGametes(['ino+', 'ino'])
    expect(cock.gametes.map((g) => g.allele).sort()).toEqual(['ino', 'ino+'])
  })

  it('reproduces the stored backend Opaline result (op/op × op+/W) exactly', () => {
    const trace = buildLocusTrace({
      name: 'Opaline',
      category: 'visual_mutation',
      inheritance_type: 'Sex-linked recessive',
      status: 'calculated',
      parent_contribution: { parent_cock: { code: 'op/op' }, parent_hen: { code: 'op+/W', assumed_non_carrier: true } },
      punnett: { parent_1_alleles: ['op', 'op'], parent_2_alleles: ['op+', 'W'], sex_linked: true },
      results: [
        { genotype: 'op+/op', sex: 'cock', probability: 0.5, expression: 'carrier_split', phenotype: 'Carrier / split for Opaline' },
        { genotype: 'op/W', sex: 'hen', probability: 0.5, expression: 'visual_hemizygous', phenotype: 'Opaline (visual)' },
      ],
    })
    expect(trace.verified).toBe(true)
    expect(trace.outcomes.find((o) => o.sex === 'cock').phenotype.phenotype).toBe('Carrier / split for Opaline')
    expect(trace.chromosomes.hen.chromosomes.map((c) => c.chromosome)).toEqual(['Z', 'W'])
  })
})

describe('Test 5 — different species', () => {
  it('flags an interspecific pairing and reduced confidence, never same-species', () => {
    const pairing = describeSpeciesPairing({
      same_species: false,
      status: 'LIMITED_OR_UNCERTAIN',
      parent_1: { species: 'Peach-faced lovebird', scientific_name: 'Agapornis roseicollis' },
      parent_2: { species: 'Masked lovebird', scientific_name: 'Agapornis personatus' },
    })
    expect(pairing.sameSpecies).toBe(false)
    expect(pairing.interspecific).toBe(true)
    expect(pairing.confidence).toBe('Reduced')
    const findings = collectValidationFindings({ result: { result_presentation: { species_compatibility: { same_species: false } } } })
    expect(findings.some((f) => f.code === 'interspecific')).toBe(true)
  })

  it('treats identical species records as same-species', () => {
    expect(describeSpeciesPairing({ same_species: true, status: 'SAME_SPECIES' }).interspecific).toBe(false)
  })
})

describe('Test 6 — missing genotype is never invented', () => {
  it('parseGenotype reports missing input instead of assuming wild type', () => {
    const parsed = parseGenotype('')
    expect(parsed.valid).toBe(false)
    expect(parsed.alleles).toEqual([])
  })

  it('a locus without one parental genotype produces no Punnett cells', () => {
    const square = buildPunnettSquare([], ['B', 'B'])
    expect(square.valid).toBe(false)
    expect(square.cells).toHaveLength(0)
  })

  it('unavailable loci surface as missing-genotype findings', () => {
    const findings = collectValidationFindings({
      result: { result_presentation: { probabilities: { unavailable: [{ category: 'base_color', name: 'Base color', reason: 'No stored genotype.' }] } } },
    })
    expect(findings.find((f) => f.code === 'missing_genotype').message).toBe('No stored genotype.')
  })
})

describe('Dosage and allelic-compound expression', () => {
  it('reads intermediate dominant as single factor, not complete dominance', () => {
    const mode = resolveInheritanceMode('Intermediate dominant')
    expect(mode.incomplete).toBe(true)
    expect(mode.dominant).toBe(false)
    expect(classifyExpression('D+/D+', mode)).toBe('non_carrier')
    expect(classifyExpression('D+/D', mode)).toBe('visual_single_factor')
    expect(classifyExpression('D/D', mode)).toBe('visual_double')
  })

  it('keeps complete dominance on heterozygous and homozygous classes', () => {
    const mode = resolveInheritanceMode('Autosomal dominant')
    expect(classifyExpression('Pi+/Pi', mode)).toBe('visual_heterozygous')
    expect(classifyExpression('Pi/Pi', mode)).toBe('visual_homozygous')
    expect(classifyExpression('Pi+/Pi+', mode)).toBe('non_carrier')
  })

  it('treats two different recessive alleles as a visual compound', () => {
    const mode = resolveInheritanceMode('Autosomal recessive')
    expect(classifyExpression('bl+/blaq', mode)).toBe('carrier_split')
    expect(classifyExpression('blaq/blaq', mode)).toBe('visual')
    expect(classifyExpression('blaq/bltq', mode)).toBe('visual_compound')
  })

  it('marks a single-factor sex-linked incomplete cock as visual', () => {
    const mode = resolveInheritanceMode('Sex-linked incomplete dominant')
    expect(classifyExpression('Grw+/Grw', mode)).toBe('visual_single_factor')
    expect(classifyExpression('Grw/Grw', mode)).toBe('visual_double')
    expect(classifyExpression('Grw/W', mode)).toBe('visual_hemizygous')
    expect(classifyExpression('Grw+/W', mode)).toBe('hemizygous_wild')
  })
})

describe('Weighted compatibility model mirrors GICA points', () => {
  const gica = {
    score: 97,
    label: 'Excellent',
    classification_scale: { Excellent: '90–100', Good: '75–89', Fair: '60–74', Poor: '40–59', 'Not Recommended': '0–39' },
    breakdown: [
      { key: 'species_compatibility', factor: 'Species Compatibility', max_points: 30, points: 30 },
      { key: 'inheritance_information', factor: 'Inheritance Information', max_points: 20, points: 20 },
      { key: 'mutation_compatibility', factor: 'Mutation Compatibility', max_points: 15, points: 15 },
      { key: 'genetic_risk', factor: 'Genetic Risk', max_points: 15, points: 12 },
      { key: 'genetic_diversity', factor: 'Genetic Diversity', max_points: 10, points: 10 },
      { key: 'breeding_constraints', factor: 'Breeding Constraints', max_points: 10, points: 10 },
    ],
  }
  const model = buildCompatibilityModel(gica, { same_species: true })

  it('weights sum to 100% and weighted contributions equal awarded points', () => {
    expect(model.weightsSumPercent).toBeCloseTo(100, 10)
    model.factors.forEach((f) => expect(f.weighted).toBeCloseTo(f.points, 10))
    expect(model.computedTotal).toBeCloseTo(97, 10)
    expect(model.consistent).toBe(true)
  })

  it('uses the backend classification scale', () => {
    expect(classifyCompatibility(97, gica.classification_scale).label).toBe('Excellent')
    expect(classifyCompatibility(74, gica.classification_scale).label).toBe('Fair')
    expect(classifyCompatibility(39, gica.classification_scale).label).toBe('Not Recommended')
  })

  it('reports an inconsistency when the stored score disagrees with the factor sum', () => {
    expect(buildCompatibilityModel({ ...gica, score: 60 }).consistent).toBe(false)
  })
})

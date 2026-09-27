import { canonicalGenotypeKey, encodeParentLocus, parseGenotype, isWChromosome } from './alleleEncoder'
import { buildGametes } from './gameteGenerator'
import { buildPunnettSquare } from './punnettCalculator'
import { aggregateCells, percent, simplifyFraction, sortOutcomes, sumProbabilities, expectedRange } from './probabilityAggregator'
import { mapPhenotype } from './phenotypeMapper'
import { resolveInheritanceMode } from './inheritanceResolver'
import { chromosomeMapping, classifySexLinkedOutcome } from './sexLinkedResolver'
import { DEFAULT_CLUTCH_SIZES } from './constants'
import { homozygousFromSplitCarriers } from './splitCarrierHomozygous'

/**
 * Orchestrates the F1–F5 trace for every locus the backend RBGIA engine processed and
 * cross-checks the reconstruction against the stored results. The stored payload stays
 * authoritative; `verified` tells the UI whether the visible trace reproduces it exactly.
 */

const EPSILON = 1e-6

export function buildLocusTrace(outcome, roles = {}) {
  const name = outcome?.name || outcome?.locus || 'Locus'
  const mode = resolveInheritanceMode(outcome?.inheritance_type, outcome?.category)
  const stored = Array.isArray(outcome?.results) && outcome.results.length
    ? outcome.results
    : (Array.isArray(outcome?.punnett?.results) ? outcome.punnett.results : [])

  const cock = encodeParentLocus(
    outcome?.parent_contribution?.parent_cock || { code: outcome?.genetic_code_cock || outcome?.parent_1_code },
    name,
  )
  const hen = encodeParentLocus(
    outcome?.parent_contribution?.parent_hen || { code: outcome?.genetic_code_hen || outcome?.parent_2_code },
    name,
  )

  const cockAlleles = arrayOr(outcome?.punnett?.parent_1_alleles, cock.alleles)
  const henAlleles = arrayOr(outcome?.punnett?.parent_2_alleles, hen.alleles)
  const sexLinked = Boolean(outcome?.punnett?.sex_linked) || mode.sexLinked || henAlleles.some(isWChromosome)
  const calculated = (outcome?.status || 'calculated') === 'calculated' && cockAlleles.length === 2 && henAlleles.length === 2

  const gametes = { cock: buildGametes(cockAlleles), hen: buildGametes(henAlleles) }
  const square = calculated ? buildPunnettSquare(cockAlleles, henAlleles, { sexLinked }) : { rows: cockAlleles, cols: henAlleles, cells: [], total: 0, sexLinked, valid: false, reason: outcome?.reason || 'Locus not calculated by the engine.' }
  const aggregated = square.valid ? aggregateCells(square.cells) : []
  const outcomes = aggregated.map((row) => ({
    ...row,
    phenotype: mapPhenotype(row.genotype, row.sex, mode, stored),
    sexLinkedClass: sexLinked ? classifySexLinkedOutcome(row.sex, row.genotype, mode) : null,
  }))

  const verification = compareWithStored(outcomes, stored)
  const fixed = outcomes.length === 1 && outcomes[0].probability >= 1 - EPSILON

  return {
    key: outcome?.locus_key || name,
    name,
    category: outcome?.category || null,
    inheritanceType: outcome?.inheritance_type || null,
    mode,
    sexLinked,
    status: outcome?.status || 'calculated',
    reason: outcome?.reason || null,
    confidence: outcome?.confidence || null,
    provisional: Boolean(outcome?.provisional),
    verificationStatus: outcome?.verification_status || null,
    assumptionNote: outcome?.assumption_note || null,
    roles,
    parents: { cock, hen },
    alleles: { cock: cockAlleles, hen: henAlleles },
    gametes,
    square,
    outcomes,
    stored,
    storedSorted: sortOutcomes(stored.map((row) => ({ ...row, probability: Number(row.probability) || 0 }))),
    verified: verification.verified,
    verificationDetail: verification.detail,
    fixed,
    chromosomes: sexLinked ? chromosomeMapping(cockAlleles, henAlleles) : null,
    probabilitySum: sumProbabilities(outcomes),
    carrierHomozygous: outcome?.carrier_homozygous || homozygousFromSplitCarriers({
      cockAlleles,
      henAlleles,
      sexLinked,
      inheritanceType: outcome?.inheritance_type,
      results: stored,
    }),
  }
}

function compareWithStored(outcomes, stored) {
  if (!stored.length) return { verified: null, detail: 'No stored per-locus results to compare against.' }
  if (!outcomes.length) return { verified: null, detail: 'Trace not reconstructed for this locus.' }
  const mismatches = []
  outcomes.forEach((row) => {
    const match = stored.find((s) => canonical(s.genotype) === canonical(row.genotype) && sexMatches(s.sex, row.sex))
    if (!match) {
      mismatches.push(`${row.genotype} (${row.sex}) not present in stored results`)
      return
    }
    if (Math.abs((Number(match.probability) || 0) - row.probability) > EPSILON) {
      mismatches.push(`${row.genotype}: stored ${match.probability} vs trace ${row.probability}`)
    }
  })
  const storedSum = sumProbabilities(stored)
  if (Math.abs(storedSum - 1) > 1e-3) mismatches.push(`Stored probabilities sum to ${storedSum.toFixed(4)}`)
  return { verified: mismatches.length === 0, detail: mismatches.length ? mismatches.join('; ') : 'Reconstructed probabilities match the stored RBGIA results.' }
}

function canonical(genotype) {
  const parsed = parseGenotype(genotype)
  return parsed.valid ? canonicalGenotypeKey(parsed.alleles) : String(genotype || '')
}

function sexMatches(a, b) {
  if (!a || !b || a === 'both' || b === 'both') return true
  return a === b
}

function arrayOr(value, fallback) {
  return Array.isArray(value) && value.length ? value : fallback
}

/* ---------------------------------------------------------------- joint outcomes */

export function buildJointOutcomes(result = {}) {
  const presentation = result.result_presentation || {}
  const theoretical = Array.isArray(result.rbgia_data?.theoretical_distribution) ? result.rbgia_data.theoretical_distribution : []
  const complete = Array.isArray(presentation.probabilities?.complete_offspring) ? presentation.probabilities.complete_offspring : []
  const eggs = Array.isArray(result.egg_outcomes) && result.egg_outcomes.length
    ? result.egg_outcomes
    : (presentation.egg_chick_examples || [])
  // Only living chicks carry RBGIA genetics; several simulated eggs may share one RBGIA outcome, so prefer the one with an image.
  const livingEggs = eggs.filter((e) => e && (!e.egg_status || e.egg_status === 'living_chick'))
  const preferImaged = (map, key, egg) => {
    if (!key) return
    const existing = map.get(key)
    if (!existing || (!existing.image?.image_url && egg.image?.image_url)) map.set(key, egg)
  }
  const eggByKey = new Map()
  const eggByGenotype = new Map()
  livingEggs.forEach((egg) => {
    preferImaged(eggByKey, egg.rbgia_outcome_key || egg.outcome_key, egg)
    preferImaged(eggByGenotype, egg.genotype, egg)
  })

  const source = theoretical.length ? theoretical : complete
  // The engine multiplies the raw per-locus probabilities (sex-linked loci already carry their sex share),
  // drops sex-incompatible combinations, then normalises by the sum of all surviving products.
  // Reproduce that exactly: P(row) = Π P(locus) ÷ Σ_rows Π P(locus).
  const products = source.map((row) => {
    const loci = Array.isArray(row.loci) ? row.loci : []
    return loci.length ? loci.reduce((acc, locus) => acc * (Number(locus.probability) || 0), 1) : null
  })
  const productSum = products.reduce((acc, p) => acc + (p ?? 0), 0)
  const normalised = productSum > 0 && Math.abs(productSum - 1) > EPSILON
  const rows = source.map((row, index) => {
    const loci = Array.isArray(row.loci) ? row.loci : []
    const rawProduct = products[index]
    const product = rawProduct == null ? null : (normalised ? rawProduct / productSum : rawProduct)
    const probability = Number(row.probability) || 0
    const egg = (row.outcome_key && eggByKey.get(row.outcome_key)) || eggByGenotype.get(row.genotype) || null
    return {
      id: row.outcome_key || `${row.genotype || 'outcome'}-${index}`,
      index,
      sex: row.sex || null,
      sexLabel: row.sex_label || null,
      baseColor: row.base_color || null,
      baseColorGenotype: row.base_color_genotype || null,
      darkFactor: row.dark_factor || null,
      visualMutations: row.visual_mutations || [],
      splitHiddenGenes: row.split_hidden_genes || [],
      genotype: row.genotype || null,
      phenotype: row.phenotype || null,
      eyes: row.eyes || egg?.eyes || null,
      head: row.head || egg?.head || null,
      body: row.body || egg?.body || null,
      wings: row.wings || egg?.wings || null,
      rump: row.rump || egg?.rump || null,
      tail: row.tail || egg?.tail || null,
      probability,
      fraction: row.fraction || null,
      loci,
      productOfLoci: product,
      rawProductOfLoci: rawProduct,
      normalisationDivisor: normalised ? productSum : null,
      productMatches: product == null ? null : Math.abs(product - probability) <= EPSILON,
      formula: loci.length
        ? `${loci.map((l) => l.fraction || fmt(l.probability)).join(' × ')}${normalised ? ` ÷ ${fmt(productSum)} (Σ of all sex-compatible products)` : ''} = ${row.fraction || fmt(probability)}`
        : null,
      inheritanceClasses: row.inheritance_classes || null,
      passedFromParents: row.passed_from_parents || row.inherited_from?.passed || [],
      egg,
      imageUrl: egg?.image?.image_url || egg?.image_url || null,
      eggNumber: egg?.egg_number ?? null,
    }
  })

  const sorted = [...rows].sort((a, b) => b.probability - a.probability || a.index - b.index)
  sorted.forEach((row, rank) => { row.rank = rank + 1 })
  return { rows: sorted, total: sumProbabilities(sorted), source: theoretical.length ? 'rbgia_data.theoretical_distribution' : 'probabilities.complete_offspring' }
}

/** Groups joint genotypes by their visible result (sex + base colour + visual + splits). */
export function groupVisualOutcomes(rows, { includeSex = true, includeSplits = true } = {}) {
  const groups = new Map()
  rows.forEach((row) => {
    const visual = row.visualMutations.length ? row.visualMutations.join(' + ') : 'No visual mutation'
    const splits = includeSplits && row.splitHiddenGenes.length ? `split ${row.splitHiddenGenes.join(', ')}` : ''
    const parts = [row.baseColor || 'Base colour not stored', visual, splits].filter(Boolean)
    const key = `${includeSex ? row.sex || '' : ''}|${parts.join('|')}`
    if (!groups.has(key)) {
      groups.set(key, {
        key,
        sex: includeSex ? row.sex : null,
        sexLabel: includeSex ? row.sexLabel : null,
        baseColor: row.baseColor,
        visualMutations: row.visualMutations,
        splitHiddenGenes: includeSplits ? row.splitHiddenGenes : [],
        label: parts.join(' / '),
        probability: 0,
        members: [],
      })
    }
    const group = groups.get(key)
    group.probability += row.probability
    group.members.push(row)
  })
  const list = [...groups.values()].sort((a, b) => b.probability - a.probability || a.members[0].index - b.members[0].index)
  const denominator = commonDenominator(rows)
  list.forEach((group, i) => {
    group.rank = i + 1
    group.fraction = denominator ? simplifyFraction(Math.round(group.probability * denominator), denominator) : null
  })
  return list
}

function commonDenominator(rows) {
  const dens = rows.map((r) => Number(String(r.fraction || '').split('/')[1]) || 0).filter(Boolean)
  return dens.length ? Math.max(...dens) : 0
}

/* ---------------------------------------------------------------- full trace */

export function buildRbgiaTrace(result = {}) {
  const presentation = result.result_presentation || {}
  const snapshot = result.parent_snapshot || {}
  const roles = parentRoles(snapshot)
  const outcomes = Array.isArray(presentation.genetic_analysis?.outcomes) && presentation.genetic_analysis.outcomes.length
    ? presentation.genetic_analysis.outcomes
    : Array.isArray(presentation.verification?.punnett_detail) ? presentation.verification.punnett_detail : []

  const loci = outcomes.map((outcome) => buildLocusTrace(outcome, roles))
  const joint = buildJointOutcomes(result)
  const visualOutcomes = groupVisualOutcomes(joint.rows)
  const appearanceOutcomes = groupVisualOutcomes(joint.rows, { includeSex: false, includeSplits: false })
  const sexLinkedLoci = loci.filter((l) => l.sexLinked)
  const modeCounts = loci.reduce((acc, l) => { acc[l.mode.key] = (acc[l.mode.key] || 0) + 1; return acc }, {})

  return {
    roles,
    loci,
    sexLinkedLoci,
    joint,
    visualOutcomes,
    appearanceOutcomes,
    breakdownRows: loci.map(breakdownRow),
    summary: {
      totalOutcomes: joint.rows.length,
      visualOutcomeCount: visualOutcomes.length,
      appearanceCount: appearanceOutcomes.length,
      top: visualOutcomes.slice(0, 2),
      topAppearance: appearanceOutcomes.slice(0, 2),
      sexLinkedTraits: sexLinkedLoci.map((l) => l.name),
      modeCounts,
      primaryModes: Object.entries(modeCounts).sort((a, b) => b[1] - a[1]).map(([key]) => key),
      lociVerified: loci.filter((l) => l.verified === true).length,
      lociWithStored: loci.filter((l) => l.verified !== null).length,
      allVerified: loci.every((l) => l.verified !== false),
      probabilityTotal: joint.total,
      method: presentation.algorithm?.method || result.genetic_calculation?.method || null,
      deterministic: presentation.algorithm?.deterministic !== false,
    },
    clutch: buildClutchInterpretation(appearanceOutcomes),
  }
}

function parentRoles(snapshot) {
  const p1 = snapshot.parent_1 || {}
  const p2 = snapshot.parent_2 || {}
  const isCock = (p) => String(p.sex || '').toLowerCase() === 'cock' || /cock|male/i.test(p.sex_label || '') && !/female/i.test(p.sex_label || '')
  const cockParent = isCock(p1) ? { slot: 'Parent 1', ...p1 } : isCock(p2) ? { slot: 'Parent 2', ...p2 } : null
  const henParent = cockParent?.slot === 'Parent 1' ? { slot: 'Parent 2', ...p2 } : { slot: 'Parent 1', ...p1 }
  return {
    cock: cockParent ? { slot: cockParent.slot, birdId: cockParent.bird_id || null } : { slot: 'Cock', birdId: null },
    hen: henParent && henParent !== cockParent ? { slot: henParent.slot, birdId: henParent.bird_id || null } : { slot: 'Hen', birdId: null },
  }
}

function breakdownRow(trace) {
  const outcomeText = trace.outcomes.length
    ? trace.outcomes.map((o) => `${percent(o.probability)} ${o.genotype}${o.sex !== 'both' ? ` ${o.sex === 'cock' ? '♂' : '♀'}` : ''}`).join(' · ')
    : (trace.reason || 'Not calculated')
  let formula = 'F1 → F5'
  if (!trace.square.valid) formula = 'Skip (not calculated)'
  else if (trace.fixed) formula = 'Fixed (single genotype)'
  else if (trace.sexLinked) formula = 'F2 (ZW) + F3 + F5'
  else formula = 'F3 + F5: ΣP'
  return {
    key: trace.key,
    name: trace.name,
    category: trace.category,
    mode: trace.mode,
    cockAlleles: trace.parents.cock.code,
    henAlleles: trace.parents.hen.code,
    outcomeText,
    formula,
    verified: trace.verified,
    sexLinked: trace.sexLinked,
  }
}

export function buildClutchInterpretation(appearanceOutcomes, clutchSizes = DEFAULT_CLUTCH_SIZES, limit = 8) {
  const top = appearanceOutcomes.slice(0, limit)
  return clutchSizes.map((size) => ({
    size,
    rows: top.map((group) => ({ label: group.label, probability: group.probability, ...expectedRange(size, group.probability) })),
  }))
}

function fmt(value) {
  return Number.isFinite(value) ? (Math.round(value * 10000) / 10000).toString() : '—'
}

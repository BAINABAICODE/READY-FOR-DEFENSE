import { canonicalGenotypeKey, isWildType, parseGenotype } from './alleleEncoder'
import { EXPRESSION_LABELS } from './constants'

/**
 * F4 — Phenotype mapping.
 * Classifies a genotype into an expression class using the resolved inheritance mode.
 * The human-readable phenotype text is taken from the stored dataset via the backend
 * result rows (`lookup`) — it is never generated here. When no stored text exists the
 * mapper says so explicitly instead of inventing one.
 */

export function classifyExpression(genotype, mode) {
  const parsed = parseGenotype(genotype)
  if (!parsed.valid) return 'unknown'
  const [a, b] = parsed.alleles
  const hemizygous = parsed.hemizygous
  const z = parsed.zAlleles

  if (hemizygous) {
    if (!z.length) return 'unknown'
    return isWildType(z[0]) ? 'hemizygous_wild' : 'visual_hemizygous'
  }

  const wildCount = [a, b].filter(isWildType).length
  if (wildCount === 2) return 'non_carrier'
  if (wildCount === 1) {
    if (mode?.incomplete) return 'visual_single_factor'
    if (mode?.dominant) return 'visual_heterozygous'
    return 'carrier_split'
  }
  if (a === b) {
    if (mode?.incomplete) return 'visual_double'
    if (mode?.dominant) return 'visual_homozygous'
    return 'visual'
  }
  return 'visual_compound'
}

export function expressionLabel(expression) {
  if (!expression) return '—'
  return EXPRESSION_LABELS[expression] || String(expression).replace(/_/g, ' ')
}

export function isVisualExpression(expression) {
  return typeof expression === 'string' && expression.startsWith('visual')
}

export function isCarrierExpression(expression) {
  return expression === 'carrier_split'
}

/**
 * @param {string} genotype
 * @param {'both'|'cock'|'hen'} sex
 * @param {object} mode resolved inheritance mode
 * @param {Array<object>} lookup backend `results` rows for the locus
 */
export function mapPhenotype(genotype, sex, mode, lookup = []) {
  const stored = findStoredResult(genotype, sex, lookup)
  const expression = stored?.expression || classifyExpression(genotype, mode)
  return {
    genotype,
    sex,
    expression,
    expressionLabel: expressionLabel(expression),
    phenotype: stored?.phenotype ?? null,
    phenotypeNote: stored?.phenotype_note ?? (stored ? null : 'No stored phenotype record for this genotype.'),
    baseColor: stored?.base_color ?? null,
    visualMutations: stored?.visual_mutations ?? [],
    splitHidden: stored?.split_hidden ?? [],
    visual: isVisualExpression(expression),
    carrier: isCarrierExpression(expression),
    storedMatch: Boolean(stored),
  }
}

export function findStoredResult(genotype, sex, lookup = []) {
  const target = canonical(genotype)
  return (lookup || []).find((row) => canonical(row?.genotype) === target && sexMatches(row?.sex, sex)) || null
}

function sexMatches(a, b) {
  if (!a || !b) return true
  if (a === 'both' || b === 'both') return true
  return a === b
}

function canonical(genotype) {
  const parsed = parseGenotype(genotype)
  return parsed.valid ? canonicalGenotypeKey(parsed.alleles) : String(genotype || '')
}

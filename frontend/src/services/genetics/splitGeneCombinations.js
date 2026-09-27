import {
  canHideBlSplit,
  isBlLocus,
  mutationLocusKey,
  splitLocusKey,
  splitVisualName,
} from './geneticLocus.js'

export function splitGeneBlock(candidate, selected, context = {}) {
  if (!candidate) return ''

  const { sex, baseColor, visualMutations = [] } = context
  const others = (selected || []).filter((item) => item && String(item.id) !== String(candidate.id))

  if (sex === 'hen' && candidate.hen_can_split === false) {
    return `${candidate.name} cannot be stored as a hidden split for a hen. A hen with this sex-linked gene on her single Z is visual, not split.`
  }
  if (sex === 'cock' && candidate.cock_can_split === false) {
    return `${candidate.name} cannot be stored as a hidden split for a cock.`
  }

  const locus = splitLocusKey(candidate)
  if (locus) {
    const sameLocus = others.find((item) => splitLocusKey(item) === locus)
    if (sameLocus) {
      return `${candidate.name} cannot be combined with ${sameLocus.name}. A split keeps one wild-type copy of this gene, so only one hidden allele can be stored.`
    }
  }

  if (isBlLocus(locus) && !canHideBlSplit(baseColor)) {
    const colorName = baseColor?.name || 'This base color'
    return `${candidate.name} can only stay hidden on a green-series bird. ${colorName} already uses both copies of the blue gene.`
  }

  const visual = (visualMutations || []).find((item) => sameLocusPair(locus, mutationLocusKey(item)))
  if (visual) {
    return `${visual.name} is already visual on this bird, so ${candidate.name} cannot also be stored as a hidden split. A split is the hidden heterozygous form.`
  }

  return ''
}

export function visualSplitBlock(candidate, splits) {
  if (!candidate || !splits?.length) return ''

  const locus = mutationLocusKey(candidate)
  const split = splits.find((item) => sameLocusPair(splitLocusKey(item), locus))
  if (!split) return ''

  const hidden = splitVisualName(split) || split.name
  return `This bird is split for ${hidden}, so ${candidate.name} stays hidden. A split bird does not show this mutation.`
}

export function baseColorBlock(color, splits) {
  if (!color || !splits?.length || canHideBlSplit(color)) return ''

  const split = splits.find((item) => isBlLocus(splitLocusKey(item)))
  if (!split) return ''

  return `${split.name} can only stay hidden on a green-series bird. ${color.name} already shows both copies of the blue gene.`
}

export function keepCombinableGeneIds(ids, genes, context = {}) {
  const kept = []
  const keptRecords = []

  for (const id of ids || []) {
    const gene = (genes || []).find((item) => String(item.id) === String(id)) || null
    if (gene && splitGeneBlock(gene, keptRecords, context)) continue
    kept.push(id)
    if (gene) keptRecords.push(gene)
  }

  return kept
}

function sameLocusPair(left, right) {
  return Boolean(left && right && left === right)
}

import { isWChromosome, isWildType } from './alleleEncoder'

/**
 * Chance that a recessive heterozygous split carrier produces a visual homozygous chick.
 * Returns null when the gene is not recessive or neither parent is a split carrier.
 */
export function homozygousFromSplitCarriers({ cockAlleles, henAlleles, sexLinked, inheritanceType, results }) {
  if (!isRecessive(inheritanceType)) return null
  const cock = Array.isArray(cockAlleles) ? cockAlleles : []
  const hen = Array.isArray(henAlleles) ? henAlleles : []
  const cockCarrier = isHeterozygousCarrier(cock)
  const henCarrier = !sexLinked && isHeterozygousCarrier(hen)
  if (!cockCarrier && !henCarrier) return null
  if (!Array.isArray(results) || !results.length) return null

  let probability = 0
  let hemizygous = 0
  const genotypes = []
  results.forEach((row) => {
    const expression = String(row?.expression || '')
    const chance = Number(row?.probability) || 0
    const genotype = String(row?.genotype || '')
    if ((expression === 'visual' || expression === 'visual_homozygous') && !genotype.split('/').some(isWChromosome)) {
      probability += chance
      if (genotype) genotypes.push(genotype)
    }
    if (sexLinked && expression === 'visual_hemizygous') hemizygous += chance
  })

  const cockPass = mutantDose(cock)
  const henPass = sexLinked ? henZMutant(hen) : mutantDose(hen)
  const formula = sexLinked
    ? `1/2 × ${rate(cockPass)} × ${rate(henPass)} = ${fraction(0.5 * cockPass * henPass)}`
    : `${rate(cockPass)} × ${rate(henPass)} = ${fraction(cockPass * henPass)}`

  const who = cockCarrier && henCarrier
    ? 'Both parents are heterozygous split carriers.'
    : cockCarrier
      ? 'The cock is a heterozygous split carrier.'
      : 'The hen is a heterozygous split carrier.'

  const statement = sexLinked
    ? `${who} A son is visual homozygous only when he also receives a mutant Z from the hen. ${formula} of offspring are visual homozygous sons. ${fraction(hemizygous)} are visual daughters, who show the one mutant Z they receive from the cock.`
    : `${who} Each heterozygous parent passes the mutant allele in half of its gametes. The visual homozygous chance is ${formula} of offspring.`

  return {
    applies: true,
    cock_heterozygous_carrier: cockCarrier,
    hen_heterozygous_carrier: henCarrier,
    probability,
    fraction: fraction(probability),
    formula,
    genotypes: [...new Set(genotypes)],
    hemizygous_visual_probability: sexLinked ? hemizygous : null,
    hemizygous_visual_fraction: sexLinked ? fraction(hemizygous) : null,
    statement,
  }
}

function isRecessive(inheritanceType) {
  return String(inheritanceType || '').toLowerCase().includes('recessive')
}

function isMutant(allele) {
  return Boolean(allele) && !isWChromosome(allele) && !isWildType(allele)
}

function isHeterozygousCarrier(alleles) {
  return alleles.filter(isMutant).length === 1 && alleles.filter(isWildType).length === 1
}

function mutantDose(alleles) {
  return alleles.filter(isMutant).length / 2
}

function henZMutant(alleles) {
  return alleles.some(isMutant) ? 1 : 0
}

function rate(value) {
  return fraction(value)
}

function fraction(probability) {
  if (!(probability > 1e-9)) return '0'
  if (Math.abs(probability - 1) < 1e-9) return '1'
  for (const denominator of [1, 2, 4, 8, 16]) {
    const numerator = Math.round(probability * denominator)
    if (Math.abs(probability - numerator / denominator) < 1e-6) {
      const divisor = gcd(numerator, denominator)
      return `${numerator / divisor}/${denominator / divisor}`
    }
  }
  return String(Math.round(probability * 10000) / 10000)
}

function gcd(left, right) {
  return right === 0 ? Math.max(left, 1) : gcd(right, left % right)
}

import { isWChromosome, isWildType } from './alleleEncoder'
import { SEX_CHROMOSOMES } from './constants'

/**
 * Avian ZW sex-linked inheritance helpers.
 *   Cock = ZZ (two Z-linked alleles)   Hen = ZW (one Z-linked allele + W)
 *   Son  = Z(from cock) + Z(from hen)  Daughter = Z(from cock) + W(from hen)
 * The W chromosome does not carry the corresponding Z-linked allele, so a hen is
 * always hemizygous: a single mutant Z allele is expressed visually.
 */

export function chromosomeMapping(cockAlleles, henAlleles) {
  const cockZ = (cockAlleles || []).filter((a) => !isWChromosome(a))
  const henZ = (henAlleles || []).filter((a) => !isWChromosome(a))
  const henHasW = (henAlleles || []).some(isWChromosome)
  return {
    cock: {
      karyotype: SEX_CHROMOSOMES.cock,
      chromosomes: cockZ.map((allele) => ({ chromosome: 'Z', allele, label: `Z${superscript(allele)}`, wild: isWildType(allele) })),
      valid: cockZ.length === 2,
    },
    hen: {
      karyotype: SEX_CHROMOSOMES.hen,
      chromosomes: [
        ...henZ.slice(0, 1).map((allele) => ({ chromosome: 'Z', allele, label: `Z${superscript(allele)}`, wild: isWildType(allele) })),
        { chromosome: 'W', allele: 'W', label: 'W', wild: null },
      ],
      valid: henZ.length === 1 && henHasW,
    },
  }
}

function superscript(allele) {
  return allele ? `(${allele})` : ''
}

/**
 * Classifies a Z-linked outcome for the breakdown table (visual/carrier × male/female).
 * A single mutant Z is hidden on a recessive cock and visual on an incomplete or complete dominant cock.
 */
export function classifySexLinkedOutcome(sex, genotype, mode = null) {
  const alleles = String(genotype || '').split('/').map((s) => s.trim())
  const z = alleles.filter((a) => !isWChromosome(a))
  const mutantCount = z.filter((a) => a && !isWildType(a)).length
  const dosageVisual = Boolean(mode?.incomplete || mode?.dominant)
  if (sex === 'hen') {
    return mutantCount ? 'visual female' : 'non-visual female'
  }
  if (mutantCount === 2) return 'visual male'
  if (mutantCount === 1) return dosageVisual ? 'visual male' : 'carrier male'
  return 'non-carrier male'
}

export const SEX_LINKED_RULES = Object.freeze([
  'Cock produces Z-bearing sperm only (ZZ).',
  'Hen produces either a Z-bearing or a W-bearing egg (ZW).',
  'Z + Z = male offspring; Z + W = female offspring.',
  'The W chromosome carries no allele at this locus, so daughters express whichever Z allele they receive from the cock.',
])

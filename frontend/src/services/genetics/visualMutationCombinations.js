import { mutationLocusKey } from './geneticLocus.js'

export function visualCombinationBlock(candidate, selected, sex) {
  if (!candidate) return ''

  if (isHen(sex) && isSexLinked(candidate) && dosageRank(candidate) === 2) {
    return `${candidate.name} cannot be selected for a hen. A hen has one Z chromosome, so she cannot show the double-factor form of a sex-linked mutation.`
  }

  const others = (selected || []).filter((item) => item && String(item.id) !== String(candidate.id))

  for (const current of others) {
    if (candidate.dosage_key && current.dosage_key && candidate.dosage_key === current.dosage_key) {
      return `${candidate.name} cannot be combined with ${current.name}. A bird is either the single-factor or the double-factor form of this mutation, not both.`
    }
  }

  const locus = mutationLocusKey(candidate)
  if (locus) {
    const sameLocus = others.filter((item) => mutationLocusKey(item) === locus)
    const limit = isHen(sex) && isSexLinked(candidate) ? 1 : 2
    if (sameLocus.length >= limit) {
      const names = joinNames([candidate.name, ...sameLocus.map((item) => item.name)])
      if (limit === 1) {
        return `${names} cannot be combined. A hen has one Z chromosome, so she can show only one allele of this sex-linked locus.`
      }
      return `${names} cannot all be selected. A bird has two copies of this gene, so only two alleles of this locus can be present.`
    }
  }

  return ''
}

export function keepCombinableMutationIds(ids, mutations, sex) {
  const kept = []
  const keptRecords = []

  for (const id of ids || []) {
    const mutation = (mutations || []).find((item) => String(item.id) === String(id)) || null
    if (mutation && visualCombinationBlock(mutation, keptRecords, sex)) continue
    kept.push(id)
    if (mutation) keptRecords.push(mutation)
  }

  return kept
}

function dosageRank(mutation) {
  if (mutation?.dosage_rank === 1 || mutation?.dosage_rank === 2) return mutation.dosage_rank
  const allele = String(mutation?.allele || '')
  if (/^two\s+/i.test(allele)) return 2
  if (/^one\s+/i.test(allele)) return 1
  return null
}

function isHen(sex) {
  return String(sex || '').trim().toLowerCase() === 'hen'
}

function isSexLinked(mutation) {
  return /^sex-linked/i.test(String(mutation?.inheritance_type || ''))
}

function joinNames(names) {
  if (names.length < 2) return names[0] || ''
  return `${names.slice(0, -1).join(', ')} and ${names[names.length - 1]}`
}

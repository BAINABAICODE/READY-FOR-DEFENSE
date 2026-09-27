export function normalizeAllele(allele) {
  if (allele == null) return null
  const value = String(allele).trim().toLowerCase().replaceAll('*', '')
  if (!value) return null
  const match = value.match(/^([a-z0-9]+)(\+)?/)
  if (!match) return value
  return `${match[1]}${match[2] || ''}`
}

export function tokenFromAlleleText(allele) {
  if (allele == null || !String(allele).trim()) return null
  const two = String(allele).match(/^(?:one|two)\s+([A-Za-z0-9*]+)/i)
  if (two) return normalizeAllele(two[1])
  const token = String(allele).match(/^([A-Za-z0-9*]+)/)
  return token ? normalizeAllele(token[1]) : null
}

export function locusFromSeriesAndAllele(series, allele) {
  const seriesKey = String(series || '').trim().toLowerCase()

  if (/\ba locus\b/.test(seriesKey)) return 'locus:a'
  if (seriesKey.includes('ino locus')) return 'locus:sl-ino'
  if (/base-color locus|locus bl|\bbl locus\b/.test(seriesKey)) return 'locus:bl'

  const of = String(allele || '').match(/Allele of ([^.]+)/i)
  if (of) {
    const named = of[1].trim().toLowerCase()
    if (named === 'a') return 'locus:a'
    if (named === 'ino') return 'locus:sl-ino'
    const key = normalizeAllele(named)
    return key ? `allele:${key}` : null
  }

  const token = tokenFromAlleleText(allele)
  return token ? `allele:${token}` : null
}

export function mutationLocusKey(mutation) {
  return mutation?.locus_key || locusFromSeriesAndAllele(mutation?.series, mutation?.allele)
}

export function splitLocusKey(gene) {
  return gene?.locus_key || locusFromSeriesAndAllele(gene?.genetic_category, gene?.mutant_allele || gene?.genetic_symbol)
}

export function blAlleles(geneticCode) {
  if (!geneticCode) return []
  const blPart = String(geneticCode).split('|')[0] || ''
  return blPart
    .split(/\s*\/\s*/)
    .map((part) => normalizeAllele(part))
    .filter(Boolean)
}

export function canHideBlSplit(color) {
  if (!color) return true
  if (typeof color.can_hide_bl_split === 'boolean') return color.can_hide_bl_split
  const alleles = blAlleles(color.genetic_code)
  if (!alleles.length) return String(color.series || '').trim().toLowerCase() === 'green'
  return alleles.every((allele) => allele === 'bl+')
}

export function isBlLocus(locusKey) {
  return locusKey === 'locus:bl'
}

export function splitVisualName(gene) {
  const phenotype = String(gene?.phenotype_when_visual || '').split(',')[0].trim()
  if (phenotype) return phenotype
  return String(gene?.name || '').replace(/^split\s+/i, '').trim()
}

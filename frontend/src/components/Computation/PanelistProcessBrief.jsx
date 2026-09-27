import { useMemo } from 'react'
import { buildCompatibilityModel } from '../../services/genetics'

function toPercent(value) {
  if (typeof value === 'number' && Number.isFinite(value)) {
    if (value >= 0 && value <= 1) return Math.round(value * 1000) / 10
    return Math.round(value * 10) / 10
  }
  if (typeof value === 'string') {
    const fraction = value.match(/^(\d+)\s*\/\s*(\d+)$/)
    if (fraction) {
      const den = Number(fraction[2])
      if (den > 0) return Math.round((Number(fraction[1]) / den) * 1000) / 10
    }
    const pct = value.match(/([\d.]+)\s*%/)
    if (pct) return Number(pct[1])
  }
  if (value && typeof value === 'object') {
    if (typeof value.probability === 'number') return toPercent(value.probability)
    if (value.fraction) return toPercent(value.fraction)
  }
  return null
}

function formatPct(value) {
  const pct = toPercent(value)
  if (pct == null) return null
  return Number.isInteger(pct) ? `${pct}%` : `${pct}%`
}

function documented(value) {
  if (value == null || value === '') return null
  const text = String(value).trim()
  if (!text || /not documented/i.test(text)) return null
  return text
}

function outcomeName(row) {
  if (!row || typeof row !== 'object') return null
  const named = row.phenotype || row.label || row.trait || row.name || row.mutation || row.base_color
  if (named && typeof named === 'object') return named.display || named.name || named.description || null
  if (named) return String(named)
  if (row.genotype) return String(row.genotype)
  if (row.sex_label || row.sex) return String(row.sex_label || row.sex)
  return null
}

function rankedOutcomes(rows) {
  return [...(rows || [])]
    .map((row) => ({ name: outcomeName(row), pct: toPercent(row) }))
    .filter((row) => row.name)
    .sort((a, b) => (b.pct ?? -1) - (a.pct ?? -1))
}

function joinOutcomes(rows, limit = 2) {
  const list = rankedOutcomes(rows).slice(0, limit)
  if (!list.length) return 'Not calculated for this pair'
  return list.map((row) => (row.pct == null ? row.name : `${row.name} · ${formatPct(row.pct)}`)).join('  ·  ')
}

function distributionValue(rows, noun) {
  const list = rankedOutcomes(rows)
  if (!list.length) return 'Not calculated for this pair'
  const name = list[0].name
  const tooLong = name.length > 72 || /joint genotype/i.test(name)
  if (!tooLong) return joinOutcomes(rows, 2)
  const share = list[0].pct == null ? '' : ` · highest share ${formatPct(list[0].pct)}`
  return `${rows.length} ${noun}${share}`
}

function modeKey(locus) {
  return String(locus?.mode?.key || locus?.inheritanceType || '').toLowerCase()
}

function countModes(loci = []) {
  const dominant = loci.filter((locus) => /dominant/.test(modeKey(locus)) && !/recessive/.test(modeKey(locus))).length
  const recessive = loci.filter((locus) => /recessive/.test(modeKey(locus))).length
  const sexLinked = loci.filter((locus) => locus.sexLinked || /sex_linked|sex-linked/.test(modeKey(locus))).length
  return { dominant, recessive, sexLinked, total: loci.length }
}

function factorLine(factor, fallback) {
  if (!factor) return fallback || 'Not stored on this result'
  const weight = Number.isFinite(factor.weightPercent) ? `${Math.round(factor.weightPercent)}% weight` : 'weight not stored'
  return `${factor.points} / ${factor.max} points · ${weight}`
}

export default function PanelistProcessBrief({
  pair,
  gica,
  gicaScore,
  rbgiaTrace,
  algorithm,
  forecast,
  probabilities,
  complexityReport,
  species,
  parentSnapshot,
  onOpen,
  onDownload,
}) {
  const model = useMemo(
    () => buildCompatibilityModel(gica || {}, species || {}),
    [gica, species],
  )
  const loci = rbgiaTrace?.loci || []
  const modes = countModes(loci)
  const summary = rbgiaTrace?.summary || {}
  const parent1 = parentSnapshot?.parent_1?.bird_id || pair?.parent_1?.bird_id || 'Parent 1'
  const parent2 = parentSnapshot?.parent_2?.bird_id || pair?.parent_2?.bird_id || 'Parent 2'
  const method = algorithm?.method || summary.method || complexityReport?.method || 'AGAPORA-RBGIA-GICA-v3'
  const scoreText = gicaScore == null ? 'Not documented' : `${Math.round(gicaScore)} / 100`
  const status = pair?.label || gica?.label || model.label || 'Not documented'
  const eggs = documented(forecast?.estimated_eggs)
  const hatchlings = documented(forecast?.estimated_hatchlings)
  const hatchRate = documented(forecast?.hatch_rate || forecast?.hatch_rate_percent)
  const hatchPct = formatPct(forecast?.hatch_rate ?? forecast?.hatch_rate_percent)
  const factors = model.factors || []
  const factorByKey = Object.fromEntries(factors.map((factor) => [factor.key, factor]))

  const recommendation = pair?.recommendation || gica?.recommendation || ''
  const ledger = [
    { label: 'Pair', value: `${parent1} × ${parent2}`, section: 'compatibility' },
    { label: 'Score', value: `${scoreText} · ${status}`, section: 'final-output' },
    { label: 'Sex of chicks', value: joinOutcomes(probabilities?.sex), section: 'distribution' },
    {
      label: 'Looks',
      value: summary.appearanceCount
        ? joinOutcomes(rbgiaTrace?.appearanceOutcomes, 2)
        : distributionValue(probabilities?.phenotype, 'looks'),
      section: 'distribution',
    },
    { label: 'Visual mutations', value: joinOutcomes(probabilities?.visual_mutations), section: 'distribution' },
    { label: 'Hidden genes', value: joinOutcomes(probabilities?.split_hidden_genes), section: 'distribution' },
    { label: 'Expected eggs', value: eggs || 'Not documented', section: 'forecast' },
    { label: 'Expected chicks', value: hatchlings || 'Not documented', section: 'forecast' },
    { label: 'Hatch rate', value: hatchPct || hatchRate || 'Not documented', section: 'forecast' },
    {
      label: 'Genotypes',
      value: distributionValue(probabilities?.genotype, 'genotypes'),
      section: 'distribution',
    },
    { label: 'Dominant · recessive · sex-linked', value: loci.length ? `${modes.dominant} dominant · ${modes.recessive} recessive · ${modes.sexLinked} sex-linked` : 'Not calculated', section: 'inheritance' },
    { label: 'Genes used', value: loci.length ? `${modes.total} genes · ${summary.totalOutcomes ?? '—'} joint genotypes` : 'No per-gene trace stored', section: 'inheritance' },
    { label: 'Method', value: method, section: 'flow' },
    { label: 'Desirable traits', value: factorLine(factorByKey.inheritance_information), section: 'gica' },
    { label: 'Recessive risk', value: factorLine(factorByKey.genetic_risk, gica?.risk?.level ? `Risk level ${gica.risk.level}` : null), section: 'gica' },
    { label: 'Genetic diversity', value: factorLine(factorByKey.genetic_diversity, gica?.diversity?.level ? `Diversity ${gica.diversity.level}` : null), section: 'gica' },
    { label: 'Mutation load', value: factorLine(factorByKey.mutation_compatibility), section: 'gica' },
    { label: 'Species fit', value: factorLine(factorByKey.species_compatibility), section: 'gica' },
    { label: 'Breeding limits', value: factorLine(factorByKey.breeding_constraints), section: 'gica' },
    {
      label: 'Time this run used',
      value: complexityReport ? `${complexityReport.time.bound} · ${complexityReport.time.measuredOps} steps` : 'Not counted',
      section: 'complexity',
    },
    {
      label: 'Space this run used',
      value: complexityReport ? `${complexityReport.space.bound} · ${complexityReport.space.measuredUnits} stored units` : 'Not counted',
      section: 'complexity',
    },
  ]

  return (
    <section className="panel-brief" id="process-brief" aria-labelledby="panel-brief-title">
      <header className="panel-brief__mast">
        <p className="panel-brief__kicker">{parent1} × {parent2}</p>
        <h2 id="panel-brief-title">Pair result</h2>
        <p className="panel-brief__score">
          <strong>{scoreText}</strong>
          <span>{status}</span>
        </p>
        <p>{recommendation || 'Open the score proof to read the recommendation for this pair.'}</p>
        {onDownload ? (
          <button type="button" className="thesis-report-btn" onClick={onDownload}>
            Generate Report
          </button>
        ) : null}
      </header>

      <div className="panel-brief__ledger-wrap">
        <h3>Read in this order</h3>
        <p>
          The score comes first, then the chicks, then the nest. Proof opens that step in a window. Eggs and chicks are on the next tab.
        </p>
        <dl className="panel-brief__ledger">
          {ledger.map((row) => (
            <div key={row.label}>
              <dt>{row.label}</dt>
              <dd>{row.value}</dd>
              <dd>
                <button type="button" onClick={() => onOpen?.(row.section)}>
                  Proof
                </button>
              </dd>
            </div>
          ))}
        </dl>
      </div>

      <p className="panel-brief__disclaimer">
        This is an estimate from the stored pair. It does not guarantee a nest, a hatch, or the exact chicks you will see.
      </p>
    </section>
  )
}

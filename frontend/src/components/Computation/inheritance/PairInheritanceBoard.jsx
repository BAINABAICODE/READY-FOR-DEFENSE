import { useMemo, useState } from 'react'
import { describeAllele } from '../../../services/genetics'
import { barColor } from '../shared/format'

function toPercent(value) {
  if (typeof value === 'number' && Number.isFinite(value)) {
    if (value >= 0 && value <= 1) return Math.round(value * 1000) / 10
    return Math.round(value * 10) / 10
  }
  if (value && typeof value === 'object') {
    if (typeof value.probability === 'number') return toPercent(value.probability)
    if (value.fraction) return toPercent(value.fraction)
  }
  return null
}

function formatPercent(value) {
  const pct = toPercent(value)
  return pct == null ? '—' : `${pct}%`
}

function sexName(row) {
  const value = String(row?.sex || row?.sex_label || row?.trait || '').toLowerCase()
  if (value.includes('cock') || value === 'male') return 'Male / Cock'
  if (value.includes('hen') || value === 'female') return 'Female / Hen'
  return row?.trait || row?.name || '—'
}

function rowName(row) {
  return row?.trait || row?.name || row?.phenotype || row?.genotype || sexName(row)
}

function parentPass(locus, side) {
  const alleles = locus.alleles?.[side] || []
  const code = locus.parents?.[side]?.code || alleles.join('/')
  const meanings = alleles.map((allele) => describeAllele(allele, locus.name).plain)
  return { code: code || '—', meanings: [...new Set(meanings)] }
}

function chickLook(outcome) {
  const mapped = outcome.phenotype || {}
  if (mapped.visualMutations?.length) return mapped.visualMutations.join(', ')
  if (mapped.baseColor) return mapped.baseColor
  if (mapped.expressionLabel && mapped.expressionLabel !== '—') return mapped.expressionLabel
  if (outcome.sex && outcome.sex !== 'both') {
    return outcome.sex === 'cock' || outcome.sex === 'male' ? 'Male / Cock' : 'Female / Hen'
  }
  return outcome.genotype || 'Stored outcome'
}

function sortRows(rows) {
  return [...(rows || [])]
    .filter((row) => toPercent(row) != null)
    .sort((a, b) => (toPercent(b) ?? -1) - (toPercent(a) ?? -1))
}

export function buildInheritanceRows(loci = []) {
  return (loci || []).map((locus) => {
    const cock = parentPass(locus, 'cock')
    const hen = parentPass(locus, 'hen')
    const chicks = (locus.outcomes || []).map((outcome) => ({
      look: chickLook(outcome),
      genotype: outcome.genotype,
      percent: toPercent(outcome),
      visual: Boolean(outcome.phenotype?.visual),
      carrier: Boolean(outcome.phenotype?.carrier),
    }))
    return {
      key: locus.key || locus.name,
      gene: locus.name,
      category: locus.category,
      rule: locus.mode?.label || locus.inheritanceType || 'Stored rule',
      cock,
      hen,
      chicks,
      calculated: locus.status === 'calculated' && (locus.square?.valid !== false),
      reason: locus.reason,
    }
  })
}

function ChartGroup({ title, rows, nameResolver, empty }) {
  const sorted = sortRows(rows)
  if (!sorted.length) {
    return (
      <article className="inherit-chart">
        <h4>{title}</h4>
        <p className="inherit-board__empty">{empty || 'Not calculated for this pair.'}</p>
      </article>
    )
  }

  return (
    <article className="inherit-chart">
      <h4>{title}</h4>
      <ul>
        {sorted.map((row, index) => {
          const pct = toPercent(row) ?? 0
          const name = nameResolver ? nameResolver(row) : rowName(row)
          return (
            <li key={`${title}-${name}-${index}`} className={index === 0 ? 'is-top' : undefined}>
              <div className="inherit-chart__meta">
                <span>{name}</span>
                <strong>{formatPercent(pct)}</strong>
              </div>
              <div className="inherit-chart__track" role="img" aria-label={`${name} ${formatPercent(pct)}`}>
                <span style={{ width: `${Math.max(2, Math.min(100, pct))}%`, background: barColor(name, index) }} />
              </div>
            </li>
          )
        })}
      </ul>
    </article>
  )
}

export default function PairInheritanceBoard({
  trace,
  probabilities = {},
  title = 'What this pair can pass',
}) {
  const [view, setView] = useState('both')
  const rows = useMemo(() => buildInheritanceRows(trace?.loci || []), [trace])
  const cockLabel = trace?.roles?.cock?.birdId
    ? `Cock · ${trace.roles.cock.birdId}`
    : 'Cock'
  const henLabel = trace?.roles?.hen?.birdId
    ? `Hen · ${trace.roles.hen.birdId}`
    : 'Hen'

  const unavailable = (category) => (
    (probabilities.unavailable || []).find((item) => item.category === category)?.reason
  )

  if (!rows.length && !probabilities.sex && !probabilities.base_color) {
    return (
      <section className="inherit-board" aria-label={title}>
        <p className="inherit-board__empty">
          Inheritance table and graph need stored parental genotypes. Missing genes were not invented.
        </p>
      </section>
    )
  }

  return (
    <section className="inherit-board" aria-label={title}>
      <header className="inherit-board__head">
        <div>
          <p className="inherit-board__kicker">Pair inheritance check</p>
          <h3>{title}</h3>
          <p>
            Each row is one stored gene. The table shows what the cock and hen can pass.
            The graph is the same Punnett odds, grouped so you can compare looks at a glance.
          </p>
        </div>
        <div className="inherit-board__switch" role="tablist" aria-label="Inheritance view">
          {[
            ['both', 'Table and graph'],
            ['table', 'Table'],
            ['graph', 'Graph'],
          ].map(([id, label]) => (
            <button
              key={id}
              type="button"
              role="tab"
              aria-selected={view === id}
              className={view === id ? 'is-active' : undefined}
              onClick={() => setView(id)}
            >
              {label}
            </button>
          ))}
        </div>
      </header>

      {view !== 'graph' && rows.length ? (
        <div className="inherit-board__table-wrap">
          <table className="inherit-board__table">
            <caption className="gx-visually-hidden">What each parent passes and what chicks can inherit</caption>
            <thead>
              <tr>
                <th scope="col">Gene</th>
                <th scope="col">{cockLabel} passes</th>
                <th scope="col">{henLabel} passes</th>
                <th scope="col">Chicks can inherit</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.key}>
                  <th scope="row">
                    <span className="inherit-board__gene">{row.gene}</span>
                    <span className="inherit-board__rule">{row.rule}</span>
                  </th>
                  <td>
                    <code>{row.cock.code}</code>
                    <small>{row.cock.meanings.join(' · ')}</small>
                  </td>
                  <td>
                    <code>{row.hen.code}</code>
                    <small>{row.hen.meanings.join(' · ')}</small>
                  </td>
                  <td>
                    {row.calculated && row.chicks.length ? (
                      <ul className="inherit-board__chicks">
                        {row.chicks.map((chick, index) => (
                          <li key={`${row.key}-${chick.genotype}-${index}`}>
                            <span>{chick.look}{chick.carrier ? ' · split / hidden' : ''}</span>
                            <strong>{formatPercent(chick.percent)}</strong>
                          </li>
                        ))}
                      </ul>
                    ) : (
                      <span className="inherit-board__empty">{row.reason || 'Not calculated.'}</span>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}

      {view !== 'table' ? (
        <div className="inherit-board__graphs">
          <ChartGroup
            title="Sex"
            rows={probabilities.sex}
            nameResolver={sexName}
            empty={unavailable('chromosomal_sex')}
          />
          <ChartGroup
            title="Base color"
            rows={probabilities.base_color}
            empty={unavailable('base_color')}
          />
          <ChartGroup
            title="Visual mutations"
            rows={probabilities.visual_mutations}
            empty={unavailable('visual_mutation')}
          />
          <ChartGroup
            title="Split / hidden genes"
            rows={probabilities.split_hidden_genes}
            empty={unavailable('split_gene')}
          />
        </div>
      ) : null}
    </section>
  )
}

import { useMemo } from 'react'
import Disclosure from '../shared/Disclosure'
import { ProbabilityBar, StatusPill } from '../shared/primitives'
import { eggStatusShort, eggStatusTone } from './eggStatus'

function Stat({ label, value, hint }) {
  return (
    <div className="clutch-stat">
      <dt>{label}</dt>
      <dd>{value ?? '—'}</dd>
      {hint ? <p className="clutch-stat__hint">{hint}</p> : null}
    </div>
  )
}

export default function ClutchSimulationPanel({ simulation, eggs = [] }) {
  const counts = simulation?.counts || {}
  const level = simulation?.compatibility_level || {}
  const distribution = simulation?.clutch_distribution || []

  const eggTimeline = useMemo(() => (simulation?.eggs || []).map((egg) => {
    const card = eggs.find((e) => Number(e.egg_number) === Number(egg.egg_number))
    return { ...egg, card }
  }), [simulation, eggs])

  if (!simulation) {
    return (
      <section className="clutch-panel clutch-panel--legacy" aria-labelledby="clutch-panel-title">
        <p className="compute-result__eyebrow">Pair compatibility → clutch simulation</p>
        <h3 id="clutch-panel-title">Compatibility-based clutch simulation not stored</h3>
        <p className="compute-result__empty">
          This result predates the clutch simulation layer. Egg cards below are illustrative RBGIA genetic outcomes only. Re-run the
          computation to obtain a GICA-driven simulated clutch.
        </p>
      </section>
    )
  }

  const hatchedLabel = `${counts.hatched ?? 0} Hatched`
  const outcomeLines = [
    hatchedLabel,
    `${counts.unfertilized ?? 0} Unfertilized`,
    `${counts.failed_to_develop ?? 0} Failed to Develop`,
    `${counts.failed_to_hatch ?? 0} Failed to Hatch`,
  ]

  return (
    <section className="clutch-panel" aria-labelledby="clutch-panel-title">
      <header className="clutch-panel__head">
        <div>
          <p className="compute-result__eyebrow">Nest order</p>
          <h3 id="clutch-panel-title">Expected eggs and chicks</h3>
        </div>
      </header>

      <dl className="clutch-stats">
        <Stat label="Score" value={simulation.gica_score != null ? `${simulation.gica_score} / 100` : '—'} />
        <Stat label="Pair rating" value={level.label || '—'} hint={level.range ? `Score range ${level.range}` : null} />
        <Stat label="Eggs" value={`${counts.eggs_laid ?? simulation.clutch_size ?? '—'} eggs`} hint={`Usual range ${simulation.allowed_range?.min ?? 3}–${simulation.allowed_range?.max ?? 7}`} />
        <Stat label="Living chicks" value={counts.living_chicks ?? '—'} />
        <Stat label="What happened" value={<ul className="clutch-outcome-lines">{outcomeLines.map((line) => <li key={line}>{line}</li>)}</ul>} />
      </dl>

      <p className="clutch-panel__explanation">{simulation.explanation}</p>

      <ol className="clutch-timeline" aria-label="Eggs in nest order">
        {eggTimeline.map((egg) => {
          const short = eggStatusShort(egg.status, egg.status_label)
          const card = egg.card
          const genetics = card && egg.status === 'living_chick'
            ? [card.sex_label || card.sex, card.base_color, ...(card.visual_mutations || [])].filter(Boolean).join(' · ')
            : null
          return (
            <li key={egg.egg_number} className={`clutch-egg is-${egg.status}`}>
              <span className="clutch-egg__number">Egg #{egg.egg_number}</span>
              <StatusPill tone={eggStatusTone(egg.status)}>{short}</StatusPill>
              {genetics ? <span className="clutch-egg__genetics">{genetics}</span> : null}
              {egg.status === 'living_chick' && card?.probability?.fraction ? (
                <span className="clutch-egg__prob">RBGIA {card.probability.fraction}</span>
              ) : null}
            </li>
          )
        })}
      </ol>

      <Disclosure title="Why this many eggs" defaultOpen={false}>
        <p className="gx-muted">
          Sampled clutch size roll: <code className="gx-code">{simulation.clutch_roll}</code> against the cumulative distribution below →{' '}
          <strong>{simulation.clutch_size} eggs</strong>. Higher compatibility shifts probability toward larger clutches; it never guarantees one.
        </p>
        <ul className="clutch-distribution">
          {distribution.map((row) => (
            <li key={row.eggs} className={row.eggs === simulation.clutch_size ? 'is-sampled' : ''}>
              <span className="clutch-distribution__label">{row.eggs} eggs</span>
              <ProbabilityBar probability={row.percent / 100} label={`${row.eggs} eggs`} tone={row.eggs === simulation.clutch_size ? 'brand' : 'muted'} compact />
            </li>
          ))}
        </ul>
        <h4>Simulated viability tendencies</h4>
        <ul className="clutch-viability">
          {Object.entries(simulation.viability_modifiers || {}).map(([stage, p]) => (
            <li key={stage}><span>{stage}</span><strong>{Math.round(p * 100)}%</strong></li>
          ))}
        </ul>
        <table className="gx-table clutch-levels">
          <caption>All compatibility levels (configured in one place on the server)</caption>
          <thead>
            <tr><th scope="col">Level</th><th scope="col">GICA range</th><th scope="col">3</th><th scope="col">4</th><th scope="col">5</th><th scope="col">6</th><th scope="col">7</th><th scope="col">Tendency</th></tr>
          </thead>
          <tbody>
            {(simulation.levels || []).map((row) => (
              <tr key={row.key} className={row.key === level.key ? 'is-current' : ''}>
                <th scope="row">{row.label}</th>
                <td>{row.range}</td>
                {row.clutch.map((c) => <td key={c.eggs}>{c.percent}%</td>)}
                <td>{row.tendency}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </Disclosure>

      <Disclosure title="How each egg was decided" defaultOpen={false}>
        <p className="gx-muted">
          Each stage compares a seeded roll with the stage success tendency. A living chick then samples its genotype from the RBGIA
          pool below — GICA never changes those probabilities.
        </p>
        <table className="gx-table clutch-trace">
          <thead>
            <tr><th scope="col">Egg</th><th scope="col">Fertilization</th><th scope="col">Development</th><th scope="col">Hatching</th><th scope="col">RBGIA sample</th><th scope="col">Result</th></tr>
          </thead>
          <tbody>
            {eggTimeline.map((egg) => {
              const byStage = Object.fromEntries((egg.stages || []).map((s) => [s.stage, s]))
              const cell = (s) => (s ? `${s.roll.toFixed(4)} ≤ ${s.success_probability} → ${s.passed ? 'pass' : 'fail'}` : '—')
              const sampled = egg.card && egg.status === 'living_chick'
                ? [egg.card.sex_label || egg.card.sex, egg.card.base_color, ...(egg.card.visual_mutations || [])].filter(Boolean).join(' · ')
                : (egg.outcome_key || 'outcome')
              return (
                <tr key={egg.egg_number}>
                  <th scope="row">#{egg.egg_number}</th>
                  <td>{cell(byStage.fertilization)}</td>
                  <td>{cell(byStage.development)}</td>
                  <td>{cell(byStage.hatching)}</td>
                  <td>{egg.genetic_outcome_roll != null ? `${egg.genetic_outcome_roll.toFixed(4)} → ${sampled}${egg.card?.probability?.fraction ? ` (${egg.card.probability.fraction})` : ''}` : '—'}</td>
                  <td>{eggStatusShort(egg.status, egg.status_label)}</td>
                </tr>
              )
            })}
          </tbody>
        </table>
        <h4>RBGIA outcome pool (unchanged by GICA)</h4>
        <ul className="clutch-pool">
          {(simulation.genetic_outcome_pool?.weights || []).map((w) => (
            <li key={w.outcome_key || w.index}>
              <span title={w.outcome_key || undefined}>RBGIA outcome {w.index + 1}</span>
              <ProbabilityBar probability={w.probability} label={`RBGIA outcome ${w.index + 1}`} compact />
            </li>
          ))}
        </ul>
      </Disclosure>

      <p className="clutch-panel__disclaimer">{simulation.disclaimer}</p>
    </section>
  )
}

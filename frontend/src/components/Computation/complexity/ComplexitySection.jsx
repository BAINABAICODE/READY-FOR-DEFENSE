import { StatusPill } from '../shared/primitives'

function ComplexityTable({ rows, mode }) {
  const showTime = mode === 'time'
  return (
    <div className="gx-table-wrap">
      <table className="gx-table">
        <caption className="gx-visually-hidden">
          {showTime ? 'Time complexity of each stored calculation' : 'Space complexity of each stored calculation'}
        </caption>
        <thead>
          <tr>
            <th scope="col">Calculation</th>
            <th scope="col">What was counted</th>
            <th scope="col">{showTime ? 'Time' : 'Space'}</th>
            <th scope="col">{showTime ? 'Operations' : 'Stored units'}</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={row.id}>
              <th scope="row">
                {row.name}
                <span className="gx-muted gx-stack">{row.group}</span>
              </th>
              <td>{row.detail}</td>
              <td><code className="gx-code">{showTime ? row.time : row.space}</code></td>
              <td>{showTime ? row.timeOps : row.spaceUnits}</td>
            </tr>
          ))}
        </tbody>
        <tfoot>
          <tr>
            <th scope="row">This pair</th>
            <td>Sum of the rows above — not a generic constant.</td>
            <td />
            <td>
              {showTime
                ? rows.reduce((sum, row) => sum + row.timeOps, 0)
                : rows.reduce((sum, row) => sum + row.spaceUnits, 0)}
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
  )
}

export default function ComplexitySection({ mode, report, proves }) {
  const isTime = mode === 'time'
  const headline = isTime ? report.time : report.space
  const { L, C, P, R, F, E, S } = report.variables

  return (
    <section className="compute-result__card compute-section-panel">
      <p className="compute-result__eyebrow">08 · Optional · this does not change the chicks or the score</p>
      <h2>{isTime ? 'How much time this run took' : 'How much this run stored'}</h2>
      {proves ? (
        <p className="compute-proves">
          <span className="compute-proves__label">Why this step</span>
          {proves}
        </p>
      ) : null}
      <p className="compute-read">
        {isTime
          ? 'Why: a beginner can ignore this and the breeding result stays the same. How: each row counts the Punnett cells, chick combinations, score factors, and nest stages this pair actually used.'
          : 'Why: this is bookkeeping, not a second genetic cross. How: each row counts the squares, chick rows, score records, and eggs this result kept.'}
      </p>

      <div className="compute-summary__grid compute-summary__grid--forecast">
        <article className="compute-metric">
          <p className="compute-metric__label">Asymptotic bound</p>
          <p className="compute-metric__value">{headline.bound}</p>
          <p className="compute-metric__hint">{headline.explanation}</p>
        </article>
        <article className="compute-metric">
          <p className="compute-metric__label">This computation</p>
          <p className="compute-metric__value">{headline.substituted}</p>
          <p className="compute-metric__hint">
            {isTime
              ? `${headline.measuredOps} primitive combination steps counted from the payload.`
              : `${headline.measuredUnits} stored records counted from the payload.`}
          </p>
        </article>
        <article className="compute-metric">
          <p className="compute-metric__label">Counts used</p>
          <p className="compute-metric__value">L={L} · C={C} · P={P}</p>
          <p className="compute-metric__hint">R={R} joint rows · F={F} GICA factors · E={E} eggs · S={S} stages</p>
        </article>
      </div>

      <div className="compute-complexity__vars" aria-label="Variable definitions">
        <StatusPill tone="info">L loci calculated</StatusPill>
        <StatusPill tone="info">C Punnett cells</StatusPill>
        <StatusPill tone="info">P = Π kᵢ joint product</StatusPill>
        <StatusPill tone="info">R retained rows</StatusPill>
        <StatusPill tone="info">F GICA factors</StatusPill>
        <StatusPill tone="info">E eggs · S stages</StatusPill>
      </div>

      <h3>{isTime ? 'Time cost of every calculation' : 'Space cost of every calculation'}</h3>
      <ComplexityTable rows={report.calculations} mode={mode} />
    </section>
  )
}

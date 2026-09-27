import { GenotypeBadge, SexBadge } from '../shared/primitives'
import { percentText } from '../shared/format'

/** F5 — shows every gamete path that contributes to an outcome and the aggregated fraction. */
export default function FormulaTrace({ locus }) {
  if (!locus.outcomes.length) return <p className="gx-muted">Nothing to aggregate.</p>
  const carrier = locus.carrierHomozygous
  return (
    <div className="gx-aggregate">
      {carrier ? (
        <p className="gx-formula__line">
          Visual homozygous from split carriers: {carrier.formula}. {carrier.statement}
        </p>
      ) : null}
      <p className="gx-formula__line">
        P(outcome) = Σ P(gamete<sub>cock</sub>) × P(gamete<sub>hen</sub>) = (equivalent cells) ÷ (total cells)
      </p>
      <ul className="gx-aggregate__list">
        {locus.outcomes.map((outcome) => (
          <li key={`${outcome.sex}-${outcome.genotype}`} className="gx-aggregate__item">
            <div className="gx-aggregate__head">
              <GenotypeBadge genotype={outcome.genotype} />
              {outcome.sex !== 'both' ? <SexBadge sex={outcome.sex} label={outcome.sex === 'cock' ? '♂' : '♀'} /> : null}
              <strong className="gx-aggregate__pct">{percentText(outcome.probability)}</strong>
              <span className="gx-muted">= {outcome.count} / {outcome.total} cells = {outcome.fraction}</span>
            </div>
            <ol className="gx-aggregate__paths">
              {outcome.paths.map((path, index) => (
                <li key={index}>
                  Path {index + 1}: P({path.fromCock} from cock) × P({path.fromHen} from hen) = {path.expression}
                </li>
              ))}
            </ol>
            <p className="gx-aggregate__sum">Σ = {outcome.paths.map((p) => p.probability.toFixed(2)).join(' + ')} = <strong>{outcome.probability.toFixed(2)}</strong> → {percentText(outcome.probability)}</p>
          </li>
        ))}
      </ul>
      <p className="gx-note">Check: {locus.outcomes.map((o) => percentText(o.probability)).join(' + ')} = {percentText(locus.probabilitySum)}.</p>
    </div>
  )
}

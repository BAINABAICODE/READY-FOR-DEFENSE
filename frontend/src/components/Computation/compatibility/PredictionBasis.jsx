import { CheckMark, StatusPill } from '../shared/primitives'

export default function PredictionBasis({ confidence, summary }) {
  const genotype = confidence.genotypeState
  const pedigree = confidence.pedigreeState
  return (
    <div className="gx-basis">
      <dl className="gx-basis__list">
        <div><dt>Deterministic inheritance rules</dt><dd><CheckMark ok labels={{ ok: 'Same parents, same result' }} /></dd></div>
        <div><dt>Complete parental genotype</dt><dd><CheckMark ok={genotype === 'complete'} unknown={genotype === 'insufficient'} labels={{ ok: 'Complete for all calculated loci', no: 'Partial', unknown: 'Insufficient data' }} /></dd></div>
        <div><dt>Grandparent records</dt><dd><CheckMark ok={pedigree === 'provided'} unknown={pedigree === 'not_provided'} labels={{ ok: 'Provided for both parents', no: 'Provided for one parent', unknown: 'Not provided' }} /></dd></div>
        <div><dt>Mutation database coverage</dt><dd><CheckMark ok={confidence.coverage >= 1} labels={{ ok: `${confidence.lociCalculated} / ${confidence.lociTotal} loci calculated`, no: `${confidence.lociCalculated} / ${confidence.lociTotal} loci calculated (${confidence.coveragePercent}%)` }} /></dd></div>
        <div><dt>Engine data-confidence level</dt><dd>{confidence.backendLevel || 'Not provided'}{confidence.backendMessage ? <span className="gx-muted"> — {confidence.backendMessage}</span> : null}</dd></div>
        <div><dt>Method</dt><dd>{summary?.method || 'AGAPORA RBGIA'}</dd></div>
      </dl>
      <p className="gx-basis__verdict">
        Prediction confidence: <StatusPill tone={confidence.overall === 'High' ? 'ok' : confidence.overall === 'Moderate' ? 'warn' : 'bad'}>{confidence.overall}</StatusPill>
      </p>
      <p className="gx-note">
        Every percentage on this page is a theoretical inheritance probability derived from Mendelian allele combination and the stored
        mutation database. It is a predicted probability, not a guarantee of offspring; actual clutch results may differ.
      </p>
    </div>
  )
}

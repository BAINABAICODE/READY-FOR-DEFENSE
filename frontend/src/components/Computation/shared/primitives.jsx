import { alleleClass, describeAllele } from '../../../services/genetics'
import { barColor, percentText } from './format'

export function ProbabilityBar({ probability, label, tone = 'brand', compact = false, color }) {
  const pct = Math.max(0, Math.min(100, (Number(probability) || 0) * 100))
  return (
    <div className={`gx-bar is-${tone}${compact ? ' is-compact' : ''}`} role="img" aria-label={`${label ? `${label}: ` : ''}${percentText(probability)}`}>
      <div className="gx-bar__track" aria-hidden="true">
        <div className="gx-bar__fill" style={{ width: `${pct}%`, background: color || barColor(label) }} />
      </div>
      <span className="gx-bar__value" aria-hidden="true">{percentText(probability)}</span>
    </div>
  )
}

export function AlleleBadge({ allele, title }) {
  const desc = describeAllele(allele)
  const cls = alleleClass(allele)
  return (
    <span className={`gx-allele is-${cls}`} title={title || desc.meaning || desc.kindLabel}>
      <span className="gx-allele__symbol">{allele}</span>
      <span className="gx-allele__kind" aria-hidden="true">{cls === 'w' ? 'W' : cls === 'wild' ? '+' : 'm'}</span>
    </span>
  )
}

export function AllelePlain({ allele, locusName }) {
  const desc = describeAllele(allele, locusName)
  return (
    <span className="gx-allele-plain">
      <AlleleBadge allele={allele} title={desc.meaning} />
      <span className="gx-allele-plain__text">{desc.plain}</span>
    </span>
  )
}

export function GenotypeBadge({ genotype }) {
  const raw = String(genotype || '').trim()
  if (!raw) return <span className="gx-muted">—</span>
  const alleles = raw.split('/').map((s) => s.trim()).filter(Boolean)
  if (alleles.length !== 2 || raw.includes('|')) {
    return <code className="gx-code" title="Stored code could not be parsed into a diploid allele pair">{raw}</code>
  }
  return (
    <span className="gx-genotype" aria-label={`genotype ${genotype}`}>
      {alleles.map((allele, index) => (
        <span key={`${allele}-${index}`} className="gx-genotype__part">
          {index > 0 ? <span className="gx-genotype__slash" aria-hidden="true">/</span> : null}
          <AlleleBadge allele={allele} />
        </span>
      ))}
    </span>
  )
}

export function SexBadge({ sex, label }) {
  const value = String(sex || '').toLowerCase()
  if (value === 'cock' || value === 'male') return <span className="gx-sex is-cock"><span aria-hidden="true">♂</span> {label || 'Male / Cock'}</span>
  if (value === 'hen' || value === 'female') return <span className="gx-sex is-hen"><span aria-hidden="true">♀</span> {label || 'Female / Hen'}</span>
  return <span className="gx-sex is-both">{label || 'Either sex'}</span>
}

export function ModeBadge({ mode }) {
  if (!mode) return null
  const tone = mode.sexLinked ? 'sexlinked' : mode.incomplete ? 'incomplete' : mode.recessive ? 'recessive' : mode.dominant ? 'dominant' : mode.unknown ? 'unknown' : 'fixed'
  return <span className={`gx-mode is-${tone}`}>{mode.label}</span>
}

export function ExpressionBadge({ expression, label }) {
  const tone = !expression ? 'unknown' : expression.startsWith('visual') ? 'visual' : expression === 'carrier_split' ? 'carrier' : 'wild'
  return <span className={`gx-expression is-${tone}`}>{label || expression || '—'}</span>
}

export function StatusPill({ tone = 'neutral', children }) {
  return <span className={`gx-pill is-${tone}`}>{children}</span>
}

export function CheckMark({ ok, unknown = false, labels = { ok: 'Yes', no: 'No', unknown: 'Not provided' } }) {
  if (unknown || ok == null) return <span className="gx-check is-unknown">— {labels.unknown}</span>
  return ok
    ? <span className="gx-check is-ok"><span aria-hidden="true">✓</span> {labels.ok}</span>
    : <span className="gx-check is-no"><span aria-hidden="true">✕</span> {labels.no}</span>
}

export function KeyValue({ rows }) {
  return (
    <dl className="gx-kv">
      {rows.map(([key, value]) => (
        <div key={key}>
          <dt>{key}</dt>
          <dd>{value == null || value === '' ? <span className="gx-muted">Not provided</span> : value}</dd>
        </div>
      ))}
    </dl>
  )
}

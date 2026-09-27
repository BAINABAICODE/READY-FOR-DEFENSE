import { GenotypeBadge, ModeBadge, ProbabilityBar, SexBadge } from '../shared/primitives'
import { breedingOutcome, outcomeBaseColor, outcomeSplit, outcomeVisual, percentText } from '../shared/format'
import { resolveInheritanceMode } from '../../../services/genetics'
import PhenotypeMap from '../inheritance/PhenotypeMap'

function LociGenotype({ genotype }) {
  const parts = String(genotype || '').split('|').map((s) => s.trim()).filter(Boolean)
  if (!parts.length) return <span className="gx-muted">Not provided</span>
  return (
    <ul className="gx-card__loci">
      {parts.map((part) => {
        const [name, code] = part.split(':').map((s) => s.trim())
        return <li key={part}><span className="gx-card__locus-name">{name}</span> <GenotypeBadge genotype={code || name} /></li>
      })}
    </ul>
  )
}

function inheritanceSummary(row) {
  const modes = new Map()
  ;(row.loci || []).forEach((locus) => {
    if (!locus.probability || locus.probability >= 1) return
    const mode = resolveInheritanceMode(locus.inheritance_type, locus.category)
    modes.set(mode.key, mode)
  })
  if (!modes.size && row.inheritanceClasses) {
    return Object.entries(row.inheritanceClasses).filter(([, v]) => v).map(([k]) => k.replace(/_/g, ' ')).join(', ') || 'Fixed loci only'
  }
  return [...modes.values()]
}

function passLabel(expression) {
  const value = String(expression || '')
  if (value.startsWith('visual')) return 'Visual'
  if (value === 'carrier_split') return 'Split / hidden'
  if (value === 'non_carrier' || value === 'hemizygous_wild') return 'Not carried'
  if (value === 'female') return 'Hen (ZW)'
  if (value === 'male') return 'Cock (ZZ)'
  return value ? value.replace(/_/g, ' ') : 'Stored result'
}

function PassedFromParents({ rows }) {
  const list = rows || []
  if (!list.length) return <span className="gx-muted">Alleles passed by each parent were not stored for this outcome.</span>
  return (
    <ul className="gx-passed">
      {list.map((item) => (
        <li key={`${item.locus}-${item.chick_genotype}`}>
          <span className="gx-passed__locus">{item.locus}</span>
          <span>cock <code>{item.from_cock || '—'}</code> · hen <code>{item.from_hen || '—'}</code></span>
          <strong>{passLabel(item.expression)}</strong>
        </li>
      ))}
    </ul>
  )
}

function geneticExplanation(row) {
  const dark = row.darkFactor && row.darkFactor !== 'none' ? ` Dark factor ${row.darkFactor}.` : ''
  return `${breedingOutcome(row)}.${dark} That is the chick expected from the alleles these parents pass. Hidden genes are splits and are not seen on the bird.`
}

export default function OffspringOutcomeCard({ row, rank }) {
  const modes = inheritanceSummary(row)
  return (
    <article className="gx-card" aria-labelledby={`outcome-${row.id}-title`}>
      <header className="gx-card__head">
        <p className="gx-card__eyebrow" id={`outcome-${row.id}-title`}>Outcome #{rank}</p>
        <p className="gx-card__title">{breedingOutcome(row)}</p>
        <SexBadge sex={row.sex} label={row.sexLabel} />
      </header>
      {row.imageUrl ? (
        <figure className="gx-card__media">
          <img src={row.imageUrl} alt={`Illustrative visualisation of ${row.baseColor || 'offspring'}${row.visualMutations.length ? ` ${row.visualMutations.join(' ')}` : ''}, ${row.sexLabel || ''}`} loading="lazy" />
          <figcaption>Visualisation of the already-determined genotype{row.eggNumber ? ` (egg card ${row.eggNumber})` : ''}. The image does not influence the genetics.</figcaption>
        </figure>
      ) : null}
      <dl className="gx-card__facts">
        <div>
          <dt>Base color</dt>
          <dd><strong>{outcomeBaseColor(row)}</strong>{row.darkFactor && row.darkFactor !== 'none' ? ` · ${row.darkFactor}` : ''}</dd>
        </div>
        <div>
          <dt>Visual mutation</dt>
          <dd>{outcomeVisual(row)}</dd>
        </div>
        <div>
          <dt>Split / hidden</dt>
          <dd>{outcomeSplit(row)}</dd>
        </div>
        <div>
          <dt>Genotype</dt>
          <dd><LociGenotype genotype={row.genotype} /></dd>
        </div>
        <div>
          <dt>Probability</dt>
          <dd><ProbabilityBar probability={row.probability} label={`Outcome ${rank}`} compact />{row.fraction ? <span className="gx-muted"> {row.fraction}</span> : null}</dd>
        </div>
        <div>
          <dt>Sex</dt>
          <dd>{row.sexLabel || 'Not sex-linked / depends on inheritance rule'} <span className="gx-muted">(ZZ / ZW cross, 1/2 each)</span></dd>
        </div>
        <div>
          <dt>Inheritance</dt>
          <dd>{Array.isArray(modes) ? (modes.length ? modes.map((m) => <ModeBadge key={m.key} mode={m} />) : <span className="gx-muted">All loci fixed in both parents</span>) : modes}</dd>
        </div>
        <div>
          <dt>Formula trace</dt>
          <dd>{row.formula ? <code className="gx-code">F5: {row.formula}</code> : <code className="gx-code">{row.fraction || percentText(row.probability)}</code>}{row.productMatches === false ? <span className="gx-alert is-bad"> product ≠ stored</span> : null}</dd>
        </div>
        <div>
          <dt>Passed from parents</dt>
          <dd>
            <PassedFromParents rows={row.passedFromParents || row.passed_from_parents} />
            <p className="gx-note">Each parent passes one allele. Separate genes assort independently. Sex-linked alleles travel on the Z chromosome.</p>
          </dd>
        </div>
        <div>
          <dt>Genetic explanation</dt>
          <dd>{geneticExplanation(row)}</dd>
        </div>
      </dl>
      <PhenotypeMap
        source={{
          phenotype: row.phenotype,
          baseColor: row.baseColor,
          visualMutations: row.visualMutations,
          eyes: row.eyes,
          head: row.head,
          neck: row.neck,
          body: row.body,
          wings: row.wings,
          rump: row.rump,
          tail: row.tail,
          head_to_tail: row.head_to_tail || row.headToTail,
        }}
      />
      {row.phenotype ? <p className="gx-card__phenotype">{row.phenotype}</p> : null}
    </article>
  )
}

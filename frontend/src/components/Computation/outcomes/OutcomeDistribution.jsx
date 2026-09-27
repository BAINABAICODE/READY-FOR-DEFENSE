import { useState } from 'react'
import { ProbabilityBar, SexBadge } from '../shared/primitives'
import { barColor, percentText } from '../shared/format'

export default function OutcomeDistribution({ groups, total, source, tone }) {
  const [showAll, setShowAll] = useState(false)
  const visible = showAll ? groups : groups.slice(0, 10)
  const hidden = groups.length - visible.length
  return (
    <div className="gx-distribution">
      <ol className="gx-distribution__list">
        {visible.map((group, index) => (
          <li key={group.key} className="gx-distribution__row">
            <div className="gx-distribution__label">
              <span className="gx-distribution__rank">#{group.rank}</span>
              <span className="gx-distribution__name">{group.label}</span>
              {group.sex ? <SexBadge sex={group.sex} label={group.sex === 'cock' ? '♂' : '♀'} /> : null}
              <span className="gx-muted">{group.members.length} genotype{group.members.length === 1 ? '' : 's'}{group.fraction ? ` · ${group.fraction}` : ''}</span>
            </div>
            <ProbabilityBar probability={group.probability} label={group.label} color={barColor(group.label, index)} tone={tone || (group.visualMutations.length ? 'accent' : 'brand')} />
          </li>
        ))}
      </ol>
      {hidden > 0 || showAll ? (
        <button type="button" className="gx-linkbtn" onClick={() => setShowAll((v) => !v)} aria-expanded={showAll}>
          {showAll ? 'Show fewer outcomes' : `Show ${hidden} more outcome${hidden === 1 ? '' : 's'}`}
        </button>
      ) : null}
      <p className="gx-note">
        Sorted from highest to lowest predicted probability. Total = <strong>{percentText(total)}</strong>. Source: <code>{source}</code>.
      </p>
    </div>
  )
}
